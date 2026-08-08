<?php

namespace Tests\Unit\Services;

use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Payment;
use App\Services\BookingExpirationService;
use App\Services\BookingFoodService;
use App\Services\BookingSeatLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

class BookingExpirationServiceTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    public function test_overdue_pending_booking_becomes_expired(): void
    {
        [$booking] = $this->overdueBooking();

        $this->assertTrue(app(BookingExpirationService::class)->expire($booking->id));
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'booking_status' => 'expired']);
    }

    public function test_pending_booking_before_deadline_is_unchanged(): void
    {
        $scenario = $this->bookingScenario();
        $booking = $this->reserve($scenario, [$scenario['seats'][0]->id])->booking;

        $this->assertFalse(app(BookingExpirationService::class)->expire($booking->id));
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'booking_status' => 'pending_payment']);
    }

    public function test_paid_booking_is_never_expired(): void
    {
        [$booking] = $this->overdueBooking();
        $booking->update(['booking_status' => 'paid', 'payment_status' => 'paid']);

        $this->assertFalse(app(BookingExpirationService::class)->expire($booking->id));
        $this->assertDatabaseHas('booking_seats', [
            'booking_id' => $booking->id,
            'active_lock_key' => BookingSeat::ACTIVE_LOCK_KEY,
        ]);
    }

    public function test_used_booking_is_never_expired(): void
    {
        [$booking] = $this->overdueBooking();
        $booking->update(['booking_status' => 'used']);

        $this->assertFalse(app(BookingExpirationService::class)->expire($booking->id));
        $this->assertSame('used', $booking->fresh()->booking_status);
    }

    public function test_cancelled_booking_is_not_expired_again(): void
    {
        [$booking] = $this->overdueBooking();
        $booking->update(['booking_status' => 'cancelled']);
        app(BookingSeatLockService::class)->release($booking);

        $this->assertFalse(app(BookingExpirationService::class)->expire($booking->id));
        $this->assertSame('cancelled', $booking->fresh()->booking_status);
    }

    public function test_already_expired_booking_is_idempotently_skipped(): void
    {
        [$booking] = $this->overdueBooking();
        $service = app(BookingExpirationService::class);

        $this->assertTrue($service->expire($booking->id));
        $this->assertFalse($service->expire($booking->id));
        $this->assertSame('expired', $booking->fresh()->booking_status);
    }

    public function test_expiration_releases_lock_but_preserves_seat_history_and_price(): void
    {
        [$booking] = $this->overdueBooking();
        $lockId = $booking->bookingSeats()->value('id');

        app(BookingExpirationService::class)->expire($booking->id);

        $this->assertDatabaseHas('booking_seats', [
            'id' => $lockId,
            'booking_id' => $booking->id,
            'active_lock_key' => null,
            'price' => 50000,
        ]);
    }

    public function test_released_seat_can_be_locked_by_a_new_booking(): void
    {
        [$booking, $scenario] = $this->overdueBooking();
        app(BookingExpirationService::class)->expire($booking->id);

        $replacement = $this->reserve($scenario, [$scenario['seats'][0]->id])->booking;

        $this->assertNotSame($booking->id, $replacement->id);
        $this->assertDatabaseHas('booking_seats', [
            'booking_id' => $replacement->id,
            'active_lock_key' => BookingSeat::ACTIVE_LOCK_KEY,
        ]);
    }

    public function test_command_is_idempotent_when_run_twice(): void
    {
        $this->overdueBooking();

        $this->artisan('bookings:expire-pending', ['--batch' => 1])
            ->expectsOutputToContain('Expired')
            ->assertSuccessful();
        $this->artisan('bookings:expire-pending', ['--batch' => 1])
            ->expectsOutputToContain('Checked')
            ->assertSuccessful();

        $this->assertDatabaseCount('booking_seats', 1);
    }

    public function test_command_processes_candidates_in_configured_batches(): void
    {
        $bookingIds = collect(range(1, 5))->map(function () {
            [$booking] = $this->overdueBooking();

            return $booking->id;
        });

        $this->artisan('bookings:expire-pending', ['--batch' => 2])->assertSuccessful();

        $this->assertSame(5, $bookingIds->count());
        $this->assertDatabaseCount('bookings', 5);
        $this->assertSame(5, Booking::query()->where('booking_status', 'expired')->count());
    }

    public function test_exception_rolls_back_status_and_lock_for_that_booking(): void
    {
        [$booking] = $this->overdueBooking();
        $seatLocks = Mockery::mock(BookingSeatLockService::class);
        $seatLocks->shouldReceive('release')->once()->andThrow(new RuntimeException('release failed'));
        $service = new BookingExpirationService($seatLocks, app(BookingFoodService::class));

        try {
            $service->expire($booking->id);
            $this->fail('Expected lock release failure.');
        } catch (RuntimeException) {
            $this->assertDatabaseHas('bookings', [
                'id' => $booking->id,
                'booking_status' => 'pending_payment',
            ]);
            $this->assertDatabaseHas('booking_seats', [
                'booking_id' => $booking->id,
                'active_lock_key' => BookingSeat::ACTIVE_LOCK_KEY,
            ]);
        }
    }

    public function test_command_continues_after_one_booking_errors(): void
    {
        [$first] = $this->overdueBooking();
        [$second] = $this->overdueBooking();
        $real = app(BookingExpirationService::class);
        $service = Mockery::mock(BookingExpirationService::class);
        $service->shouldReceive('expire')->with($first->id)->once()->andThrow(new RuntimeException('one failure'));
        $service->shouldReceive('expire')->with($second->id)->once()->andReturnUsing(
            fn () => $real->expire($second->id)
        );
        $this->app->instance(BookingExpirationService::class, $service);

        $this->artisan('bookings:expire-pending', ['--batch' => 1])->assertFailed();

        $this->assertSame('pending_payment', $first->fresh()->booking_status);
        $this->assertSame('expired', $second->fresh()->booking_status);
    }

    public function test_couple_locks_are_released_together(): void
    {
        $scenario = $this->bookingScenario();
        $pairIds = $scenario['seats']->where('type', 'couple')->pluck('id')->all();
        $booking = $this->reserve($scenario, $pairIds)->booking;
        $booking->update(['expires_at' => now()->subMinute()]);

        app(BookingExpirationService::class)->expire($booking->id);

        $this->assertSame(2, BookingSeat::query()
            ->where('booking_id', $booking->id)
            ->whereNull('active_lock_key')
            ->count());
        $this->assertSame(0, BookingSeat::query()
            ->where('booking_id', $booking->id)
            ->where('active_lock_key', BookingSeat::ACTIVE_LOCK_KEY)
            ->count());
    }

    public function test_provider_uncertainty_states_retain_the_booking_and_seats(): void
    {
        foreach ([Payment::STATUS_PROCESSING, Payment::STATUS_UNRESOLVED, Payment::STATUS_REVIEW] as $status) {
            [$booking] = $this->overdueBooking();
            $this->paymentFor($booking, $status);

            $this->assertFalse(app(BookingExpirationService::class)->expire($booking->id));
            $this->assertSame('pending_payment', $booking->fresh()->booking_status);
            $this->assertSame(
                BookingSeat::ACTIVE_LOCK_KEY,
                $booking->bookingSeats()->sole()->active_lock_key,
            );
        }
    }

    public function test_authoritative_payment_success_wins_expiration_race(): void
    {
        [$booking] = $this->overdueBooking();
        $this->paymentFor($booking, Payment::STATUS_SUCCESS, [
            'verified_at' => now(),
            'paid_at' => now(),
        ]);

        $this->assertFalse(app(BookingExpirationService::class)->expire($booking->id));
        $this->assertSame('pending_payment', $booking->fresh()->booking_status);
        $this->assertNotNull($booking->bookingSeats()->sole()->active_lock_key);
    }

    public function test_scheduler_expires_ordinary_pending_attempt_but_retains_unresolved_attempt(): void
    {
        [$eligible] = $this->overdueBooking();
        $this->paymentFor($eligible, Payment::STATUS_PENDING);
        [$retained] = $this->overdueBooking();
        $this->paymentFor($retained, Payment::STATUS_UNRESOLVED);

        $this->artisan('bookings:expire-pending')->assertSuccessful();
        $this->artisan('bookings:expire-pending')->assertSuccessful();

        $this->assertSame('expired', $eligible->fresh()->booking_status);
        $this->assertNull($eligible->bookingSeats()->sole()->active_lock_key);
        $this->assertSame('pending_payment', $retained->fresh()->booking_status);
        $this->assertNotNull($retained->bookingSeats()->sole()->active_lock_key);
    }

    private function overdueBooking(): array
    {
        $scenario = $this->bookingScenario();
        $booking = $this->reserve($scenario, [$scenario['seats'][0]->id])->booking;
        $booking->update(['expires_at' => now()->subMinute()]);

        return [$booking, $scenario];
    }

    private function paymentFor(Booking $booking, string $status, array $overrides = []): Payment
    {
        return Payment::createForProvider('vnpay', [
            'booking_id' => $booking->id,
            'payment_method' => 'vnpay',
            'order_code' => 'EXPIRY-'.str()->upper(str()->random(16)),
            'amount' => (int) $booking->total_amount,
            'currency' => 'VND',
            'status' => $status,
            'expires_at' => now()->subMinute(),
            'reconcile_until' => now()->addDay(),
            ...$overrides,
        ]);
    }
}
