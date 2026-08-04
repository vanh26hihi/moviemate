<?php

namespace Tests\Unit\Payments;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

class BookingSeatCompatibilityMigrationTest extends TestCase
{
    public function test_no_table_is_a_safe_noop(): void
    {
        $this->assertSame('NOOP', $this->compatibilityDecision($this->inventory(table: false)));
    }

    public function test_table_and_foreign_key_without_old_unique_is_a_safe_noop(): void
    {
        $this->assertSame('NOOP', $this->compatibilityDecision($this->inventory()));
    }

    public function test_old_unique_as_sole_foreign_key_support_adds_compatibility_index(): void
    {
        $inventory = $this->inventory();
        $inventory['indexes']['booking_seats_showtime_id_seat_id_unique'] = $this->index(
            'booking_seats_showtime_id_seat_id_unique',
            ['showtime_id', 'seat_id'],
            false,
        );

        $this->assertSame('ADD', $this->compatibilityDecision($inventory));
    }

    public function test_permanent_showtime_leading_index_prevents_compatibility_index(): void
    {
        $inventory = $this->inventoryWithOldUnique();
        $inventory['indexes']['booking_seats_showtime_seat_index'] = $this->index(
            'booking_seats_showtime_seat_index',
            ['showtime_id', 'seat_id'],
        );

        $this->assertSame('NOOP', $this->compatibilityDecision($inventory));
    }

    public function test_correct_compatibility_index_is_idempotent(): void
    {
        $inventory = $this->inventoryWithOldUnique();
        $inventory['indexes']['booking_seats_showtime_fk_support'] = $this->index(
            'booking_seats_showtime_fk_support',
            ['showtime_id'],
        );

        $this->assertSame('NOOP', $this->compatibilityDecision($inventory));
    }

    public function test_same_name_wrong_definition_aborts_before_ddl(): void
    {
        $inventory = $this->inventoryWithOldUnique();
        $inventory['indexes']['booking_seats_showtime_fk_support'] = $this->index(
            'booking_seats_showtime_fk_support',
            ['seat_id', 'showtime_id'],
        );

        try {
            $this->compatibilityDecision($inventory);
            $this->fail('The same-name wrong-definition index must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('#1 seat_id non-unique', $exception->getMessage());
            $this->assertStringContainsString('No DDL was executed', $exception->getMessage());
        }
    }

    public function test_wrong_old_unique_order_does_not_trigger_ddl(): void
    {
        $inventory = $this->inventory();
        $inventory['indexes']['booking_seats_showtime_id_seat_id_unique'] = $this->index(
            'booking_seats_showtime_id_seat_id_unique',
            ['seat_id', 'showtime_id'],
            false,
        );

        $this->assertSame('NOOP', $this->compatibilityDecision($inventory));
    }

    public function test_cleanup_drops_exact_compatibility_index_only_with_alternative_support(): void
    {
        $inventory = $this->inventory();
        $inventory['indexes']['booking_seats_showtime_fk_support'] = $this->index(
            'booking_seats_showtime_fk_support',
            ['showtime_id'],
        );
        $inventory['indexes']['booking_seats_showtime_seat_index'] = $this->index(
            'booking_seats_showtime_seat_index',
            ['showtime_id', 'seat_id'],
        );

        $this->assertSame('DROP', $this->cleanupDecision($inventory));
    }

    public function test_cleanup_leaves_only_supporting_index_in_place(): void
    {
        $inventory = $this->inventory();
        $inventory['indexes']['booking_seats_showtime_fk_support'] = $this->index(
            'booking_seats_showtime_fk_support',
            ['showtime_id'],
        );

        $this->assertSame('NOOP', $this->cleanupDecision($inventory));
    }

    public function test_existing_deployment_shape_is_a_noop_for_both_migrations(): void
    {
        $inventory = $this->inventory();
        $inventory['indexes']['booking_seats_showtime_seat_index'] = $this->index(
            'booking_seats_showtime_seat_index',
            ['showtime_id', 'seat_id'],
        );

        $this->assertSame('NOOP', $this->compatibilityDecision($inventory));
        $this->assertSame('NOOP', $this->cleanupDecision($inventory));
    }

    /** @return array<string, mixed> */
    private function inventory(bool $table = true): array
    {
        return [
            'table' => $table,
            'showtime_column' => $table,
            'foreign_keys' => $table ? [[
                'CONSTRAINT_NAME' => 'booking_seats_showtime_id_foreign',
                'COLUMN_NAME' => 'showtime_id',
                'ORDINAL_POSITION' => 1,
                'REFERENCED_TABLE_NAME' => 'showtimes',
                'REFERENCED_COLUMN_NAME' => 'id',
            ]] : [],
            'indexes' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function inventoryWithOldUnique(): array
    {
        $inventory = $this->inventory();
        $inventory['indexes']['booking_seats_showtime_id_seat_id_unique'] = $this->index(
            'booking_seats_showtime_id_seat_id_unique',
            ['showtime_id', 'seat_id'],
            false,
        );

        return $inventory;
    }

    /** @return list<array<string, mixed>> */
    private function index(string $name, array $columns, bool $nonUnique = true): array
    {
        return array_map(fn (string $column, int $offset): array => [
            'INDEX_NAME' => $name,
            'NON_UNIQUE' => $nonUnique ? 1 : 0,
            'SEQ_IN_INDEX' => $offset + 1,
            'COLUMN_NAME' => $column,
            'SUB_PART' => null,
            'INDEX_TYPE' => 'BTREE',
            'IS_VISIBLE' => 'YES',
        ], $columns, array_keys($columns));
    }

    /** @param array<string, mixed> $inventory */
    private function compatibilityDecision(array $inventory): string
    {
        $migration = require dirname(__DIR__, 3)
            .'/database/migrations/2026_07_16_235959_ensure_booking_seat_showtime_fk_support.php';

        return (new ReflectionMethod($migration, 'upDecision'))->invoke($migration, $inventory);
    }

    /** @param array<string, mixed> $inventory */
    private function cleanupDecision(array $inventory): string
    {
        $migration = require dirname(__DIR__, 3)
            .'/database/migrations/2026_08_04_123000_remove_booking_seat_fk_compatibility_index.php';

        return (new ReflectionMethod($migration, 'upDecision'))->invoke($migration, $inventory);
    }
}
