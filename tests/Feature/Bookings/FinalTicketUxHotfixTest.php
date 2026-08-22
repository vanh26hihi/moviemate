<?php

namespace Tests\Feature\Bookings;

use App\Services\Tickets\BookingLookupCapability;
use App\Services\Tickets\BookingQrPayload;
use App\Services\Tickets\TicketQrCode;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\Feature\Payments\PaymentTestCase;

final class FinalTicketUxHotfixTest extends PaymentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_secure_booking_qr_decodes_to_the_exact_counter_lookup_capability(): void
    {
        $owner = $this->userWithRole('user');
        $scenario = $this->bookingScenario(false);
        $booking = $this->reserve($scenario, [$scenario['seats'][0]->id], $owner->id)->booking;
        $payment = $this->pendingPayment($booking);
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))
            ->assertJsonPath('return_code', 1);
        $booking = $booking->fresh();
        $payload = app(BookingQrPayload::class)->value($booking);
        $png = app(TicketQrCode::class)->png($payload);

        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
        $decoder = new Process(['node', base_path('tests/Support/decode-qr.mjs')]);
        $decoder->setInput(base64_encode($png));
        $decoder->mustRun();
        $this->assertSame($payload, $decoder->getOutput());

        $this->assertTrue(app(BookingLookupCapability::class)->isValid($booking, $payload));
    }

    public function test_qr_wrapper_rejects_tampering_foreign_hosts_and_queries(): void
    {
        $payment = $this->pendingPayment();
        $this->postJson(route('payments.zalopay.callback'), $this->callbackBody($payment))->assertJsonPath('return_code', 1);
        $booking = $payment->booking->fresh();
        $capability = app(BookingLookupCapability::class)->issue($booking);
        $tampered = substr($capability, 0, -1).($capability[-1] === 'A' ? 'B' : 'A');

        $this->assertFalse(app(BookingLookupCapability::class)->isValid($booking, $tampered));
        $this->assertNull(app(BookingQrPayload::class)->capabilityFrom('https://attacker.example/'.$capability));
        $this->assertNull(app(BookingQrPayload::class)->capabilityFrom($capability.'?redirect=https://attacker.example'));
        $this->assertSame($capability, app(BookingQrPayload::class)->capabilityFrom($capability));
    }

    public function test_customer_cancel_modal_is_centered_accessible_and_guarded(): void
    {
        $history = File::get(resource_path('views/user/bookings/history.blade.php'));
        $modal = File::get(resource_path('views/components/ui/modal.blade.php'));
        $javascript = File::get(resource_path('js/app.js'));

        $this->assertStringNotContainsString('<dialog', $history);
        $this->assertStringNotContainsString('showModal()', $history);
        $this->assertStringContainsString('data-modal-open="cancel-booking-', $history);
        $this->assertStringContainsString('data-submit-once', $history);
        $this->assertStringContainsString('@csrf', $history);
        $this->assertStringContainsString('fixed inset-0', $modal);
        $this->assertStringContainsString('place-items-center', $modal);
        $this->assertStringContainsString('aria-modal="true"', $modal);
        $this->assertStringContainsString('modalFocusableSelector', $javascript);
        $this->assertStringContainsString("event.key === 'Tab'", $javascript);
        $this->assertStringContainsString("event.key !== 'Escape'", $javascript);
    }
}
