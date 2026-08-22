<?php

namespace Tests\Unit\Services;

use App\Models\SeatHold;
use App\Models\User;
use App\Services\Seats\SeatHoldExpirationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

class SeatHoldExpirationPolicyTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    public function test_it_tracks_warning_and_expiration_for_a_hold(): void
    {
        $scenario = $this->bookingScenario();
        $user = User::factory()->create();
        $policy = app(SeatHoldExpirationPolicy::class);

        $hold = SeatHold::query()->create([
            'user_id' => $user->id,
            'showtime_id' => $scenario['showtime']->id,
            'seat_id' => $scenario['seats'][0]->id,
            'expires_at' => now()->addMinutes(2),
        ]);

        $this->assertTrue($policy->shouldWarn($hold, 2));
        $this->assertGreaterThanOrEqual(0, $policy->minutesRemaining($hold));
        $this->assertTrue($policy->holdDeadline($scenario['showtime'], 7)->isFuture());
    }
}
