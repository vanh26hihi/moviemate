<?php

namespace Tests\Unit\Services;

use App\Services\BookingLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

class BookingLifecycleServiceTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    public function test_it_reports_the_active_booking_phase_and_actions(): void
    {
        $scenario = $this->bookingScenario();
        $booking = $this->bookingForScenario($scenario, [
            'booking_status' => 'pending_payment',
            'payment_status' => 'unpaid',
            'expires_at' => now()->addMinutes(12),
        ]);

        $service = app(BookingLifecycleService::class);
        $summary = $service->summary($booking);

        $this->assertSame('pending', $summary['phase']);
        $this->assertTrue($summary['can_be_paid']);
        $this->assertTrue($summary['requires_action']);
        $this->assertContains('pay', $summary['allowed_transitions']);
        $this->assertContains('cancel', $summary['allowed_transitions']);
        $this->assertSame('Chờ thanh toán', $summary['status_label']);
    }

    public function test_it_allows_valid_transitions_for_paid_and_expired_bookings(): void
    {
        $scenario = $this->bookingScenario();
        $service = app(BookingLifecycleService::class);

        $paid = $this->bookingForScenario($scenario, [
            'booking_status' => 'paid',
            'payment_status' => 'paid',
            'expires_at' => now()->addMinutes(20),
        ]);

        $expired = $this->bookingForScenario($scenario, [
            'booking_status' => 'expired',
            'payment_status' => 'failed',
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertTrue($service->canTransitionTo($paid, 'deliver'));
        $this->assertTrue($service->canTransitionTo($expired, 'rebook'));
        $this->assertFalse($service->canTransitionTo($paid, 'expire'));
        $this->assertSame('expired', $service->phase($expired));
    }
}
