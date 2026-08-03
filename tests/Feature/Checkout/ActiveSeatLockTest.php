<?php

namespace Tests\Feature\Checkout;

use App\Models\BookingSeat;
use App\Models\Showtime;
use App\Services\BookingCheckoutService;
use App\Services\BookingSeatLockService;
use App\Services\BookingTokenService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

class ActiveSeatLockTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    public function test_pending_booking_seat_records_showtime_and_active_lock_key(): void
    {
        $scenario = $this->bookingScenario();
        $result = $this->reserve($scenario, [$scenario['seats'][0]->id]);
        $lock = $result->booking->bookingSeats()->firstOrFail();

        $this->assertSame($scenario['showtime']->id, $lock->showtime_id);
        $this->assertSame(BookingSeat::ACTIVE_LOCK_KEY, $lock->active_lock_key);
    }

    public function test_database_constraint_rejects_a_second_active_lock_for_same_showtime_and_seat(): void
    {
        $scenario = $this->bookingScenario();
        $first = $this->reserve($scenario, [$scenario['seats'][0]->id])->booking;
        $second = $this->bookingForScenario($scenario);

        $this->expectException(QueryException::class);
        BookingSeat::query()->create([
            'booking_id' => $second->id,
            'showtime_id' => $scenario['showtime']->id,
            'seat_id' => $scenario['seats'][0]->id,
            'active_lock_key' => BookingSeat::ACTIVE_LOCK_KEY,
            'price' => $first->bookingSeats()->first()->price,
        ]);
    }

    public function test_checkout_reports_conflict_when_the_same_seat_is_already_locked(): void
    {
        $scenario = $this->bookingScenario();
        $this->reserve($scenario, [$scenario['seats'][0]->id]);

        $this->expectException(ValidationException::class);
        $this->reserve($scenario, [$scenario['seats'][0]->id]);
    }

    public function test_same_physical_seat_can_be_locked_for_another_showtime(): void
    {
        $scenario = $this->bookingScenario();
        $secondShowtime = Showtime::query()->create([
            'movie_id' => $scenario['movie']->id,
            'cinema_id' => $scenario['cinema']->id,
            'room_id' => $scenario['room']->id,
            'room_layout_id' => $scenario['layout']->id,
            'show_date' => now()->addDays(6)->toDateString(),
            'show_time' => '20:00:00',
            'price' => 50000,
            'status' => 'active',
        ]);
        $this->reserve($scenario, [$scenario['seats'][0]->id]);
        $scenario['showtime'] = $secondShowtime;
        $this->reserve($scenario, [$scenario['seats'][0]->id]);

        $this->assertSame(2, BookingSeat::query()->where('seat_id', $scenario['seats'][0]->id)->count());
    }

    public function test_partial_insert_is_rolled_back_when_any_seat_constraint_fails(): void
    {
        $scenario = $this->bookingScenario();
        $existing = $this->reserve($scenario, [$scenario['seats'][0]->id])->booking;
        $newBooking = $this->bookingForScenario($scenario);
        $seats = collect([$scenario['seats'][2], $scenario['seats'][0]]);

        try {
            app(BookingSeatLockService::class)->acquire($newBooking, $seats, [
                $scenario['seats'][2]->id => 100000,
                $scenario['seats'][0]->id => 50000,
            ]);
            $this->fail('Expected the active inventory unique constraint to reject the batch.');
        } catch (QueryException) {
            $this->assertDatabaseCount('booking_seats', $existing->bookingSeats()->count());
            $this->assertDatabaseMissing('booking_seats', ['booking_id' => $newBooking->id]);
        }
    }

    public function test_complete_couple_pair_is_created_atomically(): void
    {
        $scenario = $this->bookingScenario();
        $pairIds = $scenario['seats']->where('type', 'couple')->pluck('id')->all();
        $booking = $this->reserve($scenario, $pairIds)->booking;

        $this->assertCount(2, $booking->bookingSeats);
        $this->assertTrue($booking->bookingSeats->every(
            fn ($lock) => $lock->active_lock_key === BookingSeat::ACTIVE_LOCK_KEY
        ));
    }

    public function test_half_couple_is_rejected_without_creating_an_aggregate(): void
    {
        $scenario = $this->bookingScenario();

        try {
            $this->reserve($scenario, [$scenario['seats'][2]->id]);
            $this->fail('Expected half-couple validation to fail.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('bookings', 0);
            $this->assertDatabaseCount('booking_seats', 0);
        }
    }

    public function test_maintenance_seat_is_rejected(): void
    {
        $scenario = $this->bookingScenario();

        $this->expectException(ValidationException::class);
        $this->reserve($scenario, [$scenario['seats'][1]->id]);
    }

    public function test_seat_from_another_layout_is_rejected(): void
    {
        $scenario = $this->bookingScenario();
        $foreign = $this->bookingScenario();

        $this->expectException(ValidationException::class);
        $this->reserve($scenario, [$foreign['seats'][0]->id]);
    }

    public function test_cancelled_booking_releases_lock_without_deleting_history(): void
    {
        $scenario = $this->bookingScenario();
        $booking = $this->reserve($scenario, [$scenario['seats'][0]->id])->booking;
        $booking->update(['booking_status' => 'cancelled']);

        $this->assertSame(1, app(BookingSeatLockService::class)->release($booking));
        $this->assertDatabaseHas('booking_seats', [
            'booking_id' => $booking->id,
            'active_lock_key' => null,
            'price' => 50000,
        ]);
    }

    public function test_paid_and_used_bookings_keep_their_active_locks(): void
    {
        $scenario = $this->bookingScenario();
        $booking = $this->reserve($scenario, [$scenario['seats'][0]->id])->booking;

        foreach (['paid', 'used'] as $status) {
            $booking->update(['booking_status' => $status]);
            $this->assertSame(0, app(BookingSeatLockService::class)->release($booking));
            $this->assertDatabaseHas('booking_seats', [
                'booking_id' => $booking->id,
                'active_lock_key' => BookingSeat::ACTIVE_LOCK_KEY,
            ]);
        }
    }

    public function test_same_server_issued_checkout_token_returns_one_aggregate(): void
    {
        $scenario = $this->bookingScenario();
        $token = app(BookingTokenService::class)->issueCheckoutToken();
        $first = $this->reserve($scenario, [$scenario['seats'][0]->id], null, $token);
        $second = $this->reserve($scenario, [$scenario['seats'][0]->id], null, $token);

        $this->assertSame($first->booking->id, $second->booking->id);
        $this->assertFalse($first->replayed);
        $this->assertTrue($second->replayed);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_seats', 1);
    }

    public function test_predictable_client_supplied_checkout_key_is_rejected(): void
    {
        $scenario = $this->bookingScenario();

        $this->expectException(InvalidArgumentException::class);
        app(BookingCheckoutService::class)->createPendingBooking(
            $scenario['showtime']->id,
            [$scenario['seats'][0]->id],
            null,
            'guest@example.test',
            'checkout-123',
        );
    }

    public function test_raw_checkout_token_is_not_stored(): void
    {
        $scenario = $this->bookingScenario();
        $token = app(BookingTokenService::class)->issueCheckoutToken();
        $booking = $this->reserve($scenario, [$scenario['seats'][0]->id], null, $token)->booking->refresh();

        $this->assertSame(hash('sha256', $token), $booking->getRawOriginal('checkout_idempotency_key_hash'));
        $this->assertStringNotContainsString($token, json_encode($booking->getAttributes()));
    }

    public function test_http_checkout_creates_pending_booking_without_payment_or_email(): void
    {
        Mail::fake();
        $scenario = $this->bookingScenario();
        $token = app(BookingTokenService::class)->issueCheckoutToken();

        $this->post(route('user.bookings.store'), [
            'showtime_id' => $scenario['showtime']->id,
            'seat_ids' => [$scenario['seats'][0]->id],
            'customer_email' => 'guest@example.test',
            'checkout_token' => $token,
        ])->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'booking_status' => 'pending_payment',
            'payment_status' => 'unpaid',
        ]);
        $this->assertDatabaseCount('payments', 0);
        Mail::assertNothingSent();
    }
}
