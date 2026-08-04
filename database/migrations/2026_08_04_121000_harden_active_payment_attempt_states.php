<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const INDEX = 'payments_one_active_attempt_unique';

    private const INDEX_COLUMNS = ['booking_id', 'provider', 'active_attempt_key'];

    private const OLD_EXPRESSION = "case when status in ('pending', 'processing') then 'ACTIVE' else null end";

    private const NEW_EXPRESSION = "case when status in ('pending', 'processing', 'unresolved', 'review') then 'ACTIVE' else null end";

    private const BLOCKING_STATUSES = ['pending', 'processing', 'unresolved', 'review'];

    public function up(): void
    {
        $inventory = $this->inventory();
        $state = $this->classify($inventory);

        $this->assertNoDuplicateAttempts(self::BLOCKING_STATUSES, 'upgrade');

        if ($state === 'FULL_NEW') {
            return;
        }

        $this->assertNoDuplicateAttempts(self::BLOCKING_STATUSES, 'upgrade');
        $this->applyExpression($inventory, self::NEW_EXPRESSION, $inventory['index'] !== []);
    }

    public function down(): void
    {
        $inventory = $this->inventory();
        $state = $this->classify($inventory);

        $reviewIds = DB::table('payments')
            ->whereIn('status', ['unresolved', 'review'])
            ->orderBy('id')
            ->pluck('id');

        if ($reviewIds->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot restore the old active-attempt expression while unresolved or review payments exist. '
                .'Resolve these payment IDs first: '.$reviewIds->implode(',').'. No DDL was executed.',
            );
        }

        $this->assertNoDuplicateAttempts(['pending', 'processing'], 'rollback');

        if ($state === 'FULL_OLD') {
            return;
        }

        $this->assertNoDuplicateAttempts(['pending', 'processing'], 'rollback');
        $this->applyExpression($inventory, self::OLD_EXPRESSION, $inventory['index'] !== []);
    }

    /** @return array<string, mixed> */
    private function inventory(): array
    {
        return DB::getDriverName() === 'mysql'
            ? $this->mysqlInventory()
            : $this->sqliteInventory();
    }

    /** @return array<string, mixed> */
    private function mysqlInventory(): array
    {
        $database = DB::connection()->getDatabaseName();
        $tableExists = (int) DB::selectOne(
            <<<'SQL'
                SELECT COUNT(*) AS aggregate
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'payments'
                SQL,
            [$database],
        )->aggregate === 1;

        $activeColumn = DB::selectOne(
            <<<'SQL'
                SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, EXTRA, GENERATION_EXPRESSION,
                       CHARACTER_SET_NAME, COLLATION_NAME
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'active_attempt_key'
                SQL,
            [$database],
        );
        $reconcileColumn = DB::selectOne(
            <<<'SQL'
                SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, EXTRA
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'reconcile_until'
                SQL,
            [$database],
        );
        $providerColumn = DB::selectOne(
            <<<'SQL'
                SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, EXTRA
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'provider'
                SQL,
            [$database],
        );
        $indexRows = DB::select(
            <<<'SQL'
                SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART, COLLATION, INDEX_TYPE
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'payments' AND INDEX_NAME = ?
                ORDER BY SEQ_IN_INDEX
                SQL,
            [$database, self::INDEX],
        );

        $statusCounts = $tableExists
            ? DB::table('payments')->select('status', DB::raw('COUNT(*) AS aggregate'))
                ->whereIn('status', self::BLOCKING_STATUSES)
                ->groupBy('status')->orderBy('status')->pluck('aggregate', 'status')->all()
            : [];

        return [
            'driver' => 'mysql',
            'table' => $tableExists,
            'active' => $activeColumn ? (array) $activeColumn : null,
            'reconcile' => $reconcileColumn ? (array) $reconcileColumn : null,
            'provider' => $providerColumn ? (array) $providerColumn : null,
            'index' => array_map(fn (object $row): array => (array) $row, $indexRows),
            'status_counts' => $statusCounts,
        ];
    }

    /** @return array<string, mixed> */
    private function sqliteInventory(): array
    {
        $table = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'payments'");
        $columns = $table ? collect(DB::select("PRAGMA table_xinfo('payments')"))->keyBy('name') : collect();
        $active = $columns->get('active_attempt_key');
        $reconcile = $columns->get('reconcile_until');
        $provider = $columns->get('provider');
        $index = $table
            ? collect(DB::select("PRAGMA index_list('payments')"))->firstWhere('name', self::INDEX)
            : null;
        $indexRows = $index
            ? array_map(
                fn (object $row): array => [
                    'INDEX_NAME' => self::INDEX,
                    'NON_UNIQUE' => (int) $index->unique === 1 ? 0 : 1,
                    'SEQ_IN_INDEX' => (int) $row->seqno + 1,
                    'COLUMN_NAME' => $row->name,
                    'SUB_PART' => null,
                ],
                DB::select("PRAGMA index_info('".self::INDEX."')"),
            )
            : [];

        return [
            'driver' => 'sqlite',
            'table' => $table !== null,
            'table_sql' => $table?->sql,
            'active' => $active ? [
                'COLUMN_NAME' => $active->name,
                'DATA_TYPE' => strtolower((string) $active->type),
                'COLUMN_TYPE' => strtolower((string) $active->type),
                'IS_NULLABLE' => (int) $active->notnull === 0 ? 'YES' : 'NO',
                'EXTRA' => (int) $active->hidden === 2 ? 'VIRTUAL GENERATED' : 'UNKNOWN',
                'GENERATION_EXPRESSION' => $this->sqliteExpression((string) $table?->sql),
            ] : null,
            'reconcile' => $reconcile ? (array) $reconcile : null,
            'provider' => $provider ? ['IS_NULLABLE' => (int) $provider->notnull === 0 ? 'YES' : 'NO'] : null,
            'index' => $indexRows,
            'status_counts' => $table
                ? DB::table('payments')->select('status', DB::raw('COUNT(*) AS aggregate'))
                    ->whereIn('status', self::BLOCKING_STATUSES)
                    ->groupBy('status')->orderBy('status')->pluck('aggregate', 'status')->all()
                : [],
        ];
    }

    private function sqliteExpression(string $tableSql): ?string
    {
        $normalized = $this->normalize($tableSql);

        if (str_contains($normalized, $this->normalize(self::NEW_EXPRESSION))) {
            return self::NEW_EXPRESSION;
        }

        if (str_contains($normalized, $this->normalize(self::OLD_EXPRESSION))) {
            return self::OLD_EXPRESSION;
        }

        return null;
    }

    /** @param array<string, mixed> $inventory */
    private function classify(array $inventory): string
    {
        $present = [];
        $invalid = [];

        $inventory['table'] ? $present[] = 'payments table' : $invalid[] = 'payments table is missing';

        $active = $inventory['active'];
        if ($active === null) {
            $invalid[] = 'active_attempt_key generated column is missing';
        } else {
            $present[] = 'active_attempt_key column';
            $columnType = strtolower((string) ($active['COLUMN_TYPE'] ?? ''));
            $dataType = strtolower((string) ($active['DATA_TYPE'] ?? ''));
            $typeCompatible = $inventory['driver'] === 'mysql'
                ? $dataType === 'varchar' && $columnType === 'varchar(16)'
                : in_array($columnType, ['varchar', 'varchar(16)'], true);
            $virtual = str_contains(strtoupper((string) ($active['EXTRA'] ?? '')), 'VIRTUAL GENERATED');
            $nullable = strtoupper((string) ($active['IS_NULLABLE'] ?? '')) === 'YES';
            $charsetCompatible = $inventory['driver'] !== 'mysql'
                || (preg_match('/^[a-z0-9_]+$/D', (string) ($active['CHARACTER_SET_NAME'] ?? '')) === 1
                    && preg_match('/^[a-z0-9_]+$/D', (string) ($active['COLLATION_NAME'] ?? '')) === 1);

            if (! $typeCompatible || ! $virtual || ! $nullable || ! $charsetCompatible) {
                $invalid[] = 'active_attempt_key must be nullable VARCHAR(16) VIRTUAL GENERATED; found '
                    .$columnType.', '.($active['EXTRA'] ?? 'no generated storage mode').', nullable='.(string) ($active['IS_NULLABLE'] ?? 'unknown');
            }
        }

        $inventory['reconcile'] !== null
            ? $present[] = 'reconcile_until column'
            : $invalid[] = 'reconcile_until column is missing';

        $provider = $inventory['provider'];
        if ($provider === null) {
            $invalid[] = 'provider column is missing';
        } elseif (strtoupper((string) ($provider['IS_NULLABLE'] ?? '')) !== 'NO') {
            $invalid[] = 'provider column must be NOT NULL';
        } else {
            $present[] = 'provider NOT NULL column';
        }

        $indexRows = $inventory['index'];
        $indexExists = $indexRows !== [];
        $indexCorrect = ! $indexExists || $this->indexIsCorrect($indexRows);
        if ($indexExists && $indexCorrect) {
            $present[] = self::INDEX.' unique ('.implode(', ', self::INDEX_COLUMNS).')';
        } elseif ($indexExists) {
            $invalid[] = self::INDEX.' has wrong uniqueness, columns, order, or prefix: '.$this->indexDescription($indexRows);
        }

        $expression = $active['GENERATION_EXPRESSION'] ?? null;
        $normalizedExpression = is_string($expression) ? $this->normalize($expression) : null;
        $old = $normalizedExpression === $this->normalize(self::OLD_EXPRESSION);
        $new = $normalizedExpression === $this->normalize(self::NEW_EXPRESSION);
        if ($active !== null && ! $old && ! $new) {
            $invalid[] = 'active_attempt_key has an unknown generated expression';
        } elseif ($old) {
            $present[] = 'old generated expression';
        } elseif ($new) {
            $present[] = 'hardened generated expression';
        }

        if ($invalid !== []) {
            throw new RuntimeException(
                'Unexpected payments active-attempt schema state. Present: '.($present === [] ? 'none' : implode('; ', $present)).'. '
                .'Missing or invalid: '.implode('; ', $invalid).'. '
                .'Repair guidance: restore reconcile_until and provider NOT NULL, then restore active_attempt_key as nullable '
                .'VARCHAR(16) VIRTUAL GENERATED using the old or hardened expression and either remove or correct '.self::INDEX.'. '
                .'No DDL was executed.',
            );
        }

        return match (true) {
            $old && $indexExists => 'FULL_OLD',
            $new && $indexExists => 'FULL_NEW',
            $old => 'OLD_WITHOUT_INDEX',
            $new => 'NEW_WITHOUT_INDEX',
            default => throw new RuntimeException('Unexpected payments active-attempt schema state. No DDL was executed.'),
        };
    }

    /** @param list<array<string, mixed>> $rows */
    private function indexIsCorrect(array $rows): bool
    {
        if (count($rows) !== count(self::INDEX_COLUMNS)) {
            return false;
        }

        foreach ($rows as $offset => $row) {
            if ((int) ($row['NON_UNIQUE'] ?? 1) !== 0
                || (int) ($row['SEQ_IN_INDEX'] ?? 0) !== $offset + 1
                || ($row['COLUMN_NAME'] ?? null) !== self::INDEX_COLUMNS[$offset]
                || ($row['SUB_PART'] ?? null) !== null) {
                return false;
            }
        }

        return true;
    }

    /** @param list<array<string, mixed>> $rows */
    private function indexDescription(array $rows): string
    {
        return implode(', ', array_map(
            fn (array $row): string => sprintf(
                '#%d %s %s',
                (int) ($row['SEQ_IN_INDEX'] ?? 0),
                (string) ($row['COLUMN_NAME'] ?? 'unknown'),
                (int) ($row['NON_UNIQUE'] ?? 1) === 0 ? 'unique' : 'non-unique',
            ),
            $rows,
        ));
    }

    /** @param list<string> $statuses */
    private function assertNoDuplicateAttempts(array $statuses, string $operation): void
    {
        $groups = DB::table('payments')
            ->select('booking_id', 'provider')
            ->whereIn('status', $statuses)
            ->groupBy('booking_id', 'provider')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('booking_id')->orderBy('provider')
            ->get();

        if ($groups->isEmpty()) {
            return;
        }

        $details = $groups->map(function (object $group) use ($statuses): string {
            $payments = DB::table('payments')
                ->select('id', 'status')
                ->where('booking_id', $group->booking_id)
                ->where('provider', $group->provider)
                ->whereIn('status', $statuses)
                ->orderBy('id')->get();

            return 'booking_id='.$group->booking_id
                .', provider='.$group->provider
                .', payments='.$payments->map(
                    fn (object $payment): string => $payment->id.':'.$payment->status,
                )->implode('|');
        })->implode('; ');

        throw new RuntimeException(
            "Cannot {$operation} the active-attempt constraint because duplicate blocking attempts exist. "
            .'Resolve the listed payment IDs and statuses explicitly, then retry. '.$details.'. No DDL was executed.',
        );
    }

    /** @param array<string, mixed> $inventory */
    private function applyExpression(array $inventory, string $expression, bool $dropIndex): void
    {
        if ($inventory['driver'] === 'mysql') {
            $columnType = strtolower((string) $inventory['active']['COLUMN_TYPE']);
            $characterSet = (string) $inventory['active']['CHARACTER_SET_NAME'];
            $collation = (string) $inventory['active']['COLLATION_NAME'];
            $clauses = [];
            if ($dropIndex) {
                $clauses[] = 'DROP INDEX `'.self::INDEX.'`';
            }
            $clauses[] = "MODIFY COLUMN `active_attempt_key` {$columnType} CHARACTER SET {$characterSet} "
                ."COLLATE {$collation} GENERATED ALWAYS AS ({$expression}) VIRTUAL";
            $clauses[] = 'ADD UNIQUE INDEX `'.self::INDEX.'` (`booking_id`, `provider`, `active_attempt_key`)';

            DB::statement('ALTER TABLE `payments` '.implode(', ', $clauses));

            return;
        }

        if ($dropIndex) {
            DB::statement('DROP INDEX "'.self::INDEX.'"');
        }
        DB::statement('ALTER TABLE "payments" DROP COLUMN "active_attempt_key"');
        DB::statement(
            'ALTER TABLE "payments" ADD COLUMN "active_attempt_key" VARCHAR(16) '
            ."GENERATED ALWAYS AS ({$expression}) VIRTUAL",
        );
        DB::statement(
            'CREATE UNIQUE INDEX "'.self::INDEX.'" ON "payments" '
            .'("booking_id", "provider", "active_attempt_key")',
        );
    }

    private function normalize(string $expression): string
    {
        $expression = strtolower($expression);
        $expression = preg_replace('/_[a-z0-9]+/', '', $expression) ?? $expression;

        return preg_replace('/[^a-z0-9,]+/', '', $expression) ?? $expression;
    }
};
