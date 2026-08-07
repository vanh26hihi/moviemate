<?php

namespace Tests\Feature\Bookings;

use App\Mail\BookingTicketMail;
use App\Services\Tickets\TicketCheckinCapability;
use App\Services\Tickets\TicketQrCode;
use App\Services\Tickets\TicketQrPayload;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Payments\PaymentTestCase;

class PrintableTicketTest extends PaymentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_paid_owner_sees_read_only_electronic_ticket_with_opaque_qr(): void
    {
        [$owner, $booking] = $this->paidOwnerBooking();
        $response = $this->actingAs($owner)->get(route('user.bookings.ticket', $booking));

        $response->assertOk()->assertSee('VÉ ĐIỆN TỬ')->assertSee('VÉ HỢP LỆ')
            ->assertSee($booking->booking_code)->assertSee('Booking Foundation Movie')
            ->assertSee('data-qr-value="'.route('tickets.verify', ['capability' => app(TicketCheckinCapability::class)->issue($booking)]).'"', false)
            ->assertDontSee('data-qr-value="'.$booking->booking_code.'"', false)
            ->assertDontSee('In vé')->assertDontSee('Lưu PDF')->assertDontSee('Lưu ảnh')
            ->assertDontSee('data-print-ticket', false)->assertDontSee('api.qrserver.com', false);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertFalse(Route::has('user.bookings.ticket.print'));
    }

    public function test_another_customer_cannot_view_ticket_and_staff_print_route_is_forbidden(): void
    {
        [, $booking] = $this->paidOwnerBooking();
        $other = $this->userWithRole('user');

        $this->actingAs($other)->get(route('user.bookings.ticket', $booking))->assertForbidden();
        $this->actingAs($other)->post(route('staff.tickets.print.start', $booking))->assertForbidden();
    }

    public function test_used_ticket_remains_readable_with_first_checkin_time_and_stable_qr(): void
    {
        [$owner, $booking] = $this->paidOwnerBooking();
        $capability = app(TicketQrPayload::class)->url($booking);
        $booking->forceFill(['booking_status' => 'used', 'used_at' => now()->subMinute()])->save();

        $this->actingAs($owner)->get(route('user.bookings.ticket', $booking))
            ->assertOk()->assertSee('VÉ ĐÃ ĐƯỢC SỬ DỤNG')
            ->assertSee('data-qr-value="'.$capability.'"', false)
            ->assertSee('không thể kích hoạt lại');
    }

    public function test_cancelled_refunded_expired_and_unpaid_tickets_have_no_qr(): void
    {
        foreach ([
            ['booking_status' => 'cancelled'],
            ['payment_status' => 'refunded'],
            ['booking_status' => 'expired'],
        ] as $state) {
            [$owner, $booking] = $this->paidOwnerBooking();
            $booking->forceFill($state)->save();
            $this->actingAs($owner)->get(route('user.bookings.ticket', $booking))
                ->assertOk()->assertSee('VÉ KHÔNG CÒN HIỆU LỰC')->assertDontSee('data-qr-value', false);
        }
    }

    public function test_ticket_html_never_contains_booking_access_credentials_or_customer_controls(): void
    {
        [$owner, $booking] = $this->paidOwnerBooking();
        $guestSecret = 'guest-secret-that-must-not-render';
        $emailSecret = 'email-secret-that-must-not-render';
        $booking->forceFill([
            'guest_access_token_hash' => hash('sha256', $guestSecret),
            'ticket_email_token_hash' => hash('sha256', $emailSecret),
            'ticket_email_token_nonce' => str_repeat('n', 43),
        ])->save();

        $this->actingAs($owner)->get(route('user.bookings.ticket', $booking))
            ->assertOk()->assertDontSee($guestSecret)->assertDontSee($emailSecret)
            ->assertDontSee(hash('sha256', $guestSecret))->assertDontSee(hash('sha256', $emailSecret))
            ->assertDontSee('window.print')->assertDontSee('Lưu PDF');
    }

    public function test_couple_pair_uses_one_combined_label_on_ticket_and_email(): void
    {
        $owner = $this->userWithRole('user');
        $scenario = $this->bookingScenario(true);
        $pair = $scenario['seats']->where('type', 'couple')->values();
        $booking = $this->reserve($scenario, $pair->pluck('id')->all(), $owner->id)->booking;
        $payment = $this->pendingPayment($booking);
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))->assertJsonPath('return_code', 1);
        $booking = $booking->fresh()->load([
            'bookingSeats.seat', 'showtime.movie', 'showtime.room', 'showtime.cinema',
            'payments', 'foodOrder.items', 'user',
        ]);

        $this->actingAs($owner)->get(route('user.bookings.ticket', $booking))
            ->assertOk()->assertSee('Ghế đôi B1–B2')->assertDontSee('Ghế B1 ·')->assertDontSee('Ghế B2 ·');

        $capability = app(TicketQrPayload::class)->url($booking);
        $email = (new BookingTicketMail(
            $booking,
            'https://example.test/ticket',
            app(TicketQrCode::class)->png($capability),
        ))->render();
        $this->assertStringContainsString('Ghế đôi B1–B2', $email);
        $this->assertStringContainsString('Mã QR xác minh vé MovieMate', $email);
        $this->assertStringNotContainsString('Ghế B1, Ghế B2', $email);
    }

    private function paidOwnerBooking(): array
    {
        $owner = $this->userWithRole('user');
        $scenario = $this->bookingScenario(false);
        $booking = $this->reserve($scenario, [$scenario['seats'][0]->id], $owner->id)->booking;
        $payment = $this->pendingPayment($booking);
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))->assertJsonPath('return_code', 1);

        return [$owner, $booking->fresh()];
    }
}
