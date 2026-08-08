<?php

namespace Tests\Feature\Checkout;

use App\Models\Order;
use App\Models\Payment;
use App\Services\BookingTokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Payments\PaymentTestCase;

final class BookingExpiryHotfixTest extends PaymentTestCase
{
    public function test_seat_map_lazily_releases_expired_hold_and_history_renders_canonical_state(): void
    {
        $this->seedRbac();
        $customerA = $this->userWithRole('user');
        $customerB = $this->userWithRole('user');
        $scenario = $this->twoAvailableSeatScenario();
        $seatIds = $scenario['seats']->pluck('id')->all();
        $booking = $this->reserve($scenario, $seatIds, $customerA->id)->booking;
        Order::query()->create([
            'booking_id' => $booking->id,
            'user_id' => $customerA->id,
            'customer_name' => $customerA->name,
            'customer_email' => $customerA->email,
            'pickup_cinema_id' => $scenario['cinema']->id,
            'subtotal' => 0,
            'total_amount' => 0,
            'status' => 'pending',
        ]);

        $this->withoutVite();
        $this->actingAs($customerB)
            ->get(route('user.bookings.selectSeat', $scenario['showtime']))
            ->assertOk()
            ->assertSee('Ghế A1, loại Thường, đã có người giữ')
            ->assertSee('Ghế A2, loại Thường, đã có người giữ');
        $this->actingAs($customerA)
            ->get(route('user.bookings.history'))
            ->assertOk()
            ->assertSee('Chờ thanh toán')
            ->assertSee('Thanh toán tiếp')
            ->assertSee('Hủy đơn');

        $this->travel(15)->minutes();
        $this->travel(1)->seconds();

        $this->actingAs($customerB)
            ->get(route('user.bookings.selectSeat', $scenario['showtime']))
            ->assertOk()
            ->assertSee('Ghế A1, loại Thường, còn trống')
            ->assertSee('Ghế A2, loại Thường, còn trống');

        $this->assertSame('expired', $booking->fresh()->booking_status);
        $this->assertSame(0, $booking->bookingSeats()->whereNotNull('active_lock_key')->count());
        $this->assertDatabaseHas('orders', ['booking_id' => $booking->id, 'status' => 'expired']);
        $this->assertDatabaseCount('booking_ticket_deliveries', 0);

        $history = $this->actingAs($customerA)->get(route('user.bookings.history'))->assertOk();
        $history->assertSee('Đã hết hạn')
            ->assertDontSee(route('user.bookings.pending', $booking), false)
            ->assertDontSee('action="'.route('user.bookings.cancel', $booking).'"', false);
    }

    public function test_direct_hold_reacquires_expired_seats_without_opening_seat_map_first(): void
    {
        $scenario = $this->twoAvailableSeatScenario();
        $seatIds = $scenario['seats']->pluck('id')->all();
        $expired = $this->reserve($scenario, $seatIds)->booking;
        $expired->forceFill(['expires_at' => now()->subSecond()])->save();

        $replacement = $this->reserve(
            $scenario,
            $seatIds,
            null,
            app(BookingTokenService::class)->issueCheckoutToken(),
        )->booking;

        $this->assertSame('expired', $expired->fresh()->booking_status);
        $this->assertSame(0, $expired->bookingSeats()->whereNotNull('active_lock_key')->count());
        $this->assertNotSame($expired->id, $replacement->id);
        $this->assertSame(2, $replacement->bookingSeats()->whereNotNull('active_lock_key')->count());
    }

    public function test_payment_continue_expires_booking_before_provider_io_and_shows_safe_message(): void
    {
        $this->seedRbac();
        $customer = $this->userWithRole('user');
        $scenario = $this->twoAvailableSeatScenario();
        $booking = $this->reserve($scenario, $scenario['seats']->pluck('id')->all(), $customer->id)->booking;
        $booking->forceFill(['expires_at' => now()->subSecond()])->save();
        Http::fake();

        $this->actingAs($customer)
            ->post(route('payments.zalopay.initiate', $booking))
            ->assertRedirect(route('user.bookings.expired', $booking))
            ->assertSessionHas('warning', 'Thời gian giữ ghế đã hết. Vui lòng chọn ghế lại.');

        $this->assertSame('expired', $booking->fresh()->booking_status);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('booking_ticket_deliveries', 0);
        Http::assertNothingSent();
    }

    public function test_unresolved_provider_attempt_retains_expired_ttl_hold_for_safe_review(): void
    {
        $scenario = $this->twoAvailableSeatScenario();
        $seatIds = $scenario['seats']->pluck('id')->all();
        $booking = $this->reserve($scenario, $seatIds)->booking;
        $this->pendingPayment($booking, ['status' => Payment::STATUS_UNRESOLVED]);
        $booking->forceFill(['expires_at' => now()->subSecond()])->save();

        $this->withoutVite();
        $this->get(route('user.bookings.selectSeat', $scenario['showtime']))
            ->assertOk()
            ->assertSee('Ghế A1, loại Thường, đã có người giữ')
            ->assertSee('Ghế A2, loại Thường, đã có người giữ');

        $this->assertSame('pending_payment', $booking->fresh()->booking_status);
        $this->assertSame(2, $booking->bookingSeats()->whereNotNull('active_lock_key')->count());
    }

    public function test_countdown_requests_server_reconciliation_instead_of_claiming_release(): void
    {
        $javascript = file_get_contents(resource_path('js/app.js'));
        $success = file_get_contents(resource_path('views/user/bookings/success.blade.php'));

        $this->assertIsString($javascript);
        $this->assertIsString($success);
        $this->assertStringContainsString('Thời gian giữ ghế đã hết.', $success);
        $this->assertStringContainsString('data-expiry-reload="true"', $success);
        $this->assertStringContainsString('window.location.reload()', $javascript);
        $this->assertStringNotContainsString('active_lock_key', $javascript);
    }

    public function test_lazy_expiry_query_counts_are_bounded(): void
    {
        $this->withoutVite();

        $availableScenario = $this->twoAvailableSeatScenario();
        $seatMapWithoutStaleHold = $this->measureQueries(
            fn () => $this->get(route('user.bookings.selectSeat', $availableScenario['showtime']))->assertOk(),
        );

        $staleScenario = $this->twoAvailableSeatScenario();
        $staleBooking = $this->reserve(
            $staleScenario,
            $staleScenario['seats']->pluck('id')->all(),
        )->booking;
        $staleBooking->forceFill(['expires_at' => now()->subSecond()])->save();
        $seatMapWithOneStaleHold = $this->measureQueries(
            fn () => $this->get(route('user.bookings.selectSeat', $staleScenario['showtime']))->assertOk(),
        );

        $directScenario = $this->twoAvailableSeatScenario();
        $directSeatIds = $directScenario['seats']->pluck('id')->all();
        $directStaleBooking = $this->reserve($directScenario, $directSeatIds)->booking;
        $directStaleBooking->forceFill(['expires_at' => now()->subSecond()])->save();
        $directHoldAfterStaleHolder = $this->measureQueries(fn () => $this->reserve(
            $directScenario,
            $directSeatIds,
            null,
            app(BookingTokenService::class)->issueCheckoutToken(),
        ));

        $this->seedRbac();
        $customer = $this->userWithRole('user');
        $historyScenario = $this->twoAvailableSeatScenario();
        $historyBooking = $this->reserve(
            $historyScenario,
            $historyScenario['seats']->pluck('id')->all(),
            $customer->id,
        )->booking;
        $historyBooking->forceFill(['expires_at' => now()->subSecond()])->save();
        $bookingHistory = $this->measureQueries(
            fn () => $this->actingAs($customer)->get(route('user.bookings.history'))->assertOk(),
        );

        $counts = compact(
            'seatMapWithoutStaleHold',
            'seatMapWithOneStaleHold',
            'directHoldAfterStaleHolder',
            'bookingHistory',
        );
        $diagnostic = json_encode($counts, JSON_THROW_ON_ERROR);
        $this->assertLessThanOrEqual(30, $seatMapWithoutStaleHold, 'Seat map query count is unbounded. '.$diagnostic);
        $this->assertLessThanOrEqual(45, $seatMapWithOneStaleHold, 'Stale seat map query count is unbounded. '.$diagnostic);
        $this->assertLessThanOrEqual(45, $directHoldAfterStaleHolder, 'Direct hold query count is unbounded. '.$diagnostic);
        $this->assertLessThanOrEqual(35, $bookingHistory, 'Booking history query count is unbounded. '.$diagnostic);

        if (getenv('REPORT_QUERY_COUNTS') === '1') {
            fwrite(STDOUT, 'BOOKING_EXPIRY_QUERY_COUNTS='.$diagnostic.PHP_EOL);
        }
    }

    private function twoAvailableSeatScenario(): array
    {
        $scenario = $this->bookingScenario(false);
        $scenario['seats'][1]->forceFill(['status' => 'active'])->save();

        return $scenario;
    }

    /** @param callable(): mixed $callback */
    private function measureQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $callback();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }
}
