<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const COMPATIBILITY_INDEX = 'booking_seats_showtime_fk_support';

    private const OLD_UNIQUE_INDEX = 'booking_seats_showtime_id_seat_id_unique';

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $inventory = $this->inventory();
        if ($this->upDecision($inventory) === 'ADD') {
            DB::statement(
                'ALTER TABLE `booking_seats` ADD INDEX `'.self::COMPATIBILITY_INDEX.'` (`showtime_id`)',
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $inventory = $this->inventory();
        $compatibility = $inventory['indexes'][self::COMPATIBILITY_INDEX] ?? [];
        if ($compatibility === []) {
            return;
        }
        if (! $this->isCompatibilityIndex($compatibility)) {
            throw new RuntimeException(
                self::COMPATIBILITY_INDEX.' has an unexpected definition: '
                .$this->indexDescription($compatibility).'. No DDL was executed.',
            );
        }

        if ($this->hasAlternativeSupport($inventory['indexes'], false)) {
            DB::statement('ALTER TABLE `booking_seats` DROP INDEX `'.self::COMPATIBILITY_INDEX.'`');
        }
    }

    /** @return array{table: bool, showtime_column: bool, foreign_keys: list<array<string, mixed>>, indexes: array<string, list<array<string, mixed>>} */
    private function inventory(): array
    {
        $database = DB::connection()->getDatabaseName();
        $table = (int) DB::selectOne(
            <<<'SQL'
                SELECT COUNT(*) AS aggregate
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'booking_seats'
                SQL,
            [$database],
        )->aggregate === 1;

        if (! $table) {
            return ['table' => false, 'showtime_column' => false, 'foreign_keys' => [], 'indexes' => []];
        }

        $showtimeColumn = (int) DB::selectOne(
            <<<'SQL'
                SELECT COUNT(*) AS aggregate
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'booking_seats' AND COLUMN_NAME = 'showtime_id'
                SQL,
            [$database],
        )->aggregate === 1;
        $foreignRows = DB::select(
            <<<'SQL'
                SELECT CONSTRAINT_NAME, COLUMN_NAME, ORDINAL_POSITION, POSITION_IN_UNIQUE_CONSTRAINT,
                       REFERENCED_TABLE_SCHEMA, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'booking_seats' AND REFERENCED_TABLE_NAME IS NOT NULL
                ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION
                SQL,
            [$database],
        );
        $indexRows = DB::select(
            <<<'SQL'
                SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART, INDEX_TYPE, IS_VISIBLE
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'booking_seats'
                ORDER BY INDEX_NAME, SEQ_IN_INDEX
                SQL,
            [$database],
        );

        $indexes = [];
        foreach ($indexRows as $row) {
            $values = (array) $row;
            $indexes[(string) $values['INDEX_NAME']][] = $values;
        }

        return [
            'table' => true,
            'showtime_column' => $showtimeColumn,
            'foreign_keys' => array_map(fn (object $row): array => (array) $row, $foreignRows),
            'indexes' => $indexes,
        ];
    }

    /** @param array{table: bool, showtime_column: bool, foreign_keys: list<array<string, mixed>>, indexes: array<string, list<array<string, mixed>>} $inventory */
    private function upDecision(array $inventory): string
    {
        if (! $inventory['table']) {
            return 'NOOP';
        }

        $compatibility = $inventory['indexes'][self::COMPATIBILITY_INDEX] ?? [];
        if ($compatibility !== [] && ! $this->isCompatibilityIndex($compatibility)) {
            throw new RuntimeException(
                self::COMPATIBILITY_INDEX.' has an unexpected definition: '
                .$this->indexDescription($compatibility).'. No DDL was executed.',
            );
        }
        if ($compatibility !== []) {
            return 'NOOP';
        }

        $oldUnique = $inventory['indexes'][self::OLD_UNIQUE_INDEX] ?? [];
        if (! $inventory['showtime_column']
            || ! $this->hasShowtimeForeignKey($inventory['foreign_keys'])
            || ! $this->isOldUniqueIndex($oldUnique)
            || $this->hasAlternativeSupport($inventory['indexes'])) {
            return 'NOOP';
        }

        return 'ADD';
    }

    /** @param list<array<string, mixed>> $rows */
    private function hasShowtimeForeignKey(array $rows): bool
    {
        return collect($rows)->groupBy('CONSTRAINT_NAME')->contains(function ($constraint): bool {
            $first = $constraint->sortBy(fn (array $row): int => (int) $row['ORDINAL_POSITION'])->first();

            return ($first['COLUMN_NAME'] ?? null) === 'showtime_id'
                && ($first['REFERENCED_TABLE_NAME'] ?? null) === 'showtimes'
                && ($first['REFERENCED_COLUMN_NAME'] ?? null) === 'id';
        });
    }

    /** @param array<string, list<array<string, mixed>>> $indexes */
    private function hasAlternativeSupport(array $indexes, bool $excludeOldUnique = true): bool
    {
        foreach ($indexes as $name => $rows) {
            if ($name === self::COMPATIBILITY_INDEX
                || ($excludeOldUnique && $name === self::OLD_UNIQUE_INDEX)) {
                continue;
            }
            if ($this->beginsWithShowtime($rows)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array<string, mixed>> $rows */
    private function isCompatibilityIndex(array $rows): bool
    {
        return count($rows) === 1
            && (int) ($rows[0]['NON_UNIQUE'] ?? 0) === 1
            && $this->beginsWithShowtime($rows);
    }

    /** @param list<array<string, mixed>> $rows */
    private function isOldUniqueIndex(array $rows): bool
    {
        return count($rows) === 2
            && (int) ($rows[0]['NON_UNIQUE'] ?? 1) === 0
            && (int) ($rows[0]['SEQ_IN_INDEX'] ?? 0) === 1
            && ($rows[0]['COLUMN_NAME'] ?? null) === 'showtime_id'
            && ($rows[0]['SUB_PART'] ?? null) === null
            && (int) ($rows[1]['NON_UNIQUE'] ?? 1) === 0
            && (int) ($rows[1]['SEQ_IN_INDEX'] ?? 0) === 2
            && ($rows[1]['COLUMN_NAME'] ?? null) === 'seat_id'
            && ($rows[1]['SUB_PART'] ?? null) === null;
    }

    /** @param list<array<string, mixed>> $rows */
    private function beginsWithShowtime(array $rows): bool
    {
        if ($rows === []) {
            return false;
        }

        $first = $rows[0];

        return (int) ($first['SEQ_IN_INDEX'] ?? 0) === 1
            && ($first['COLUMN_NAME'] ?? null) === 'showtime_id'
            && ($first['SUB_PART'] ?? null) === null
            && strtoupper((string) ($first['INDEX_TYPE'] ?? 'BTREE')) === 'BTREE'
            && strtoupper((string) ($first['IS_VISIBLE'] ?? 'YES')) === 'YES';
    }

    /** @param list<array<string, mixed>> $rows */
    private function indexDescription(array $rows): string
    {
        return implode(', ', array_map(
            fn (array $row): string => sprintf(
                '#%d %s %s prefix=%s type=%s visible=%s',
                (int) ($row['SEQ_IN_INDEX'] ?? 0),
                (string) ($row['COLUMN_NAME'] ?? 'unknown'),
                (int) ($row['NON_UNIQUE'] ?? 0) === 1 ? 'non-unique' : 'unique',
                ($row['SUB_PART'] ?? null) === null ? 'none' : (string) $row['SUB_PART'],
                (string) ($row['INDEX_TYPE'] ?? 'unknown'),
                (string) ($row['IS_VISIBLE'] ?? 'unknown'),
            ),
            $rows,
        ));
    }
};
