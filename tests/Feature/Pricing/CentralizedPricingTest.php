<?php

namespace Tests\Feature\Pricing;

use App\Exceptions\PricingConfigurationException;
use App\Models\Cinema;
use App\Models\CinemaPricingRule;
use App\Models\Room;
use App\Models\Showtime;
use App\Services\TicketPricingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralizedPricingTest extends TestCase
{
    use RefreshDatabase;

    private Cinema $cinema;

    private Room $room;

    private Showtime $showtime;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cinema = Cinema::factory()->create(['timezone' => 'Asia/Ho_Chi_Minh']);
        $this->room = Room::factory()->create(['cinema_id' => $this->cinema->id, 'room_type' => '3D']);
        $this->showtime = new Showtime([
            'cinema_id' => $this->cinema->id, 'room_id' => $this->room->id,
            'show_date' => '2030-09-01', 'show_time' => '19:00:00',
        ]);
        $this->showtime->setRelation('cinema', $this->cinema);
        $this->showtime->setRelation('room', $this->room);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2029-01-01 12:00:00', 'Asia/Ho_Chi_Minh'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_specificity_priority_and_stable_id_choose_exactly_one_base(): void
    {
        $this->rule('global', 'base', 50_000, ['priority' => 999]);
        $this->rule('branch', 'base', 60_000, ['cinema_id' => $this->cinema->id]);
        $first = $this->rule('room first', 'base', 70_000, ['cinema_id' => $this->cinema->id, 'room_id' => $this->room->id, 'priority' => 10]);
        $this->rule('room second', 'base', 90_000, ['cinema_id' => $this->cinema->id, 'room_id' => $this->room->id, 'priority' => 10]);

        $price = app(TicketPricingService::class)->calculate($this->showtime, 'normal', false);
        $this->assertSame(70_000, $price->finalAmount);
        $this->assertSame($first->name, $price->baseRuleName);
    }

    public function test_inactive_future_and_exact_end_boundary_rules_are_ignored(): void
    {
        $this->rule('valid', 'base', 55_000);
        $this->rule('inactive', 'base', 90_000, ['status' => 'inactive', 'priority' => 100]);
        $this->rule('future', 'base', 90_000, ['starts_at' => '2031-01-01', 'priority' => 100]);
        $this->rule('ended', 'base', 90_000, ['ends_at' => CarbonImmutable::now(), 'priority' => 100]);
        $this->assertSame(55_000, app(TicketPricingService::class)->calculate($this->showtime, 'normal', false)->finalAmount);
    }

    public function test_categories_do_not_double_charge_and_holiday_replaces_weekend_by_default(): void
    {
        $this->rule('base', 'base', 50_000);
        $this->rule('vip low', 'seat_type', 10_000, ['seat_type' => 'vip']);
        $this->rule('vip winner', 'seat_type', 20_000, ['seat_type' => 'vip', 'priority' => 20]);
        $this->rule('weekend', 'weekend', 5_000, ['days_of_week' => [6, 7]]);
        $this->rule('holiday', 'holiday', 8_000, ['date_start' => '2030-09-01', 'date_end' => '2030-09-01']);
        $price = app(TicketPricingService::class)->calculate($this->showtime, 'vip', false);
        $this->assertSame(78_000, $price->finalAmount);
        $this->assertSame(['seat_type', 'holiday'], array_column($price->surcharges, 'type'));
    }

    public function test_room_format_cross_midnight_window_and_adjustments_are_server_derived(): void
    {
        $this->showtime->show_time = '23:30:00';
        $this->rule('base', 'base', 50_000);
        $this->rule('3D', 'room_type', 10_000, ['room_type' => '3D']);
        $this->rule('late', 'time_window', 7_000, ['time_start' => '22:00', 'time_end' => '02:00']);
        $this->rule('branch', 'cinema_adjustment', 3_000, ['cinema_id' => $this->cinema->id]);
        $this->rule('room', 'room_adjustment', 2_000, ['cinema_id' => $this->cinema->id, 'room_id' => $this->room->id]);
        $this->assertSame(72_000, app(TicketPricingService::class)->calculate($this->showtime, 'normal', false)->finalAmount);
    }

    public function test_no_base_rule_is_rejected_safely(): void
    {
        $this->expectException(PricingConfigurationException::class);
        app(TicketPricingService::class)->calculate($this->showtime, 'normal', false);
    }

    private function rule(string $name, string $type, int $amount, array $attributes = []): CinemaPricingRule
    {
        return CinemaPricingRule::query()->create([
            'name' => $name, 'rule_type' => $type, 'amount_vnd' => $amount,
            'priority' => 0, 'status' => 'active', ...$attributes,
        ]);
    }
}
