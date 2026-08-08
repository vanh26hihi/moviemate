<?php

namespace Tests\Feature\Bookings;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\FoodItem;
use App\Models\Order;
use App\Models\Payment;
use App\Services\BookingCheckoutService;
use App\Services\BookingTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

class BookingCancellationTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_cancel_route_is_delete_only_and_protected_by_auth_active_and_throttle(): void
    {
        $route = Route::getRoutes()->getByName('user.bookings.cancel');

        $this->assertNotNull($route);
        $this->assertSame(['DELETE'], $route->methods());
        $this->assertSame('bookings/{booking}', $route->uri());
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('active', $route->gatherMiddleware());
        $this->assertContains('throttle:10,1', $route->gatherMiddleware());
    }

    public function test_guest_is_redirected_and_other_customers_staff_and_managers_are_forbidden(): void
    {
        $owner = $this->userWithRole('user');
        $booking = $this->pendingBooking($owner->id);

        $this->delete(route('user.bookings.cancel', $booking))
            ->assertRedirect(route('login'));

        foreach (['user', 'staff', 'manager'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->delete(route('user.bookings.cancel', $booking))
                ->assertForbidden();
        }

        $this->assertSame('pending_payment', $booking->fresh()->booking_status);
    }

    public function test_owner_cancellation_releases_seats_cancels_food_and_preserves_history(): void
    {
        $owner = $this->userWithRole('user');
        $scenario = $this->bookingScenario(false);
        $food = FoodItem::query()->create([
            'name' => 'Combo cancellation history',
            'price' => 60_000,
            'active' => true,
        ]);
        $booking = app(BookingCheckoutService::class)->createPendingBooking(
            $scenario['showtime']->id,
            [$scenario['seats'][0]->id],
            $owner->id,
            $owner->email,
            app(BookingTokenService::class)->issueCheckoutToken(),
            [['food_id' => $food->id, 'quantity' => 1]],
        )->booking;
        $order = Order::query()->where('booking_id', $booking->id)->sole();
        $orderItem = $order->items()->sole();
        $bookingSeat = $booking->bookingSeats()->sole();

        $this->actingAs($owner)
            ->delete(route('user.bookings.cancel', $booking))
            ->assertRedirect(route('user.bookings.history'))
            ->assertSessionHas('success', 'Đơn đặt vé đã được hủy.');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'booking_status' => 'cancelled',
            'payment_status' => 'unpaid',
        ]);
        $this->assertDatabaseHas('booking_seats', [
            'id' => $bookingSeat->id,
            'booking_id' => $booking->id,
            'active_lock_key' => null,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'booking_id' => $booking->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('order_items', [
            'id' => $orderItem->id,
            'order_id' => $order->id,
        ]);
        $this->assertSame(1, ActivityLog::query()->where('action', 'booking.cancelled')->count());
        $this->assertSame(
            ['A1'],
            ActivityLog::query()->where('action', 'booking.cancelled')->sole()->context['seat_units'],
        );

        $replacement = $this->reserve($scenario, [$scenario['seats'][0]->id], $owner->id)->booking;
        $this->assertNotSame($booking->id, $replacement->id);
        $this->assertSame(BookingSeat::ACTIVE_LOCK_KEY, $replacement->bookingSeats()->sole()->active_lock_key);
    }

    public function test_duplicate_cancellation_is_idempotent(): void
    {
        $owner = $this->userWithRole('user');
        $booking = $this->pendingBooking($owner->id);

        $this->actingAs($owner)->delete(route('user.bookings.cancel', $booking))
            ->assertSessionHas('success', 'Đơn đặt vé đã được hủy.');
        $this->actingAs($owner)->delete(route('user.bookings.cancel', $booking))
            ->assertSessionHas('warning', 'Đơn đặt vé đã được hủy trước đó.');

        $this->assertSame('cancelled', $booking->fresh()->booking_status);
        $this->assertNull($booking->bookingSeats()->sole()->active_lock_key);
        $this->assertSame(1, ActivityLog::query()->where('action', 'booking.cancelled')->count());
    }

    public function test_failed_and_expired_payment_attempts_are_terminal_and_allow_cancellation(): void
    {
        $owner = $this->userWithRole('user');

        foreach ([Payment::STATUS_FAILED, Payment::STATUS_EXPIRED] as $status) {
            $booking = $this->pendingBooking($owner->id);
            $this->paymentFor($booking->id, $status);

            $this->actingAs($owner)
                ->delete(route('user.bookings.cancel', $booking))
                ->assertSessionHas('success', 'Đơn đặt vé đã được hủy.');

            $this->assertSame('cancelled', $booking->fresh()->booking_status);
            $this->assertNull($booking->bookingSeats()->sole()->active_lock_key);
        }
    }

    public function test_non_terminal_or_successful_payment_attempts_refuse_cancellation(): void
    {
        $owner = $this->userWithRole('user');
        $unsafeStatuses = [
            Payment::STATUS_PENDING,
            Payment::STATUS_PROCESSING,
            Payment::STATUS_UNRESOLVED,
            Payment::STATUS_REVIEW,
            Payment::STATUS_SUCCESS,
        ];

        foreach ($unsafeStatuses as $status) {
            $booking = $this->pendingBooking($owner->id);
            $this->paymentFor($booking->id, $status);

            $this->actingAs($owner)
                ->delete(route('user.bookings.cancel', $booking))
                ->assertSessionHas('warning', 'Đơn đặt vé này không thể hủy ở trạng thái hiện tại.');

            $this->assertSame('pending_payment', $booking->fresh()->booking_status);
            $this->assertSame(
                BookingSeat::ACTIVE_LOCK_KEY,
                $booking->bookingSeats()->sole()->active_lock_key,
            );
        }
    }

    public function test_paid_used_expired_and_past_due_bookings_refuse_cancellation(): void
    {
        $owner = $this->userWithRole('user');
        $states = [
            ['booking_status' => 'paid', 'payment_status' => 'paid'],
            ['booking_status' => 'used', 'payment_status' => 'paid'],
            ['booking_status' => 'expired', 'payment_status' => 'unpaid'],
            ['booking_status' => 'pending_payment', 'payment_status' => 'unpaid', 'expires_at' => now()->subMinute()],
        ];

        foreach ($states as $state) {
            $booking = $this->pendingBooking($owner->id);
            $booking->forceFill($state)->save();

            $this->actingAs($owner)
                ->delete(route('user.bookings.cancel', $booking))
                ->assertSessionHas('warning', 'Đơn đặt vé này không thể hủy ở trạng thái hiện tại.');

            $this->assertSame($state['booking_status'], $booking->fresh()->booking_status);
            $this->assertSame(
                BookingSeat::ACTIVE_LOCK_KEY,
                $booking->bookingSeats()->sole()->active_lock_key,
            );
        }
    }

    public function test_history_renders_server_driven_actions_and_canonical_pending_filter(): void
    {
        $owner = $this->userWithRole('user');
        $pending = $this->pendingBooking($owner->id);
        $paid = $this->pendingBooking($owner->id);
        $paid->forceFill(['booking_status' => 'paid', 'payment_status' => 'paid'])->save();
        $this->paymentFor($paid->id, Payment::STATUS_SUCCESS);

        $this->actingAs($owner)->get(route('user.bookings.history'))
            ->assertOk()
            ->assertSee($pending->booking_code)
            ->assertSee($paid->booking_code)
            ->assertSee('action="'.route('user.bookings.cancel', $pending).'"', false)
            ->assertDontSee('action="'.route('user.bookings.cancel', $paid).'"', false)
            ->assertSee(route('user.bookings.pending', $pending), false)
            ->assertSee(route('user.bookings.ticket', $paid), false)
            ->assertSee(route('user.bookings.ticket-email.resend', $paid), false);

        $this->actingAs($owner)->get(route('user.bookings.history', ['status' => 'pending']))
            ->assertOk()
            ->assertSee($pending->booking_code)
            ->assertDontSee($paid->booking_code);
    }

    private function paymentFor(int $bookingId, string $status): Payment
    {
        return Payment::createForProvider('vnpay', [
            'booking_id' => $bookingId,
            'payment_method' => 'vnpay',
            'order_code' => 'CANCEL-'.str()->upper(str()->random(20)),
            'amount' => 50_000,
            'currency' => 'VND',
            'status' => $status,
            'expires_at' => now()->addMinutes(10),
            'reconcile_until' => now()->addDay(),
            ...($status === Payment::STATUS_SUCCESS ? [
                'verified_at' => now(),
                'paid_at' => now(),
                'transaction_id' => (string) random_int(100000, 999999),
            ] : []),
        ]);
    }

    private function pendingBooking(int $userId): Booking
    {
        $scenario = $this->bookingScenario(false);

        return $this->reserve($scenario, [$scenario['seats'][0]->id], $userId)->booking;
    }
}
