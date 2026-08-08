<?php

namespace Tests\Feature\Payments;

use App\Models\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

class PayOsSchemaTest extends PayOsPaymentTestCase
{
    public function test_cross_provider_active_attempt_constraint_is_present(): void
    {
        $this->assertTrue(Schema::hasColumn('payments', 'booking_attempt_guard'));
        $index = collect(Schema::getIndexes('payments'))
            ->firstWhere('name', 'payments_booking_active_attempt_unique');

        $this->assertNotNull($index);
        $this->assertTrue((bool) $index['unique']);
        $this->assertSame(['booking_id', 'booking_attempt_guard'], $index['columns']);
    }

    public function test_database_rejects_simultaneous_active_attempts_across_providers(): void
    {
        $booking = $this->payableBooking();
        $this->pendingPayment($booking);

        $this->expectException(QueryException::class);
        Payment::createForProvider('payos', [
            'booking_id' => $booking->id,
            'payment_method' => 'payos',
            'order_code' => '987654321',
            'amount' => 50000,
            'currency' => 'VND',
            'status' => Payment::STATUS_PENDING,
            'description' => 'MM987654',
            'expires_at' => now()->addMinutes(10),
            'reconcile_until' => now()->addHours(24),
        ]);
    }
}
