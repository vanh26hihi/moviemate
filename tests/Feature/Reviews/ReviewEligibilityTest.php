<?php

namespace Tests\Feature\Reviews;

use App\Models\Review;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

final class ReviewEligibilityTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    public function test_paid_owned_booking_after_showtime_end_allows_one_review_without_attendance(): void
    {
        $user = User::factory()->create();
        $scenario = $this->bookingScenario(false);
        $scenario['showtime']->update(['show_date' => now()->subDay()->toDateString(), 'show_time' => '10:00:00']);
        $booking = $this->bookingForScenario($scenario, [
            'user_id' => $user->id,
            'payment_status' => 'paid',
            'booking_status' => 'paid',
            'paid_at' => now()->subDay(),
        ]);

        $review = app(ReviewService::class)->save($user, $scenario['movie']->id, 9, 'Một bộ phim đáng xem.', $booking->id);

        $this->assertTrue($review->is_verified);
        $this->assertSame(Review::MODERATION_PUBLISHED, $review->moderation_status);
        $this->assertFalse(Schema::hasTable('loyalty_accounts'));
        $this->assertFalse(Schema::hasColumn('reviews', 'reward_awarded_at'));

        $this->expectException(ValidationException::class);
        app(ReviewService::class)->save($user, $scenario['movie']->id, 8, 'Đánh giá lần hai.', $booking->id);
    }

    public function test_paid_booking_before_showtime_end_is_denied(): void
    {
        [$user, $scenario, $booking] = $this->reviewScenario(['payment_status' => 'paid', 'booking_status' => 'paid']);

        $this->expectException(ValidationException::class);
        app(ReviewService::class)->save($user, $scenario['movie']->id, 8, null, $booking->id);
    }

    public function test_unpaid_booking_and_booking_owned_by_another_customer_are_denied(): void
    {
        [$owner, $scenario, $booking] = $this->reviewScenario(['payment_status' => 'unpaid', 'booking_status' => 'pending_payment'], true);
        $service = app(ReviewService::class);

        foreach ([$owner, User::factory()->create()] as $customer) {
            try {
                $service->save($customer, $scenario['movie']->id, 8, null, $booking->id);
                $this->fail('Review eligibility was not enforced.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_review_and_checkout_surfaces_have_no_loyalty_routes_or_copy(): void
    {
        $this->seedRbac();
        $customer = $this->userWithRole('user');

        $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('user.loyalty.history'));
        $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('user.bookings.loyalty'));
        $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('admin.loyalty.index'));
        $this->actingAs($customer)->get(route('user.bookings.history'))
            ->assertOk()
            ->assertDontSee('Điểm thưởng')
            ->assertDontSee('Lịch sử điểm');
    }

    /** @return array{User,array,mixed} */
    private function reviewScenario(array $bookingOverrides, bool $ended = false): array
    {
        $user = User::factory()->create();
        $scenario = $this->bookingScenario(false);
        if ($ended) {
            $scenario['showtime']->update(['show_date' => now()->subDay()->toDateString(), 'show_time' => '10:00:00']);
        }
        $booking = $this->bookingForScenario($scenario, ['user_id' => $user->id] + $bookingOverrides);

        return [$user, $scenario, $booking];
    }
}
