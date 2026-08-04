<?php

namespace Tests\Feature\Bookings;

use App\Models\Payment;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Payments\PaymentTestCase;

class Phase4BookingPresentationAcceptanceTest extends PaymentTestCase
{
    public function test_only_a_paid_booking_renders_a_usable_qr_ticket_and_download_control(): void
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

        $this->actingAs($owner)
            ->get(route('user.bookings.ticket', $booking))
            ->assertOk()
            ->assertSee('data-qr-value="'.$booking->booking_code.'"', false)
            ->assertSee('data-ticket-download="ticket-image-card"', false);
    }

    #[DataProvider('nonUsableBookingStates')]
    public function test_non_usable_booking_states_have_explicit_presentation_without_qr_or_download(
        string $bookingStatus,
        string $paymentStatus,
        string $expectedIcon,
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
            ->assertSee($expectedIcon, false)
            ->assertDontSee('data-qr-value', false)
            ->assertDontSee('data-ticket-download', false);
    }

    public static function nonUsableBookingStates(): array
    {
        return [
            'payment review' => ['pending_payment', Payment::STATUS_REVIEW, 'ph-warning'],
            'expired' => ['expired', Payment::STATUS_EXPIRED, 'ph-clock'],
            'cancelled' => ['cancelled', Payment::STATUS_FAILED, 'ph-x-circle'],
            'used' => ['used', Payment::STATUS_SUCCESS, 'ph-checks'],
        ];
    }
}
