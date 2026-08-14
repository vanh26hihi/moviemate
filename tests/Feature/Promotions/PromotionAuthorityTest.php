<?php

namespace Tests\Feature\Promotions;

use App\Models\Booking;
use App\Models\BookingPromotion;
use App\Models\Cinema;
use App\Models\Promotion;
use App\Models\User;
use App\Services\BookingCheckoutService;
use App\Services\BookingExpirationService;
use App\Services\BookingTokenService;
use App\Services\PromotionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

final class PromotionAuthorityTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_fixed_and_percentage_calculation_shapes_are_authoritative(): void
    {
        $cinema = Cinema::factory()->create();
        $this->promotion(' FIXED20K ', ['discount_amount_vnd' => 20_000]);
        $fixed = app(PromotionService::class)->quote(100_000, ' fixed20k ', null, $cinema->id);
        $this->assertSame(20_000, $fixed->discountAmount);
        $this->assertSame(80_000, $fixed->finalAmount);

        $this->promotion('CLAMP', ['discount_amount_vnd' => 200_000]);
        $this->assertSame(0, app(PromotionService::class)->quote(90_000, 'CLAMP', null, $cinema->id)->finalAmount);

        $this->promotion('PERCENT', [
            'type' => Promotion::TYPE_PERCENTAGE,
            'discount_amount_vnd' => null,
            'discount_percent' => 15,
            'maximum_discount_vnd' => null,
        ]);
        $this->assertSame(15_000, app(PromotionService::class)->quote(100_000, 'PERCENT', null, $cinema->id)->discountAmount);
        $this->assertSame(1, app(PromotionService::class)->quote(10, 'PERCENT', null, $cinema->id)->discountAmount);

        $this->promotion('CAPPED', [
            'type' => Promotion::TYPE_PERCENTAGE,
            'discount_amount_vnd' => null,
            'discount_percent' => 50,
            'maximum_discount_vnd' => 30_000,
        ]);
        $this->assertSame(30_000, app(PromotionService::class)->quote(100_000, 'CAPPED', null, $cinema->id)->discountAmount);
    }

    public function test_minimum_order_uses_full_ticket_and_food_gross_before_discount(): void
    {
        $cinema = Cinema::factory()->create();
        $this->promotion('GROSS100', ['minimum_order_vnd' => 100_000]);

        $this->expectException(ValidationException::class);
        try {
            app(PromotionService::class)->quote(99_999, 'GROSS100', null, $cinema->id);
        } finally {
            $quote = app(PromotionService::class)->quote(80_000 + 20_000, 'GROSS100', null, $cinema->id);
            $this->assertSame(80_000, $quote->finalAmount);
        }
    }

    public function test_validity_is_cinema_local_start_inclusive_and_end_exclusive(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-14 03:00:00', 'UTC'));
        $cinema = Cinema::factory()->create(['timezone' => 'Asia/Ho_Chi_Minh']);
        $this->promotion('START', ['starts_at' => '2026-08-14 10:00:00', 'ends_at' => '2026-08-14 10:01:00']);
        $this->assertSame(20_000, app(PromotionService::class)->quote(100_000, 'START', null, $cinema->id)->discountAmount);

        $this->promotion('END', ['starts_at' => '2026-08-14 09:59:00', 'ends_at' => '2026-08-14 10:00:00']);
        $this->expectException(ValidationException::class);
        app(PromotionService::class)->quote(100_000, 'END', null, $cinema->id);
    }

    public function test_scope_registered_first_order_and_per_user_rules_are_server_authoritative(): void
    {
        $cinema = Cinema::factory()->create();
        $other = Cinema::factory()->create();
        $user = User::factory()->create();
        $promotion = $this->promotion('MEMBER', ['registered_users_only' => true, 'first_order_only' => true]);
        $promotion->cinemas()->sync([$cinema->id]);

        foreach ([[null, $cinema->id], [$user->id, $other->id]] as [$userId, $cinemaId]) {
            try {
                app(PromotionService::class)->quote(100_000, 'MEMBER', $userId, $cinemaId);
                $this->fail('Expected eligibility rejection.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('promotion_code', $exception->errors());
            }
        }
        $this->assertSame(20_000, app(PromotionService::class)->quote(100_000, 'MEMBER', $user->id, $cinema->id)->discountAmount);
    }

    public function test_reservation_consumes_quota_release_reuses_it_and_redeem_is_terminal(): void
    {
        $scenario = $this->bookingScenario(false);
        $promotion = $this->promotion('LASTSLOT', ['global_usage_limit' => 1]);
        $first = $this->bookingForScenario($scenario);
        $second = $this->bookingForScenario($scenario);
        $third = $this->bookingForScenario($scenario);

        DB::transaction(fn () => app(PromotionService::class)->reserveForBooking($first, $promotion->code, 100_000));
        $this->assertSame(BookingPromotion::STATUS_RESERVED, $first->promotionUsage()->sole()->status);
        $this->assertQuotaRejected($second, $promotion);

        app(PromotionService::class)->release($first);
        app(PromotionService::class)->release($first);
        DB::transaction(fn () => app(PromotionService::class)->reserveForBooking($second, $promotion->code, 100_000));
        app(PromotionService::class)->redeem($second);
        app(PromotionService::class)->redeem($second);
        $this->assertSame(BookingPromotion::STATUS_REDEEMED, $second->promotionUsage()->sole()->status);
        $this->assertQuotaRejected($third, $promotion);
    }

    public function test_per_user_quota_counts_reserved_and_redeemed_but_not_released(): void
    {
        $scenario = $this->bookingScenario(false);
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $promotion = $this->promotion('USERONE', ['per_user_usage_limit' => 1]);
        $first = $this->bookingForScenario($scenario, ['user_id' => $user->id]);
        $second = $this->bookingForScenario($scenario, ['user_id' => $user->id]);
        $other = $this->bookingForScenario($scenario, ['user_id' => $otherUser->id]);

        DB::transaction(fn () => app(PromotionService::class)->reserveForBooking($first, $promotion->code, 100_000));
        $this->assertQuotaRejected($second, $promotion);
        DB::transaction(fn () => app(PromotionService::class)->reserveForBooking($other, $promotion->code, 100_000));
        app(PromotionService::class)->release($first);
        DB::transaction(fn () => app(PromotionService::class)->reserveForBooking($second, $promotion->code, 100_000));
        $this->assertSame(2, $promotion->usages()->where('status', BookingPromotion::STATUS_RESERVED)->count());
    }

    public function test_reserved_usage_redeems_after_promotion_end_and_archive_without_revalidation(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-14 03:00:00', 'UTC'));
        $scenario = $this->bookingScenario(false);
        $scenario['cinema']->update(['timezone' => 'Asia/Ho_Chi_Minh']);
        $promotion = $this->promotion('HONOR', ['ends_at' => '2026-08-14 10:01:00']);
        $booking = $this->bookingForScenario($scenario);
        DB::transaction(fn () => app(PromotionService::class)->reserveForBooking($booking, $promotion->code, 100_000));
        $promotion->update(['is_active' => false, 'archived_at' => now()]);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-14 03:03:00', 'UTC'));

        app(PromotionService::class)->redeem($booking);
        $this->assertSame(BookingPromotion::STATUS_REDEEMED, $booking->promotionUsage()->sole()->status);
    }

    public function test_used_definition_and_scope_are_immutable_even_after_release_but_lifecycle_is_mutable(): void
    {
        $scenario = $this->bookingScenario(false);
        $promotion = $this->promotion('FROZEN');
        $booking = $this->bookingForScenario($scenario);
        DB::transaction(fn () => app(PromotionService::class)->reserveForBooking($booking, $promotion->code, 100_000));
        app(PromotionService::class)->release($booking);

        try {
            $promotion->update(['discount_amount_vnd' => 1]);
            $this->fail('Used business definition must be immutable.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }
        try {
            $promotion->cinemas()->attach($scenario['cinema']->id);
            $this->fail('Used scope must be immutable.');
        } catch (QueryException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }

        $promotion->refresh();
        $promotion->update(['is_active' => false, 'archived_at' => now()]);
        $this->assertNotNull($promotion->fresh()->archived_at);
    }

    public function test_snapshot_is_complete_and_survives_master_archive(): void
    {
        $scenario = $this->bookingScenario(false);
        $promotion = $this->promotion('SNAPSHOT', [
            'type' => Promotion::TYPE_PERCENTAGE, 'discount_amount_vnd' => null,
            'discount_percent' => 25, 'maximum_discount_vnd' => 30_000,
            'minimum_order_vnd' => 80_000, 'registered_users_only' => true,
            'global_usage_limit' => 10, 'per_user_usage_limit' => 2,
        ]);
        $promotion->cinemas()->sync([$scenario['cinema']->id]);
        $user = User::factory()->create();
        $booking = $this->bookingForScenario($scenario, ['user_id' => $user->id]);
        DB::transaction(fn () => app(PromotionService::class)->reserveForBooking($booking, $promotion->code, 100_000));
        $promotion->update(['is_active' => false, 'archived_at' => now()]);
        $usage = $booking->promotionUsage()->sole();

        $this->assertSame('SNAPSHOT', $usage->code_snapshot);
        $this->assertSame(Promotion::TYPE_PERCENTAGE, $usage->type_snapshot);
        $this->assertSame(25, $usage->discount_percent_snapshot);
        $this->assertSame(30_000, $usage->maximum_discount_vnd_snapshot);
        $this->assertSame(80_000, $usage->minimum_order_vnd_snapshot);
        $this->assertSame('cinema', $usage->scope_kind_snapshot);
        $this->assertSame($scenario['cinema']->name, $usage->booking_cinema_name_snapshot);
        $this->assertTrue($usage->registered_users_only_snapshot);
        $this->assertSame(25_000, $usage->applied_discount_vnd);
        $this->assertSame(75_000, $usage->final_after_vnd);

        $this->withoutVite();
        $this->seedRbac();
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee('SNAPSHOT')
            ->assertSee('25%')
            ->assertSee('30.000 VNĐ')
            ->assertSee('80.000 VNĐ')
            ->assertSee($scenario['cinema']->name);
    }

    public function test_database_rejects_invalid_master_shapes_and_duplicate_booking_usage(): void
    {
        $base = [
            'code' => 'RAW', 'name' => 'Raw', 'type' => 'fixed', 'discount_amount_vnd' => 10_000,
            'discount_percent' => null, 'maximum_discount_vnd' => null, 'minimum_order_vnd' => 0,
            'is_active' => true, 'registered_users_only' => false, 'first_order_only' => false,
            'created_at' => now(), 'updated_at' => now(),
        ];
        $invalid = [
            ['discount_percent' => 10],
            ['maximum_discount_vnd' => 1],
            ['type' => 'percentage', 'discount_amount_vnd' => 1, 'discount_percent' => 10],
            ['type' => 'percentage', 'discount_amount_vnd' => null, 'discount_percent' => 0],
            ['type' => 'percentage', 'discount_amount_vnd' => null, 'discount_percent' => 101],
            ['type' => 'percentage', 'discount_amount_vnd' => null, 'discount_percent' => 10, 'maximum_discount_vnd' => 0],
            ['global_usage_limit' => 0],
            ['per_user_usage_limit' => 0],
            ['starts_at' => '2026-08-14 10:00:00', 'ends_at' => '2026-08-14 10:00:00'],
        ];
        foreach ($invalid as $index => $changes) {
            try {
                DB::table('promotions')->insert([...$base, 'code' => 'RAW'.$index, ...$changes]);
                $this->fail('Raw invalid Promotion shape was accepted: '.$index);
            } catch (QueryException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }

        $scenario = $this->bookingScenario(false);
        $promotion = $this->promotion('UNIQUE');
        $booking = $this->bookingForScenario($scenario);
        DB::transaction(fn () => app(PromotionService::class)->reserveForBooking($booking, $promotion->code, 100_000));
        $copy = (array) DB::table('booking_promotions')->where('booking_id', $booking->id)->first();
        unset($copy['id']);
        $this->expectException(QueryException::class);
        DB::table('booking_promotions')->insert($copy);
    }

    public function test_usage_snapshot_and_terminal_state_are_database_defended(): void
    {
        $scenario = $this->bookingScenario(false);
        $promotion = $this->promotion('DBGUARD');
        $booking = $this->bookingForScenario($scenario);
        DB::transaction(fn () => app(PromotionService::class)->reserveForBooking($booking, $promotion->code, 100_000));
        $usage = $booking->promotionUsage()->sole();

        try {
            DB::table('booking_promotions')->where('id', $usage->id)->update(['applied_discount_vnd' => 1]);
            $this->fail('Snapshot mutation must fail.');
        } catch (QueryException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }
        app(PromotionService::class)->release($booking);
        $this->expectException(QueryException::class);
        DB::table('booking_promotions')->where('id', $usage->id)->update([
            'status' => BookingPromotion::STATUS_REDEEMED, 'redeemed_at' => now(), 'released_at' => null,
        ]);
    }

    public function test_array_code_request_is_rejected_instead_of_silently_selecting_one(): void
    {
        $this->post(route('user.bookings.promotions'), [
            'action' => 'apply', 'code' => ['ONE', 'TWO'],
        ])->assertSessionHasErrors('code');
    }

    public function test_server_rejects_cross_type_admin_fields_and_used_admin_mutation(): void
    {
        $this->seedRbac();
        $admin = $this->userWithRole('admin');
        $base = [
            'code' => 'ADMIN-SHAPE', 'name' => 'Admin shape', 'type' => Promotion::TYPE_FIXED,
            'discount_amount_vnd' => 10_000, 'discount_percent' => 10,
            'maximum_discount_vnd' => null, 'minimum_order_vnd' => 0, 'is_active' => true,
            'global_usage_limit' => null, 'per_user_usage_limit' => null, 'cinema_ids' => [],
        ];
        $this->actingAs($admin)->post(route('admin.discounts.store'), $base)->assertSessionHasErrors('type');
        $this->assertDatabaseMissing('promotions', ['code' => 'ADMIN-SHAPE']);

        $scenario = $this->bookingScenario(false);
        $promotion = $this->promotion('ADMIN-USED');
        $booking = $this->bookingForScenario($scenario);
        DB::transaction(fn () => app(PromotionService::class)->reserveForBooking($booking, $promotion->code, 100_000));

        $this->put(route('admin.discounts.update', $promotion), [
            'is_active' => false, 'discount_amount_vnd' => 1,
        ])->assertSessionHasErrors('promotion');
        $this->assertTrue($promotion->fresh()->is_active);
        $this->put(route('admin.discounts.update', $promotion), ['is_active' => false])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($promotion->fresh()->is_active);
    }

    public function test_promotion_admin_and_quote_queries_are_bounded(): void
    {
        $this->seedRbac();
        $admin = $this->userWithRole('admin');
        $cinema = Cinema::factory()->create();
        $this->promotion('QUERY10', [
            'type' => Promotion::TYPE_PERCENTAGE, 'discount_amount_vnd' => null, 'discount_percent' => 10,
        ]);
        $counts = [
            'admin_index' => $this->countQueries(fn () => $this->actingAs($admin)->get(route('admin.discounts.index'))->assertOk()),
            'quote' => $this->countQueries(fn () => app(PromotionService::class)->quote(100_000, 'QUERY10', null, $cinema->id)),
        ];
        foreach ($counts as $operation => $count) {
            $this->assertLessThanOrEqual(30, $count, "{$operation} query budget exceeded: ".json_encode($counts));
        }
        if (getenv('REPORT_QUERY_COUNTS') === '1') {
            fwrite(STDOUT, 'PROMOTION_QUERY_COUNTS='.json_encode($counts, JSON_THROW_ON_ERROR).PHP_EOL);
        }
    }

    public function test_checkout_and_terminal_transition_queries_are_bounded(): void
    {
        $withScenario = $this->bookingScenario(false);
        $promotion = $this->promotion('CHECKOUT-QUERY');
        $withPromotion = $this->countQueries(fn () => app(BookingCheckoutService::class)->createPendingBooking(
            $withScenario['showtime']->id,
            [$withScenario['seats'][0]->id],
            null,
            'promotion-query@example.test',
            app(BookingTokenService::class)->issueCheckoutToken(),
            promotionCode: $promotion->code,
        ));

        $withoutScenario = $this->bookingScenario(false);
        $withoutPromotion = $this->countQueries(fn () => app(BookingCheckoutService::class)->createPendingBooking(
            $withoutScenario['showtime']->id,
            [$withoutScenario['seats'][0]->id],
            null,
            'no-promotion-query@example.test',
            app(BookingTokenService::class)->issueCheckoutToken(),
        ));
        $booking = Booking::query()->whereHas('promotionUsage')->sole();
        $booking->forceFill(['expires_at' => now()->subMinute()])->save();
        $expiry = $this->countQueries(fn () => app(BookingExpirationService::class)->expire($booking->id));

        $redeemScenario = $this->bookingScenario(false);
        $redeemBooking = $this->bookingForScenario($redeemScenario);
        DB::transaction(fn () => app(PromotionService::class)->reserveForBooking($redeemBooking, $promotion->code, 100_000));
        $redeem = $this->countQueries(fn () => app(PromotionService::class)->redeem($redeemBooking));

        $this->assertLessThanOrEqual($withoutPromotion + 8, $withPromotion);
        $this->assertLessThanOrEqual(15, $expiry);
        $this->assertLessThanOrEqual(2, $redeem);
        if (getenv('REPORT_QUERY_COUNTS') === '1') {
            fwrite(STDOUT, 'PROMOTION_CHECKOUT_QUERY_COUNTS='.json_encode([
                'with_promotion' => $withPromotion,
                'without_promotion' => $withoutPromotion,
                'expiry_release' => $expiry,
                'redeem' => $redeem,
            ], JSON_THROW_ON_ERROR).PHP_EOL);
        }
    }

    private function promotion(string $code, array $overrides = []): Promotion
    {
        return Promotion::query()->create([
            'code' => $code, 'name' => 'Promotion '.trim($code), 'type' => Promotion::TYPE_FIXED,
            'discount_amount_vnd' => 20_000, 'discount_percent' => null, 'maximum_discount_vnd' => null,
            'minimum_order_vnd' => 0, 'is_active' => true, 'registered_users_only' => false,
            'first_order_only' => false, ...$overrides,
        ]);
    }

    private function assertQuotaRejected(Booking $booking, Promotion $promotion): void
    {
        try {
            DB::transaction(fn () => app(PromotionService::class)->reserveForBooking($booking, $promotion->code, 100_000));
            $this->fail('Expected quota rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('promotion_code', $exception->errors());
        }
    }

    private function countQueries(callable $operation): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $operation();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }
}
