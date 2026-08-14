<?php

namespace Tests\MySql;

use App\Models\Promotion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

#[Group('mysql-integration')]
final class PromotionQuotaMySqlTest extends TestCase
{
    use CreatesBookingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $database = (string) DB::connection()->getDatabaseName();
        $this->assertSame('moviemate_phase4_rehearsal', $database, "Unsafe MySQL integration database [{$database}].");
        $this->assertSame(0, Artisan::call('migrate:fresh', ['--force' => true]), Artisan::output());
    }

    public function test_two_real_connections_cannot_oversubscribe_the_final_global_quota_slot(): void
    {
        $scenario = $this->bookingScenario(false);
        $promotion = Promotion::query()->create([
            'code' => 'MYSQL-LAST-SLOT',
            'name' => 'MySQL final slot',
            'type' => Promotion::TYPE_FIXED,
            'discount_amount_vnd' => 10_000,
            'minimum_order_vnd' => 0,
            'global_usage_limit' => 1,
            'is_active' => true,
        ]);
        $first = $this->bookingForScenario($scenario);
        $second = $this->bookingForScenario($scenario);

        DB::beginTransaction();
        try {
            Promotion::query()->whereKey($promotion->id)->lockForUpdate()->firstOrFail();
            $processes = collect([$first, $second])->map(fn ($booking) => new Process([
                PHP_BINARY,
                base_path('tests/Support/reserve-promotion-slot.php'),
                (string) $booking->id,
                $promotion->code,
            ], base_path(), timeout: 30));
            $processes->each->start();

            $deadline = microtime(true) + 10;
            while ($processes->contains(fn (Process $process): bool => ! $process->isRunning()) && microtime(true) < $deadline) {
                usleep(20_000);
            }
        } finally {
            DB::rollBack();
        }

        $processes->each->wait();
        $results = $processes->map(fn (Process $process): array => [
            'exit' => $process->getExitCode(),
            'stdout' => $process->getOutput(),
            'stderr' => $process->getErrorOutput(),
        ])->all();

        $this->assertSame([0, 2], collect($results)->pluck('exit')->sort()->values()->all(), json_encode($results));
        $this->assertSame(1, DB::table('booking_promotions')->where('promotion_id', $promotion->id)->whereIn('status', ['reserved', 'redeemed'])->count());
        $this->assertSame(1, collect($results)->filter(fn (array $result): bool => str_contains($result['stdout'], 'reserved'))->count());
        $this->assertSame(1, collect($results)->filter(fn (array $result): bool => str_contains($result['stdout'], 'rejected'))->count());

        if (getenv('REPORT_QUERY_COUNTS') === '1') {
            fwrite(STDOUT, 'PROMOTION_MYSQL_CONCURRENCY='.json_encode([
                'attempts' => 2, 'reserved' => 1, 'rejected' => 1, 'consumed' => 1,
            ], JSON_THROW_ON_ERROR).PHP_EOL);
        }
    }

    public function test_mysql_checks_reject_invalid_type_shapes_limits_dates_and_duplicate_codes(): void
    {
        $base = [
            'code' => 'MYSQL-RAW', 'name' => 'MySQL raw', 'type' => Promotion::TYPE_FIXED,
            'discount_amount_vnd' => 10_000, 'discount_percent' => null,
            'maximum_discount_vnd' => null, 'minimum_order_vnd' => 0,
            'is_active' => true, 'registered_users_only' => false, 'first_order_only' => false,
            'created_at' => now(), 'updated_at' => now(),
        ];
        $invalid = [
            ['discount_percent' => 10],
            ['maximum_discount_vnd' => 1],
            ['type' => Promotion::TYPE_PERCENTAGE, 'discount_amount_vnd' => 1, 'discount_percent' => 10],
            ['type' => Promotion::TYPE_PERCENTAGE, 'discount_amount_vnd' => null, 'discount_percent' => 0],
            ['type' => Promotion::TYPE_PERCENTAGE, 'discount_amount_vnd' => null, 'discount_percent' => 101],
            ['type' => Promotion::TYPE_PERCENTAGE, 'discount_amount_vnd' => null, 'discount_percent' => 10, 'maximum_discount_vnd' => 0],
            ['global_usage_limit' => 0],
            ['per_user_usage_limit' => 0],
            ['starts_at' => '2026-08-14 10:00:00', 'ends_at' => '2026-08-14 10:00:00'],
        ];

        foreach ($invalid as $index => $changes) {
            try {
                DB::table('promotions')->insert([...$base, 'code' => 'MYSQL-RAW-'.$index, ...$changes]);
                $this->fail('MySQL accepted invalid Promotion shape '.$index);
            } catch (QueryException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }

        DB::table('promotions')->insert($base);
        $this->expectException(QueryException::class);
        DB::table('promotions')->insert([...$base, 'name' => 'Duplicate']);
    }
}
