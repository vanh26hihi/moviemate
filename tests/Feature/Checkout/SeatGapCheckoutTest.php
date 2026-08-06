<?php

namespace Tests\Feature\Checkout;

use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\RoomLayoutCell;
use App\Models\Seat;
use App\Services\BookingCheckoutPreviewService;
use App\Services\BookingCheckoutService;
use App\Services\BookingExpirationService;
use App\Services\BookingTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

final class SeatGapCheckoutTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    public function test_server_rejects_bypassed_javascript_without_partial_aggregate(): void
    {
        $scenario = $this->threeSeatScenario();
        $token = app(BookingTokenService::class)->issueCheckoutToken();

        try {
            app(BookingCheckoutService::class)->createPendingBooking(
                $scenario['showtime']->id,
                [$scenario['seats'][0]->id, $scenario['seats'][2]->id],
                null,
                'gap@example.test',
                $token,
            );
            $this->fail('The authoritative checkout policy should reject the isolated middle seat.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('seat_ids', $exception->errors());
        }

        $this->assertSame(0, Booking::query()->count());
        $this->assertSame(0, BookingSeat::query()->count());
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_preview_excludes_only_the_current_signed_checkout_hold(): void
    {
        $scenario = $this->threeSeatScenario();
        $token = app(BookingTokenService::class)->issueCheckoutToken();
        $seatId = $scenario['seats'][0]->id;
        $booking = app(BookingCheckoutService::class)->createPendingBooking(
            $scenario['showtime']->id,
            [$seatId],
            null,
            'owner@example.test',
            $token,
        )->booking;

        $ownDraft = $this->draft($scenario['showtime']->id, [$seatId], $token, 'owner@example.test');
        $preview = app(BookingCheckoutPreviewService::class)->preview($ownDraft);
        $this->assertSame([$seatId], $preview->seats->pluck('id')->all());

        $otherDraft = $this->draft(
            $scenario['showtime']->id,
            [$seatId],
            app(BookingTokenService::class)->issueCheckoutToken(),
            'other@example.test',
        );

        $this->expectException(ValidationException::class);
        app(BookingCheckoutPreviewService::class)->preview($otherDraft);
        $this->assertSame('pending_payment', $booking->fresh()->booking_status);
    }

    public function test_authoritatively_expired_and_released_hold_becomes_available(): void
    {
        $scenario = $this->threeSeatScenario();
        $seatId = $scenario['seats'][0]->id;
        $booking = $this->reserve($scenario, [$seatId])->booking;
        $booking->forceFill(['expires_at' => now()->subSecond()])->save();

        $this->assertTrue(app(BookingExpirationService::class)->expire($booking->id));
        $this->assertNull($booking->bookingSeats()->sole()->fresh()->active_lock_key);

        $draft = $this->draft(
            $scenario['showtime']->id,
            [$seatId],
            app(BookingTokenService::class)->issueCheckoutToken(),
            'next@example.test',
        );
        $this->assertSame([$seatId], app(BookingCheckoutPreviewService::class)->preview($draft)->seats->pluck('id')->all());
    }

    public function test_forged_one_half_couple_request_is_rejected(): void
    {
        $scenario = $this->bookingScenario();
        $half = $scenario['seats']->firstWhere('type', 'couple');

        $this->expectException(ValidationException::class);
        $this->reserve($scenario, [$half->id]);
    }

    public function test_seat_gap_module_is_imported_and_form_has_submission_contract(): void
    {
        $scenario = $this->threeSeatScenario();

        $this->withoutVite();
        $this->get(route('user.bookings.selectSeat', $scenario['showtime']))
            ->assertOk()
            ->assertSee('data-seat-picker', false)
            ->assertSee('data-seat-geometry=', false)
            ->assertSee('data-seat-available=', false);

        $app = file_get_contents(resource_path('js/app.js'));
        $guard = file_get_contents(resource_path('js/seat-gap-guard.js'));
        $this->assertIsString($app);
        $this->assertIsString($guard);
        $this->assertStringContainsString("import './seat-gap-guard';", $app);
        $this->assertStringContainsString('form.addEventListener("submit"', $guard);
        $this->assertStringContainsString('event.preventDefault()', $guard);
        $this->assertStringContainsString('form.dataset.seatGapInvalid', $guard);
        $this->assertStringContainsString('window.addEventListener("pageshow"', $guard);
    }

    private function threeSeatScenario(): array
    {
        $scenario = $this->bookingScenario(false);
        $scenario['seats'][1]->forceFill(['status' => 'active'])->save();
        $third = Seat::query()->create([
            'room_id' => $scenario['room']->id,
            'row' => 'A',
            'number' => 3,
            'seat_code' => 'A3',
            'type' => 'normal',
            'status' => 'active',
            'x_position' => 3,
            'y_position' => 1,
        ]);
        RoomLayoutCell::query()->create([
            'room_layout_id' => $scenario['layout']->id,
            'x_position' => 3,
            'y_position' => 1,
            'cell_type' => 'seat',
            'seat_id' => $third->id,
        ]);
        $scenario['seats'] = $scenario['seats']->push($third)->values();

        return $scenario;
    }

    /** @return array<string, mixed> */
    private function draft(int $showtimeId, array $seatIds, string $token, string $email): array
    {
        return [
            'showtime_id' => $showtimeId,
            'seat_ids' => $seatIds,
            'food_items' => [],
            'customer_email' => $email,
            'checkout_token' => $token,
        ];
    }
}
