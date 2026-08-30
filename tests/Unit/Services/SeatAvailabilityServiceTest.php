<?php

namespace Tests\Unit\Services;

use App\Models\SeatHold;
use App\Models\User;
use App\Services\Seats\SeatAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

class SeatAvailabilityServiceTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    public function test_it_reports_available_and_held_seats_for_a_showtime(): void
    {
        $scenario = $this->bookingScenario();
        $user = User::factory()->create();
        $service = app(SeatAvailabilityService::class);

        SeatHold::query()->create([
            'user_id' => $user->id,
            'showtime_id' => $scenario['showtime']->id,
            'seat_id' => $scenario['seats'][0]->id,
            'expires_at' => now()->addMinutes(5),
        ]);

        $summary = $service->summary($scenario['showtime'], $scenario['layout']);

        $this->assertGreaterThan(0, $summary['total']);
        $this->assertSame('held', $summary['status_by_seat'][$scenario['seats'][0]->id]);
        $this->assertNotContains((int) $scenario['seats'][0]->id, $summary['available']);
        $this->assertTrue($service->isAvailable($scenario['showtime'], $scenario['layout'], $scenario['seats'][2]->id));
    }
}
