<?php

namespace Tests\Unit\Payments;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

class PaymentActiveAttemptMigrationParserTest extends TestCase
{
    private const OLD = "case when status in ('pending', 'processing') then 'ACTIVE' else null end";

    private const NEW = "case when status in ('pending', 'processing', 'unresolved', 'review') then 'ACTIVE' else null end";

    #[DataProvider('acceptedExpressions')]
    public function test_strict_parser_accepts_only_harmless_variations(string $expression, string $expected): void
    {
        $this->assertSame($expected, $this->classifyExpression($expression));
    }

    /** @return array<string, array{string, string}> */
    public static function acceptedExpressions(): array
    {
        return [
            'canonical old' => [self::OLD, 'OLD'],
            'canonical new' => [self::NEW, 'NEW'],
            'keywords whitespace backticks charset and outer parentheses' => [
                " (((CASE\nWHEN `payments` . `status` IN (_utf8mb4'pending', _utf8mb4'processing')\n"
                    ."THEN _utf8mb4'ACTIVE' ELSE NULL END))) ",
                'OLD',
            ],
            'unqualified backticked identifier' => [
                "CASE WHEN `status` IN ('pending','processing','unresolved','review') THEN 'ACTIVE' ELSE NULL END",
                'NEW',
            ],
            'mysql information schema escaped quotes' => [
                "(case when (`status` in (_utf8mb4\\'pending\\',_utf8mb4\\'processing\\')) "
                    ."then _utf8mb4\\'ACTIVE\\' else NULL end)",
                'OLD',
            ],
        ];
    }

    #[DataProvider('adversarialExpressions')]
    public function test_strict_parser_rejects_adversarial_expression(string $expression): void
    {
        $this->assertNull($this->classifyExpression($expression));
    }

    /** @return array<string, array{string}> */
    public static function adversarialExpressions(): array
    {
        return [
            'pending suffix' => ["case when status in ('pending_x', 'processing') then 'ACTIVE' else null end"],
            'processing suffix' => ["case when status in ('pending', 'processing_old') then 'ACTIVE' else null end"],
            'wrong active result' => ["case when status in ('pending', 'processing') then 'ACTIVE_WRONG' else null end"],
            'test active result' => ["case when status in ('pending', 'processing') then 'ACTIVE_TEST' else null end"],
            'status suffix identifier' => ["case when status_suffix in ('pending', 'processing') then 'ACTIVE' else null end"],
            'another status identifier' => ["case when another_status in ('pending', 'processing') then 'ACTIVE' else null end"],
            'extra fifth status' => ["case when status in ('pending', 'processing', 'unresolved', 'review', 'failed') then 'ACTIVE' else null end"],
            'missing status' => ["case when status in ('pending', 'processing', 'unresolved') then 'ACTIVE' else null end"],
            'duplicate status' => ["case when status in ('pending', 'processing', 'review', 'review') then 'ACTIVE' else null end"],
            'wrong case result literal' => ["case when status in ('pending', 'processing') then 'active' else null end"],
            'non-null else' => ["case when status in ('pending', 'processing') then 'ACTIVE' else 'ACTIVE' end"],
            'concatenation' => ["case when status in ('pending', 'processing') then concat('ACT', 'IVE') else null end"],
            'function call' => ["case when lower(status) in ('pending', 'processing') then 'ACTIVE' else null end"],
            'comment' => ["case when status in ('pending', 'processing') then 'ACTIVE' else null end /* accepted */"],
            'trailing sql' => ["case when status in ('pending', 'processing') then 'ACTIVE' else null end + 0"],
            'expected substring' => ["coalesce((case when status in ('pending', 'processing') then 'ACTIVE' else null end), 'ACTIVE')"],
            'fake introducer separated from literal' => ["case when status in (_utf8mb4 'pending', 'processing') then 'ACTIVE' else null end"],
            'unknown charset introducer' => ["case when status in (_notcharset'pending', 'processing') then 'ACTIVE' else null end"],
            'underscore outside literal' => ["case when _status in ('pending', 'processing') then 'ACTIVE' else null end"],
            'wrong qualifier' => ["case when other.status in ('pending', 'processing') then 'ACTIVE' else null end"],
        ];
    }

    #[DataProvider('schemaStates')]
    public function test_classifier_recognizes_only_complete_expected_states(
        string $expression,
        bool $withIndex,
        string $expected,
    ): void {
        $inventory = $this->validInventory($expression, $withIndex);

        $this->assertSame($expected, $this->invoke('classify', $inventory));
    }

    /** @return array<string, array{string, bool, string}> */
    public static function schemaStates(): array
    {
        return [
            'full old' => [self::OLD, true, 'FULL_OLD'],
            'full new' => [self::NEW, true, 'FULL_NEW'],
            'old without index' => [self::OLD, false, 'OLD_WITHOUT_INDEX'],
            'new without index' => [self::NEW, false, 'NEW_WITHOUT_INDEX'],
        ];
    }

    #[DataProvider('invalidReconcileDefinitions')]
    public function test_classifier_rejects_incompatible_reconcile_until(?array $definition, string $diagnostic): void
    {
        $inventory = $this->validInventory(self::OLD, true);
        $inventory['reconcile'] = $definition;

        try {
            $this->invoke('classify', $inventory);
            $this->fail('An incompatible reconcile_until definition must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($diagnostic, $exception->getMessage());
            $this->assertStringContainsString('No DDL was executed', $exception->getMessage());
        }
    }

    /** @return array<string, array{array<string, mixed>|null, string}> */
    public static function invalidReconcileDefinitions(): array
    {
        $valid = self::validReconcile();

        return [
            'missing' => [null, 'reconcile_until column is missing'],
            'wrong type' => [[...$valid, 'DATA_TYPE' => 'datetime', 'COLUMN_TYPE' => 'datetime'], 'DATA_TYPE=datetime'],
            'non-nullable' => [[...$valid, 'IS_NULLABLE' => 'NO'], 'IS_NULLABLE=NO'],
            'generated' => [[...$valid, 'EXTRA' => 'VIRTUAL GENERATED', 'GENERATION_EXPRESSION' => 'now()'], 'GENERATION_EXPRESSION=present'],
            'wrong default' => [[...$valid, 'COLUMN_DEFAULT' => 'CURRENT_TIMESTAMP'], 'COLUMN_DEFAULT=CURRENT_TIMESTAMP'],
            'on update' => [[...$valid, 'EXTRA' => 'DEFAULT_GENERATED on update CURRENT_TIMESTAMP'], 'on update CURRENT_TIMESTAMP'],
        ];
    }

    public function test_classifier_accepts_exact_reconcile_until_definition(): void
    {
        $this->assertSame('FULL_OLD', $this->invoke('classify', $this->validInventory(self::OLD, true)));
    }

    private function classifyExpression(string $expression): ?string
    {
        return $this->invoke('classifyExpression', $expression);
    }

    private function invoke(string $method, mixed ...$arguments): mixed
    {
        $migration = require dirname(__DIR__, 3)
            .'/database/migrations/2026_08_04_121000_harden_active_payment_attempt_states.php';

        return (new ReflectionMethod($migration, $method))->invoke($migration, ...$arguments);
    }

    /** @return array<string, mixed> */
    private function validInventory(string $expression, bool $withIndex): array
    {
        return [
            'driver' => 'mysql',
            'table' => true,
            'active' => [
                'COLUMN_NAME' => 'active_attempt_key',
                'DATA_TYPE' => 'varchar',
                'COLUMN_TYPE' => 'varchar(16)',
                'IS_NULLABLE' => 'YES',
                'EXTRA' => 'VIRTUAL GENERATED',
                'GENERATION_EXPRESSION' => $expression,
                'CHARACTER_SET_NAME' => 'utf8mb4',
                'COLLATION_NAME' => 'utf8mb4_unicode_ci',
            ],
            'reconcile' => self::validReconcile(),
            'provider' => ['IS_NULLABLE' => 'NO'],
            'index' => $withIndex ? [
                ['NON_UNIQUE' => 0, 'SEQ_IN_INDEX' => 1, 'COLUMN_NAME' => 'booking_id', 'SUB_PART' => null],
                ['NON_UNIQUE' => 0, 'SEQ_IN_INDEX' => 2, 'COLUMN_NAME' => 'provider', 'SUB_PART' => null],
                ['NON_UNIQUE' => 0, 'SEQ_IN_INDEX' => 3, 'COLUMN_NAME' => 'active_attempt_key', 'SUB_PART' => null],
            ] : [],
            'status_counts' => [],
        ];
    }

    /** @return array<string, mixed> */
    private static function validReconcile(): array
    {
        return [
            'COLUMN_NAME' => 'reconcile_until',
            'DATA_TYPE' => 'timestamp',
            'COLUMN_TYPE' => 'timestamp',
            'IS_NULLABLE' => 'YES',
            'COLUMN_DEFAULT' => null,
            'EXTRA' => '',
            'GENERATION_EXPRESSION' => '',
            'DATETIME_PRECISION' => 0,
        ];
    }
}
