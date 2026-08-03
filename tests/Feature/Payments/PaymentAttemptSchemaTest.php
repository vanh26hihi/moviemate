<?php

namespace Tests\Feature\Payments;

use App\Models\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

class PaymentAttemptSchemaTest extends PaymentTestCase
{
    public function test_payment_attempt_audit_schema_and_indexes_are_present(): void
    {
        $this->assertTrue(Schema::hasColumns('payments', [
            'booking_id', 'provider', 'app_id', 'app_trans_id', 'app_user', 'app_time_ms',
            'amount', 'currency', 'status', 'description', 'expires_at', 'zp_trans_id',
            'zp_trans_token', 'order_token', 'order_url', 'qr_code', 'provider_return_code',
            'provider_sub_return_code', 'provider_return_message', 'provider_sub_return_message',
            'server_time_ms', 'callback_received_at', 'last_queried_at', 'verified_at',
            'paid_at', 'failed_at', 'failure_reason', 'create_response_hash',
            'callback_payload_hash', 'query_response_hash',
        ]));
        $this->assertTrue(Schema::hasColumn('bookings', 'ticket_emailed_at'));
        $this->assertFalse(Schema::hasColumn('payments', 'key1'));
        $this->assertFalse(Schema::hasColumn('payments', 'key2'));
        $this->assertFalse(Schema::hasColumn('payments', 'mac'));
    }

    public function test_booking_supports_many_attempts_and_singular_relationship_returns_latest(): void
    {
        $booking = $this->payableBooking();
        $first = $this->pendingPayment($booking, ['status' => Payment::STATUS_FAILED]);
        $second = $this->pendingPayment($booking);

        $this->assertCount(2, $booking->payments);
        $this->assertSame($second->id, $booking->payment->id);
        $this->assertNotSame($first->id, $booking->payment->id);
    }

    public function test_provider_app_identity_is_unique(): void
    {
        $payment = $this->pendingPayment();

        $this->expectException(QueryException::class);
        $this->pendingPayment($payment->booking, [
            'app_id' => $payment->app_id,
            'app_trans_id' => $payment->app_trans_id,
        ]);
    }

    public function test_nullable_zp_transaction_identity_is_unique_when_present(): void
    {
        $this->pendingPayment(overrides: ['zp_trans_id' => '123456789']);

        $this->expectException(QueryException::class);
        $this->pendingPayment(overrides: ['zp_trans_id' => '123456789']);
    }
}
