<?php

namespace Tests\Feature\Checkout;

use App\Http\Controllers\User\BookingController;
use App\Http\Controllers\User\RetiredBookingStoreController;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Payments\PaymentTestCase;

class LegacyBookingStoreRetirementTest extends PaymentTestCase
{
    public function test_guest_and_authenticated_legacy_posts_return_gone_without_writes(): void
    {
        $payload = $this->forgedLegacyPayload();

        $this->post('/booking/store', $payload)->assertGone();

        $this->seedRbac();
        $this->actingAs($this->userWithRole('user'))
            ->post(route('user.bookings.store'), $payload)
            ->assertGone();

        $this->assertNoLegacyWrites();
    }

    public function test_csrf_valid_forged_payload_and_replay_are_always_gone_without_writes(): void
    {
        $csrfToken = 'valid-test-csrf-token';
        $payload = ['_token' => $csrfToken, ...$this->forgedLegacyPayload()];

        $this->withSession(['_token' => $csrfToken])->post('/booking/store', $payload)->assertGone();
        $this->withSession(['_token' => $csrfToken])->post('/booking/store', $payload)->assertGone();

        $this->assertNoLegacyWrites();
    }

    public function test_only_the_retired_responder_handles_the_legacy_uri_and_old_store_method_is_removed(): void
    {
        $legacyRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => $route->uri() === 'booking/store' && in_array('POST', $route->methods(), true));

        $this->assertCount(1, $legacyRoutes);
        $this->assertSame(RetiredBookingStoreController::class, $legacyRoutes->sole()->getActionName());
        $this->assertFalse(method_exists(BookingController::class, 'store'));
    }

    public function test_unified_checkout_and_history_ticket_staff_routes_remain_available(): void
    {
        foreach ([
            'user.bookings.checkout',
            'user.bookings.food',
            'user.bookings.food.store',
            'user.bookings.review',
            'user.bookings.confirm',
            'user.bookings.history',
            'user.bookings.ticket',
            'staff.tickets.index',
            'staff.tickets.check',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Missing route {$routeName}");
        }
    }

    private function forgedLegacyPayload(): array
    {
        return [
            'showtime_id' => 1,
            'seat_ids' => [1, 2],
            'customer_email' => 'attacker@example.test',
            'checkout_token' => 'checkout-00000000000000000000000000000000',
            'total_amount' => 1,
            'seat_subtotal' => 1,
            'food_subtotal' => 1,
            'payment_status' => 'paid',
            'food_items' => [['food_id' => 1, 'quantity' => 99, 'price' => 1]],
        ];
    }

    private function assertNoLegacyWrites(): void
    {
        foreach ([
            'bookings',
            'booking_seats',
            'payments',
            'orders',
            'order_items',
            'booking_ticket_deliveries',
        ] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }
}
