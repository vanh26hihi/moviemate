<?php

namespace Tests\Unit\Services;

use App\Models\SeatHold;
use App\Models\User;
use App\Services\Seats\SeatHoldPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesBookingFixtures;
use Tests\TestCase;

class SeatHoldPolicyTest extends TestCase
{
    use CreatesBookingFixtures;
    use RefreshDatabase;

    public function test_it_normalizes_and_evaluates_a_selection(): void
    {
        $scenario = $this->bookingScenario();
        $user = User::query()->factory()->create();
        $otherUser = User::query()->factory()->create();
        $service = app(SeatHoldPolicy::class);

        SeatHold::query()->create([
            'user_id' => $otherUser->id,
            'showtime_id' => $scenario['showtime']->id,
            'seat_id' => $scenario['seats'][0]->id,
            'expires_at' => now()->addMinutes(5),
        ]);

        $result = $service->evaluateSelection($user, $scenario['showtime'], [$scenario['seats'][0]->id, $scenario['seats'][2]->id]);

        $this->assertFalse($result['valid']);
        $this->assertSame([$scenario['seats'][0]->id], $result['conflicts']);
        $this->assertSame([$scenario['seats'][0]->id, $scenario['seats'][2]->id], $result['selected']);
        $this->assertSame('Một hoặc nhiều ghế đang được người khác giữ. Vui lòng chọn lại.', $result['message']);
    }

    public function test_it_tracks_active_holds_and_warning_window(): void
    {
        $scenario = $this->bookingScenario();
        $user = User::query()->factory()->create();
        $service = app(SeatHoldPolicy::class);

        SeatHold::query()->create([
            'user_id' => $user->id,
            'showtime_id' => $scenario['showtime']->id,
            'seat_id' => $scenario['seats'][0]->id,
            'expires_at' => now()->addMinutes(2),
        ]);
        SeatHold::query()->create([
            'user_id' => $user->id,
            'showtime_id' => $scenario['showtime']->id,
            'seat_id' => $scenario['seats'][2]->id,
            'expires_at' => now()->addMinutes(8),
        ]);

        $summary = $service->summary($user, $scenario['showtime']);
        $warning = $service->seatsAboutToExpire($user, $scenario['showtime'], 2);

        $this->assertSame(2, $summary['count']);
        $this->assertTrue($summary['is_active']);
        $this->assertSame(1, count($warning));
        $this->assertSame((int) $scenario['seats'][0]->id, $warning[0]['seat_id']);
        $this->assertSame(2, $service->activeHoldCount($user, $scenario['showtime']));
    }
}
