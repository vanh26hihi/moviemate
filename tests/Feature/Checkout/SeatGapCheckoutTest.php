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
            ->assertSee('data-seat-available=', false)
            ->assertSee('id="seatSelectionError"', false)
            ->assertSee('role="alert"', false)
            ->assertSee('aria-live="polite"', false);

        $app = file_get_contents(resource_path('js/app.js'));
        $guard = file_get_contents(resource_path('js/seat-gap-guard.js'));
        $this->assertIsString($app);
        $this->assertIsString($guard);
        $this->assertStringContainsString("import './seat-gap-guard';", $app);
        $this->assertStringContainsString('form.addEventListener("submit"', $guard);
        $this->assertStringContainsString('event.preventDefault()', $guard);
        $this->assertStringContainsString('form.dataset.seatGapInvalid', $guard);
        $this->assertStringContainsString('continueButton.disabled = selected.size === 0 || invalid', $guard);
        $this->assertStringContainsString('form.addEventListener("seat-selection:changed", evaluate)', $guard);
        $this->assertStringContainsString("form.dispatchEvent(new CustomEvent('seat-selection:changed'))", $app);
        $this->assertStringContainsString('error.hidden = true', $guard);
        $this->assertStringContainsString('Không thể tiếp tục vì ghế ${code}', $guard);
        $this->assertStringContainsString('window.addEventListener("pageshow"', $guard);
    }

    public function test_direct_checkout_bypass_redirects_to_visible_exact_vietnamese_error(): void
    {
        $scenario = $this->threeSeatScenario();
        $url = route('user.bookings.checkout', $scenario['showtime']).'?selected_seats='.
            $scenario['seats'][0]->id.','.$scenario['seats'][2]->id;

        $response = $this->get($url);

        $response->assertRedirect(route('user.bookings.selectSeat', $scenario['showtime']));
        $this->withoutVite();
        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('Không thể tiếp tục vì ghế A2 sẽ bị bỏ trống một mình trong hàng A.')
            ->assertSee('role="alert"', false);
    }

    public function test_direct_confirm_post_preserves_safe_selection_and_displays_gap_error(): void
    {
        $this->seedRbac();
        $user = $this->userWithRole('user');
        $scenario = $this->threeSeatScenario();
        $selection = $scenario['seats'][0]->id.','.$scenario['seats'][2]->id;
        $selectUrl = route('user.bookings.selectSeat', $scenario['showtime']);

        $this->actingAs($user)->get(
            route('user.bookings.checkout', $scenario['showtime']).'?selected_seats='.$selection,
        )->assertRedirect($selectUrl);

        $response = $this->from($selectUrl)->post(route('user.bookings.confirm'));
        $response->assertRedirect($selectUrl);

        $this->withoutVite();
        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('Không thể tiếp tục vì ghế A2 sẽ bị bỏ trống một mình trong hàng A.')
            ->assertSee('aria-pressed="true"', false);
    }

    public function test_couple_pair_counts_as_one_logical_seat_unit(): void
    {
        config(['booking.max_logical_seat_units' => 1]);
        $scenario = $this->bookingScenario();
        $coupleIds = $scenario['seats']->where('type', 'couple')->pluck('id')->all();

        $booking = $this->reserve($scenario, $coupleIds)->booking;

        $this->assertSame(2, $booking->bookingSeats()->count());
    }

    public function test_logical_seat_limit_rejects_an_additional_single_beside_one_couple_pair(): void
    {
        config(['booking.max_logical_seat_units' => 1]);
        $scenario = $this->bookingScenario();
        $seatIds = $scenario['seats']->where('status', 'active')->pluck('id')->all();

        try {
            $this->reserve($scenario, $seatIds);
            $this->fail('One single seat plus one couple pair must exceed a one-unit limit.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Mỗi đơn chỉ được chọn tối đa 1 ghế hoặc cặp ghế đôi.'],
                $exception->errors()['seat_ids'],
            );
        }

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('booking_seats', 0);
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
