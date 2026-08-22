<?php

namespace Tests\Feature\Bookings;

use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

final class PendingBookingExpiryScheduleTest extends TestCase
{
    public function test_authoritative_pending_booking_expiry_command_is_scheduled_every_minute(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->where('description', 'expire-stale-seat-holds')
            ->values();

        $this->assertCount(1, $events);

        $event = $events->sole();

        $this->assertNotInstanceOf(CallbackEvent::class, $event);
        $this->assertStringContainsString('bookings:expire-pending', (string) $event->command);
        $this->assertSame('* * * * *', $event->getExpression());
        $this->assertTrue($event->withoutOverlapping);
        $this->assertStringNotContainsString(
            'SeatHoldService',
            file_get_contents(base_path('routes/console.php')),
        );
    }
}
