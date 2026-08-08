<?php

namespace Tests\Feature\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;

class PayOsAdminTest extends PayOsPaymentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->withoutVite();
    }

    public function test_admin_detail_uses_generic_payos_presentation_without_sensitive_provider_data(): void
    {
        $payment = $this->payOsPayment(overrides: [
            'payment_url' => 'https://pay.payos.vn/web/private-provider-identifier',
        ]);

        $this->actingAs($this->userWithRole('manager'))
            ->get(route('admin.payments.show', $payment))
            ->assertOk()
            ->assertSee('payOS')
            ->assertSee($payment->status_label)
            ->assertDontSee('private-provider-identifier')
            ->assertDontSee(self::CHECKSUM_KEY);
    }

    public function test_authorized_admin_reconciliation_queries_existing_payos_review_attempt(): void
    {
        $payment = $this->payOsPayment(overrides: [
            'status' => Payment::STATUS_REVIEW,
            'failure_reason' => 'payos_unknown_status',
        ]);
        Http::fake(fn () => Http::response($this->providerEnvelope(
            $this->queryData($payment, 'PAID'),
        )));

        $this->actingAs($this->userWithRole('manager'))
            ->post(route('admin.payments.reconcile', $payment))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(Payment::STATUS_SUCCESS, $payment->fresh()->status);
        $this->assertSame('paid', $payment->booking->fresh()->booking_status);
        $this->assertDatabaseCount('booking_ticket_deliveries', 1);
    }
}
