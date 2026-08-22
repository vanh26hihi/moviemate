<?php

namespace Tests\Unit\Services;

use App\Models\Booking;
use App\Services\BookingStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

class BookingStateServiceTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    public function test_it_normalizes_booking_statuses_and_next_actions(): void
    {
        $scenario = $this->bookingScenario();
        $booking = $this->bookingForScenario($scenario, [
            'booking_status' => 'pending_payment',
            'payment_status' => 'unpaid',
            'expires_at' => now()->addMinutes(10),
        ]);

        $service = app(BookingStateService::class);
        $summary = $service->summary($booking);

        $this->assertSame('pending', $service->normalizedStatus($booking));
        $this->assertTrue($service->canBePaid($booking));
        $this->assertTrue($summary['can_be_paid']);
        $this->assertSame('Chờ thanh toán', $service->statusLabel($booking));
        $this->assertStringContainsString('thanh toán', $service->nextActionHint($booking));
    }

    public function test_it_marks_expired_and_paid_bookings_correctly(): void
    {
        $scenario = $this->bookingScenario();
        $service = app(BookingStateService::class);

        $pendingExpired = $this->bookingForScenario($scenario, [
            'booking_status' => 'pending_payment',
            'payment_status' => 'unpaid',
            'expires_at' => now()->subMinute(),
        ]);

        $paidBooking = $this->bookingForScenario($scenario, [
            'booking_status' => 'paid',
            'payment_status' => 'paid',
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->assertTrue($service->isExpired($pendingExpired));
        $this->assertSame('paid', $service->normalizedStatus($paidBooking));
        $this->assertSame('Đã thanh toán', $service->statusLabel($paidBooking));
        $this->assertSame('Hoàn tất và gửi vé cho khách.', $service->nextActionHint($paidBooking));
    }
}
