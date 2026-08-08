<?php

namespace Tests\Feature\Loyalty;

use App\Models\ActivityLog;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltySetting;
use App\Models\Review;
use App\Models\User;
use App\Services\BookingCheckoutService;
use App\Services\BookingExpirationService;
use App\Services\BookingTokenService;
use App\Services\LoyaltyService;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

final class VerifiedReviewAndPointsTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    public function test_used_paid_booking_after_movie_end_can_publish_once_and_earn_once(): void
    {
        LoyaltySetting::current()->update(['review_reward_points' => 125]);
        $user = User::factory()->create();
        $scenario = $this->bookingScenario(false);
        $scenario['showtime']->update(['show_date' => now()->subDay()->toDateString(), 'show_time' => '10:00:00']);
        $booking = $this->bookingForScenario($scenario, ['user_id' => $user->id, 'payment_status' => 'paid', 'booking_status' => 'used', 'used_at' => now()->subHours(5)]);

        $review = app(ReviewService::class)->save($user, $scenario['movie']->id, 9, 'Một bộ phim đáng xem.', $booking->id);
        $this->assertSame(Review::MODERATION_PUBLISHED, $review->moderation_status);
        $this->assertTrue($review->is_verified);
        $this->assertSame(125, LoyaltyAccount::query()->where('user_id', $user->id)->value('points_balance'));

        app(ReviewService::class)->save($user, $scenario['movie']->id, 8, 'Cập nhật cảm nhận.', $booking->id);
        $this->assertSame(1, Review::query()->where('user_id', $user->id)->where('movie_id', $scenario['movie']->id)->count());
        $this->assertSame(125, LoyaltyAccount::query()->where('user_id', $user->id)->value('points_balance'));
    }

    public function test_unverified_or_not_finished_booking_is_rejected(): void
    {
        $user = User::factory()->create();
        $scenario = $this->bookingScenario(false);
        $booking = $this->bookingForScenario($scenario, ['user_id' => $user->id, 'payment_status' => 'paid', 'booking_status' => 'paid']);
        $this->expectException(ValidationException::class);
        app(ReviewService::class)->save($user, $scenario['movie']->id, 10, 'Chưa đủ điều kiện.', $booking->id);
    }

    public function test_flagged_review_waits_for_moderation_before_reward(): void
    {
        $user = User::factory()->create();
        $scenario = $this->bookingScenario(false);
        $scenario['showtime']->update(['show_date' => now()->subDay()->toDateString(), 'show_time' => '10:00:00']);
        $booking = $this->bookingForScenario($scenario, ['user_id' => $user->id, 'payment_status' => 'paid', 'booking_status' => 'used', 'used_at' => now()->subHours(5)]);
        $review = app(ReviewService::class)->save($user, $scenario['movie']->id, 7, 'Xem tại https://spam.example', $booking->id);
        $this->assertSame(Review::MODERATION_PENDING, $review->moderation_status);
        $this->assertNull($review->reward_awarded_at);
        $this->assertDatabaseMissing('loyalty_accounts', ['user_id' => $user->id]);
    }

    public function test_points_quote_obeys_balance_value_and_percentage_cap(): void
    {
        $user = User::factory()->create();
        LoyaltyAccount::query()->create(['user_id' => $user->id, 'points_balance' => 1000, 'lifetime_earned' => 1000]);
        LoyaltySetting::current()->update(['point_value_vnd' => 100, 'max_points_discount_percent' => 40, 'minimum_points_redemption' => 10]);
        $quote = app(LoyaltyService::class)->quote($user->id, 100000, 1000);
        $this->assertSame(400, $quote->pointsUsed);
        $this->assertSame(40000, $quote->discountAmount);
        $this->assertSame(60000, $quote->finalAmount);
    }

    public function test_checkout_reserves_points_and_expiry_releases_them_once(): void
    {
        $user = User::factory()->create();
        LoyaltyAccount::query()->create(['user_id' => $user->id, 'points_balance' => 1000, 'lifetime_earned' => 1000]);
        LoyaltySetting::current()->update(['point_value_vnd' => 100, 'max_points_discount_percent' => 50, 'minimum_points_redemption' => 1]);
        $scenario = $this->bookingScenario(false);
        $result = app(BookingCheckoutService::class)->createPendingBooking(
            $scenario['showtime']->id, [$scenario['seats'][0]->id], $user->id, $user->email,
            app(BookingTokenService::class)->issueCheckoutToken(), pointsToUse: 100,
        );
        $this->assertSame(900, LoyaltyAccount::query()->where('user_id', $user->id)->value('points_balance'));
        $this->assertSame(10000, (int) $result->booking->points_discount_amount);

        $result->booking->update(['expires_at' => now()->subMinute()]);
        $this->assertTrue(app(BookingExpirationService::class)->expire($result->booking->id));
        $this->assertSame(1000, LoyaltyAccount::query()->where('user_id', $user->id)->value('points_balance'));
        $this->assertFalse(app(BookingExpirationService::class)->expire($result->booking->id));
        $this->assertSame(1, $result->booking->pointRedemption()->where('status', 'released')->count());
        $this->assertSame('expired', $result->booking->pointRedemption()->value('release_reason'));
    }

    public function test_every_review_eligibility_boundary_is_server_authoritative(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $scenario = $this->bookingScenario(false);
        $booking = $this->bookingForScenario($scenario, ['user_id' => $owner->id, 'payment_status' => 'unpaid', 'booking_status' => 'used']);
        $service = app(ReviewService::class);

        foreach ([
            fn () => $service->save($owner, $scenario['movie']->id, 8, 'Chưa thanh toán.', $booking->id),
            function () use ($booking, $owner, $scenario, $service): void {
                $booking->update(['payment_status' => 'paid', 'booking_status' => 'paid']);
                $service->save($owner, $scenario['movie']->id, 8, 'Chưa check-in.', $booking->id);
            },
            function () use ($booking, $owner, $scenario, $service): void {
                $booking->update(['booking_status' => 'used', 'used_at' => now()]);
                $service->save($owner, $scenario['movie']->id, 8, 'Phim chưa kết thúc.', $booking->id);
            },
            function () use ($booking, $other, $scenario, $service): void {
                $scenario['showtime']->update(['show_date' => now()->subDay()->toDateString(), 'show_time' => '10:00:00']);
                $service->save($other, $scenario['movie']->id, 8, 'Không phải vé của tôi.', $booking->id);
            },
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Review eligibility boundary was not enforced.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_domain_url_is_triaged_html_is_rejected_and_hidden_review_is_excluded_from_aggregate(): void
    {
        $user = User::factory()->create();
        $moderator = User::factory()->create();
        $scenario = $this->bookingScenario(false);
        $scenario['showtime']->update(['show_date' => now()->subDay()->toDateString(), 'show_time' => '10:00:00']);
        $booking = $this->bookingForScenario($scenario, ['user_id' => $user->id, 'payment_status' => 'paid', 'booking_status' => 'used', 'used_at' => now()->subHours(5)]);
        $service = app(ReviewService::class);

        $review = $service->save($user, $scenario['movie']->id, 9, 'Nội dung sạch và hữu ích.', $booking->id);
        $movie = $scenario['movie']->fresh()->loadAvg('reviews', 'rating')->loadCount('reviews');
        $this->assertSame(1, $movie->reviews_count);

        $service->moderate($review, Review::MODERATION_HIDDEN, 'Nội dung cần ẩn', $moderator->id);
        $movie = $scenario['movie']->fresh()->loadAvg('reviews', 'rating')->loadCount('reviews');
        $this->assertSame(0, $movie->reviews_count);
        $this->assertDatabaseHas('activity_logs', ['action' => 'review.moderated', 'subject_id' => (string) $review->id]);

        $pending = $service->save($user, $scenario['movie']->id, 7, 'truy cập spam-example.com ngay', $booking->id);
        $this->assertSame(Review::MODERATION_PENDING, $pending->moderation_status);
        $this->assertContains('url', $pending->moderation_flags);

        $this->expectException(ValidationException::class);
        $service->save($user, $scenario['movie']->id, 8, '<b>HTML không hợp lệ</b>', $booking->id);
    }

    public function test_admin_approval_rewards_once_and_records_each_moderation_transition(): void
    {
        LoyaltySetting::current()->update(['review_reward_points' => 25]);
        $user = User::factory()->create();
        $moderator = User::factory()->create();
        $scenario = $this->bookingScenario(false);
        $scenario['showtime']->update(['show_date' => now()->subDay()->toDateString(), 'show_time' => '10:00:00']);
        $booking = $this->bookingForScenario($scenario, ['user_id' => $user->id, 'payment_status' => 'paid', 'booking_status' => 'used', 'used_at' => now()->subHours(5)]);
        $review = app(ReviewService::class)->save($user, $scenario['movie']->id, 7, 'www.example.com', $booking->id);

        app(ReviewService::class)->moderate($review, Review::MODERATION_PUBLISHED, null, $moderator->id);
        app(ReviewService::class)->moderate($review->fresh(), Review::MODERATION_PUBLISHED, null, $moderator->id);

        $this->assertSame(25, LoyaltyAccount::query()->where('user_id', $user->id)->value('points_balance'));
        $this->assertSame(1, $review->fresh()->reward_awarded_at ? 1 : 0);
        $this->assertSame(2, ActivityLog::query()->where('action', 'review.moderated')->where('subject_id', (string) $review->id)->count());
    }

    public function test_point_reservation_prevents_double_spend_and_keeps_conversion_snapshot(): void
    {
        $user = User::factory()->create();
        LoyaltyAccount::query()->create(['user_id' => $user->id, 'points_balance' => 100, 'lifetime_earned' => 100]);
        LoyaltySetting::current()->update(['point_value_vnd' => 100, 'max_points_discount_percent' => 100, 'minimum_points_redemption' => 1]);
        $scenario = $this->bookingScenario();
        $service = app(BookingCheckoutService::class);
        $first = $service->createPendingBooking($scenario['showtime']->id, [$scenario['seats'][0]->id], $user->id, $user->email, app(BookingTokenService::class)->issueCheckoutToken(), pointsToUse: 80);

        $redemption = $first->booking->pointRedemption()->firstOrFail();
        $this->assertSame(100, $redemption->point_value_vnd_snapshot);
        $this->assertSame(8000, $redemption->discount_amount);
        LoyaltySetting::current()->update(['point_value_vnd' => 200]);
        $this->assertSame(8000, $redemption->fresh()->discount_amount);

        try {
            $service->createPendingBooking($scenario['showtime']->id, [$scenario['seats'][2]->id, $scenario['seats'][3]->id], $user->id, $user->email, app(BookingTokenService::class)->issueCheckoutToken(), pointsToUse: 80);
            $this->fail('The second checkout spent reserved points.');
        } catch (ValidationException) {
            $this->assertSame(20, LoyaltyAccount::query()->where('user_id', $user->id)->value('points_balance'));
        }

        app(LoyaltyService::class)->redeem($first->booking);
        app(LoyaltyService::class)->redeem($first->booking);
        $this->assertSame('redeemed', $redemption->fresh()->status);
        $this->assertSame(1, $redemption->account->transactions()->where('type', 'redeem')->count());
    }

    public function test_review_and_loyalty_pages_stay_within_query_budgets(): void
    {
        $this->seedRbac();
        $customer = $this->userWithRole('user');
        $admin = $this->userWithRole('admin');
        $scenario = $this->bookingScenario(false);
        $booking = $this->bookingForScenario($scenario, ['user_id' => $customer->id, 'payment_status' => 'paid', 'booking_status' => 'used']);
        Review::query()->create(['user_id' => $customer->id, 'movie_id' => $scenario['movie']->id, 'booking_id' => $booking->id, 'rating' => 9, 'comment' => 'Đánh giá đo truy vấn.', 'status' => Review::STATUS_VISIBLE, 'moderation_status' => Review::MODERATION_PUBLISHED, 'is_verified' => true]);
        LoyaltyAccount::query()->create(['user_id' => $customer->id, 'points_balance' => 10, 'lifetime_earned' => 10]);

        $counts = [
            'my_reviews' => $this->countQueries(fn () => $this->actingAs($customer)->get(route('user.reviews.index'))->assertOk()),
            'loyalty_history' => $this->countQueries(fn () => $this->actingAs($customer)->get(route('user.loyalty.history'))->assertOk()),
            'admin_reviews' => $this->countQueries(fn () => $this->actingAs($admin)->get(route('admin.reviews.index'))->assertOk()),
            'admin_loyalty' => $this->countQueries(fn () => $this->actingAs($admin)->get(route('admin.loyalty.index'))->assertOk()),
        ];

        foreach ($counts as $page => $count) {
            $this->assertLessThanOrEqual(30, $count, "{$page} query budget exceeded: ".json_encode($counts));
        }
        if (getenv('REPORT_QUERY_COUNTS') === '1') {
            fwrite(STDOUT, 'LOYALTY_QUERY_COUNTS='.json_encode($counts, JSON_THROW_ON_ERROR).PHP_EOL);
        }
    }

    private function countQueries(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $request();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }
}
