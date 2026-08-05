<?php

namespace Tests\Feature\Bookings;

use App\Models\Payment;
use Illuminate\Support\Facades\File;
use Tests\Feature\Payments\PaymentTestCase;

class PrintableTicketTest extends PaymentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_paid_owner_can_view_ticket_and_dedicated_print_page(): void
    {
        [$owner, $booking] = $this->paidOwnerBooking();

        $response = $this->actingAs($owner)->get(route('user.bookings.ticket', $booking));
        $response
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('VÉ XEM PHIM')
            ->assertSee($booking->booking_code)
            ->assertSee('Booking Foundation Movie')
            ->assertSee('Test booking room')
            ->assertSee('A1')
            ->assertSee('50.000 VNĐ')
            ->assertSee('data-qr-value="'.$booking->booking_code.'"', false)
            ->assertSee('data-print-ticket', false)
            ->assertDontSee('api.qrserver.com', false);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->actingAs($owner)->get(route('user.bookings.ticket.print', $booking))
            ->assertOk()
            ->assertSee('data-ticket-print-page="true"', false)
            ->assertSee('Lưu PDF / In vé')
            ->assertSee('data-print-ticket', false);
    }

    public function test_another_customer_cannot_view_or_print_the_ticket(): void
    {
        [$owner, $booking] = $this->paidOwnerBooking();
        $other = $this->userWithRole('user');

        $this->actingAs($other)->get(route('user.bookings.ticket', $booking))->assertForbidden();
        $this->actingAs($other)->get(route('user.bookings.ticket.print', $booking))->assertForbidden();
        $this->actingAs($owner)->get(route('user.bookings.ticket.print', $booking))->assertOk();
    }

    public function test_manager_and_staff_print_access_follows_existing_rbac(): void
    {
        [, $booking] = $this->paidOwnerBooking();

        foreach (['manager', 'staff'] as $role) {
            $operator = $this->userWithRole($role);
            $this->actingAs($operator)->get(route('user.bookings.ticket.print', $booking))
                ->assertOk();
        }
    }

    public function test_paid_guest_print_requires_the_exact_scoped_capability(): void
    {
        $scenario = $this->bookingScenario(false);
        $reservation = $this->reserve($scenario, [$scenario['seats'][0]->id]);
        $payment = $this->pendingPayment($reservation->booking);
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))
            ->assertJsonPath('return_code', 1);
        $booking = $reservation->booking->fresh();

        $this->get(route('user.bookings.ticket.print', $booking))->assertNotFound();
        $this->post(route('user.bookings.access.exchange', $booking), [
            'token' => $reservation->guestAccessToken,
            'destination' => 'ticket',
        ])->assertOk();
        $this->get(route('user.bookings.ticket.print', $booking))
            ->assertOk()
            ->assertSee('data-qr-value="'.$booking->booking_code.'"', false);
    }

    public function test_non_paid_and_non_success_payment_states_have_no_usable_print_ticket(): void
    {
        $owner = $this->userWithRole('user');

        foreach ([Payment::STATUS_PENDING, Payment::STATUS_REVIEW, Payment::STATUS_FAILED, Payment::STATUS_EXPIRED] as $paymentStatus) {
            $scenario = $this->bookingScenario(false);
            $booking = $this->reserve($scenario, [$scenario['seats'][0]->id], $owner->id)->booking;
            $this->pendingPayment($booking, ['status' => $paymentStatus]);

            $this->actingAs($owner)->get(route('user.bookings.ticket', $booking))
                ->assertOk()
                ->assertDontSee('data-qr-value', false)
                ->assertDontSee('data-print-ticket', false);
            $this->actingAs($owner)->get(route('user.bookings.ticket.print', $booking))->assertNotFound();
        }
    }

    public function test_cancelled_expired_and_used_bookings_never_render_an_active_qr(): void
    {
        foreach (['cancelled', 'expired', 'used'] as $status) {
            [$owner, $booking] = $this->paidOwnerBooking();
            $booking->forceFill(['booking_status' => $status])->save();

            $this->actingAs($owner)->get(route('user.bookings.ticket', $booking))
                ->assertOk()
                ->assertDontSee('data-qr-value', false)
                ->assertDontSee('data-print-ticket', false);
            $this->actingAs($owner)->get(route('user.bookings.ticket.print', $booking))->assertNotFound();
        }
    }

    public function test_ticket_html_never_contains_booking_access_credentials_or_payment_secrets(): void
    {
        [$owner, $booking] = $this->paidOwnerBooking();
        $guestSecret = 'guest-secret-that-must-not-render';
        $emailSecret = 'email-secret-that-must-not-render';
        $booking->forceFill([
            'guest_access_token_hash' => hash('sha256', $guestSecret),
            'ticket_email_token_hash' => hash('sha256', $emailSecret),
            'ticket_email_token_nonce' => str_repeat('n', 43),
        ])->save();

        $this->actingAs($owner)->get(route('user.bookings.ticket.print', $booking))
            ->assertOk()
            ->assertDontSee($guestSecret)
            ->assertDontSee($emailSecret)
            ->assertDontSee(hash('sha256', $guestSecret))
            ->assertDontSee(hash('sha256', $emailSecret));
    }

    public function test_print_assets_hide_application_chrome_and_use_browser_print_without_auto_print(): void
    {
        $css = File::get(resource_path('css/user.css'));
        $javascript = File::get(resource_path('js/app.js'));

        $this->assertStringContainsString('@media print', $css);
        $this->assertStringContainsString('body.ticket-document-page > header', $css);
        $this->assertStringContainsString('body.ticket-document-page > footer', $css);
        $this->assertStringContainsString('.print-hidden', $css);
        $this->assertStringContainsString('break-inside: avoid', $css);
        $this->assertStringContainsString('max-width: 100mm', $css);
        $this->assertStringContainsString("event.target.closest('[data-print-ticket]')", $javascript);
        $this->assertStringContainsString('window.print()', $javascript);
        $this->assertStringNotContainsString('window.addEventListener(\'load\', window.print', $javascript);
    }

    public function test_paid_success_page_exposes_ticket_print_resend_and_history_actions(): void
    {
        [$owner, $booking] = $this->paidOwnerBooking();

        $this->actingAs($owner)->get(route('user.bookings.success', $booking))
            ->assertOk()
            ->assertSee('Xem vé')
            ->assertSee('In vé')
            ->assertSee('Gửi lại email vé')
            ->assertSee('Về vé của tôi')
            ->assertSee(route('user.bookings.ticket.print', $booking), false);
    }

    public function test_couple_pair_uses_one_combined_label_on_ticket_and_email(): void
    {
        $owner = $this->userWithRole('user');
        $scenario = $this->bookingScenario(true);
        $pair = $scenario['seats']->where('type', 'couple')->values();
        $booking = $this->reserve($scenario, $pair->pluck('id')->all(), $owner->id)->booking;
        $payment = $this->pendingPayment($booking);
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))
            ->assertJsonPath('return_code', 1);
        $booking = $booking->fresh()->load([
            'bookingSeats.seat', 'showtime.movie', 'showtime.room', 'showtime.cinema',
            'payments', 'foodOrder.items', 'user',
        ]);

        $ticket = $this->actingAs($owner)->get(route('user.bookings.ticket', $booking))->assertOk();
        $ticket->assertSee('Ghế đôi B1–B2')->assertDontSee('Ghế B1 ·')->assertDontSee('Ghế B2 ·');

        $email = view('emails.booking-ticket', [
            'booking' => $booking,
            'ticketAccessUrl' => 'https://example.test/ticket',
        ])->render();
        $this->assertStringContainsString('Ghế đôi B1–B2', $email);
        $this->assertStringNotContainsString('Ghế B1, Ghế B2', $email);
    }

    private function paidOwnerBooking(): array
    {
        $owner = $this->userWithRole('user');
        $scenario = $this->bookingScenario(false);
        $booking = $this->reserve($scenario, [$scenario['seats'][0]->id], $owner->id)->booking;
        $payment = $this->pendingPayment($booking);
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))
            ->assertJsonPath('return_code', 1);

        return [$owner, $booking->fresh()];
    }
}
