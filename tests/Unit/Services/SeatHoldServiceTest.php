<?php

namespace Tests\Unit\Services;

use App\Models\SeatHold;
use App\Models\User;
use App\Services\SeatHoldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

class SeatHoldServiceTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    public function test_it_provides_a_complete_hold_snapshot_for_the_user(): void
    {
        $scenario = $this->bookingScenario();
        $user = User::factory()->create();

        $expiresAt = app(SeatHoldService::class)->holdSeats($user, $scenario['showtime'], [$scenario['seats'][0]->id]);

        $snapshot = app(SeatHoldService::class)->getSeatHoldSummary($user, $scenario['showtime']);

        $this->assertSame([$scenario['seats'][0]->id], $snapshot['seat_ids']);
        $this->assertSame($expiresAt->format('Y-m-d H:i:s'), $snapshot['expires_at']->format('Y-m-d H:i:s'));
        $this->assertSame(1, $snapshot['count']);
        $this->assertTrue($snapshot['is_active']);
        $this->assertDatabaseHas('seat_holds', [
            'user_id' => $user->id,
            'showtime_id' => $scenario['showtime']->id,
            'seat_id' => $scenario['seats'][0]->id,
        ]);
    }

    public function test_it_expands_hold_count_and_releases_selected_seats_only(): void
    {
        $scenario = $this->bookingScenario();
        $user = User::factory()->create();
        $service = app(SeatHoldService::class);

        $service->holdSeats($user, $scenario['showtime'], [$scenario['seats'][0]->id]);
        $service->holdSeats($user, $scenario['showtime'], [$scenario['seats'][0]->id, $scenario['seats'][2]->id]);

        $this->assertSame(2, SeatHold::query()->where('user_id', $user->id)->where('showtime_id', $scenario['showtime']->id)->count());

        $service->release($user, $scenario['showtime'], [$scenario['seats'][0]->id]);

        $this->assertSame(1, SeatHold::query()->where('user_id', $user->id)->where('showtime_id', $scenario['showtime']->id)->count());
        $this->assertDatabaseMissing('seat_holds', [
            'user_id' => $user->id,
            'showtime_id' => $scenario['showtime']->id,
            'seat_id' => $scenario['seats'][0]->id,
        ]);
    }
}
