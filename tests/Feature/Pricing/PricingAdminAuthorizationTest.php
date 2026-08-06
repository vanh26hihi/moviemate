<?php

namespace Tests\Feature\Pricing;

use App\Models\Cinema;
use App\Models\CinemaPricingRule;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingAdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Cinema $primary;

    private Cinema $other;

    private Room $primaryRoom;

    private Room $otherRoom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->primary = Cinema::query()->active()->primary()->firstOrFail();
        $this->other = Cinema::factory()->create(['status' => 'active', 'archived_at' => null, 'timezone' => 'Asia/Ho_Chi_Minh']);
        $this->primaryRoom = Room::factory()->create(['cinema_id' => $this->primary->id]);
        $this->otherRoom = Room::factory()->create(['cinema_id' => $this->other->id]);
    }

    public function test_admin_allowed_staff_denied_and_manager_cannot_create_global_rule(): void
    {
        $this->actingAs($this->userWithRole('admin'))->get(route('admin.pricing-rules.index'))
            ->assertOk()->assertSee('Bảng giá vé');
        $this->actingAs($this->userWithRole('staff'))->get(route('admin.pricing-rules.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('manager'))->post(route('admin.pricing-rules.store'), $this->payload(null))
            ->assertSessionHasErrors('cinema_id');
        $this->assertDatabaseCount('cinema_pricing_rules', 0);
    }

    public function test_manager_cannot_target_another_branch_room(): void
    {
        $manager = $this->userWithRole('manager');
        $this->actingAs($manager)->post(route('admin.pricing-rules.store'), $this->payload($this->primary->id, $this->otherRoom->id))
            ->assertSessionHasErrors('room_id');
    }

    public function test_manager_cannot_preview_another_branch(): void
    {
        $manager = $this->userWithRole('manager');
        $this->actingAs($manager)->post(route('admin.pricing-rules.preview'), [
            'cinema_id' => $this->other->id, 'room_id' => $this->otherRoom->id,
            'show_date' => '2030-01-01', 'show_time' => '19:00', 'seat_type' => 'normal',
        ])->assertNotFound();
    }

    public function test_preview_and_operating_hours_use_authorized_branch_and_activity_log(): void
    {
        CinemaPricingRule::query()->create([...$this->payload($this->primary->id), 'created_by_user_id' => null]);
        $manager = $this->userWithRole('manager');
        $this->actingAs($manager)->postJson(route('admin.pricing-rules.preview'), [
            'cinema_id' => $this->primary->id, 'room_id' => $this->primaryRoom->id,
            'show_date' => '2030-01-01', 'show_time' => '19:00', 'seat_type' => 'normal',
        ])->assertOk()->assertJsonPath('final_amount', 80_000);

        $hours = collect(range(1, 7))->map(fn (int $day): array => [
            'day_of_week' => $day, 'opens_at' => '08:00', 'latest_show_start_at' => '23:00', 'is_closed' => false,
        ])->all();
        $this->actingAs($manager)->patch(route('admin.cinemas.operating-hours.update', $this->primary), [
            'hours' => $hours, 'default_cleaning_buffer_minutes' => 20,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('cinema_operating_hours', 7);
        $this->assertDatabaseHas('activity_logs', ['action' => 'cinema.operating_hours_updated', 'actor_user_id' => $manager->id]);
        $this->actingAs($manager)->patch(route('admin.cinemas.operating-hours.update', $this->other), [
            'hours' => $hours, 'default_cleaning_buffer_minutes' => 20,
        ])->assertNotFound();
    }

    private function payload(?int $cinemaId, ?int $roomId = null): array
    {
        return [
            'name' => 'Giá cơ bản', 'rule_type' => 'base', 'cinema_id' => $cinemaId,
            'room_id' => $roomId, 'amount_vnd' => 80_000, 'priority' => 100,
            'stacks_with_weekend' => false, 'status' => 'active',
        ];
    }
}
