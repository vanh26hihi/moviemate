<?php

namespace Tests\Feature\Bookings;

use App\Models\Payment;
use App\Services\Tickets\TicketCheckinCapability;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Payments\PaymentTestCase;

class Phase4BookingPresentationAcceptanceTest extends PaymentTestCase
{
    public function test_only_a_paid_booking_with_a_successful_payment_renders_a_usable_qr_without_customer_print_control(): void
    {
        $this->seedRbac();
        $owner = $this->userWithRole('user');
        $scenario = $this->bookingScenario(false);
        $booking = $this->reserve($scenario, [$scenario['seats'][0]->id], $owner->id)->booking;
        $booking->forceFill([
            'booking_status' => 'paid',
            'payment_status' => 'paid',
            'paid_at' => now(),
        ])->save();
        $this->pendingPayment($booking, [
            'status' => Payment::STATUS_SUCCESS,
            'verified_at' => now(),
            'paid_at' => now(),
            'zp_trans_id' => (string) random_int(100000, 999999),
        ]);

        $this->actingAs($owner)
            ->get(route('user.bookings.ticket', $booking))
            ->assertOk()
            ->assertSee('data-qr-value="'.route('tickets.verify', ['capability' => app(TicketCheckinCapability::class)->issue($booking)]).'"', false)
            ->assertDontSee('data-print-ticket', false)
            ->assertDontSee('Lưu PDF');
    }

    #[DataProvider('nonUsableBookingStates')]
    public function test_non_usable_booking_states_have_explicit_presentation_without_qr_or_download(
        string $bookingStatus,
        string $paymentStatus,
        string $expectedState,
    ): void {
        $this->seedRbac();
        $owner = $this->userWithRole('user');
        $scenario = $this->bookingScenario(false);
        $booking = $this->reserve($scenario, [$scenario['seats'][0]->id], $owner->id)->booking;
        $booking->forceFill([
            'booking_status' => $bookingStatus,
            'payment_status' => $bookingStatus === 'used' ? 'paid' : 'unpaid',
            'used_at' => $bookingStatus === 'used' ? now() : null,
        ])->save();
        $this->pendingPayment($booking, ['status' => $paymentStatus]);

        $this->actingAs($owner)
            ->get(route('user.bookings.ticket', $booking))
            ->assertOk()
            ->assertSee($expectedState)
            ->assertDontSee('data-qr-value', false)
            ->assertDontSee('data-print-ticket', false);
    }

    public static function nonUsableBookingStates(): array
    {
        return [
            'payment review' => ['pending_payment', Payment::STATUS_REVIEW, 'Đơn chưa có vé xem phim hợp lệ để sử dụng.'],
            'expired' => ['expired', Payment::STATUS_EXPIRED, 'Đơn chưa có vé xem phim hợp lệ để sử dụng.'],
            'cancelled' => ['cancelled', Payment::STATUS_FAILED, 'Đơn chưa có vé xem phim hợp lệ để sử dụng.'],
            'used' => ['used', Payment::STATUS_SUCCESS, 'Đơn chưa có vé xem phim hợp lệ để sử dụng.'],
        ];
    }
}
