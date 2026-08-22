<?php

namespace Tests\Unit\Services;

use App\Models\Booking;
use App\Services\BookingActionPolicy;
use Tests\TestCase;

class BookingActionPolicyTest extends TestCase
{
    public function test_it_allows_payment_for_pending_unpaid_booking(): void
    {
        $booking = new Booking([
            'booking_status' => 'pending_payment',
            'payment_status' => 'unpaid',
            'expires_at' => now()->addMinutes(10),
        ]);

        $policy = app(BookingActionPolicy::class);

        $this->assertTrue($policy->canPay($booking));
        $this->assertSame('pay', $policy->recommendedAction($booking));
    }

    public function test_it_flags_expired_pending_booking_for_expiration(): void
    {
        $booking = new Booking([
            'booking_status' => 'pending_payment',
            'payment_status' => 'unpaid',
            'expires_at' => now()->subMinute(),
        ]);

        $policy = app(BookingActionPolicy::class);

        $this->assertTrue($policy->canExpire($booking));
        $this->assertSame('expire', $policy->recommendedAction($booking));
    }

    public function test_it_allows_delivery_for_paid_booking(): void
    {
        $booking = new Booking([
            'booking_status' => 'paid',
            'payment_status' => 'paid',
        ]);

        $policy = app(BookingActionPolicy::class);

        $this->assertTrue($policy->canDeliver($booking));
        $this->assertSame('deliver', $policy->recommendedAction($booking));
    }
}
