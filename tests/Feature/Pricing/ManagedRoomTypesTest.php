<?php

namespace Tests\Feature\Pricing;

use App\Models\Cinema;
use App\Models\CinemaPricingRule;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Showtime;
use App\Services\TicketPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ManagedRoomTypesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_pricing_rule_codes_and_seat_values_have_vietnamese_labels(): void
    {
        $response = $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.pricing-rules.create'))
            ->assertOk();

        foreach (CinemaPricingRule::TYPE_LABELS as $code => $label) {
            $response->assertSee('value="'.$code.'"', false)->assertSee($label);
        }
        foreach (['Ghế thường', 'Ghế VIP', 'Ghế đôi'] as $label) {
            $response->assertSee($label);
        }
    }

    public function test_admin_can_manage_screenx_and_manager_can_only_read_catalog(): void
    {
        $admin = $this->userWithRole('admin');
        $manager = $this->userWithRole('manager');

        $this->actingAs($admin)->postJson(route('admin.room-types.store'), [
            'name' => 'ScreenX', 'code' => 'screenx', 'description' => 'Ba mặt chiếu',
            'is_active' => '1', 'sort_order' => 40,
        ])->assertCreated()->assertJsonPath('room_type.code', 'SCREENX')->assertJsonPath('room_type.name', 'ScreenX');

        $type = RoomType::query()->where('code', 'SCREENX')->sole();
        $this->assertDatabaseHas('activity_logs', ['action' => 'room_type.created', 'subject_id' => (string) $type->id]);
        $this->actingAs($manager)->get(route('admin.room-types.index'))->assertOk()->assertSee('ScreenX');
        $this->actingAs($manager)->get(route('admin.room-types.create'))->assertForbidden();
        $this->actingAs($manager)->post(route('admin.room-types.store'), [
            'name' => 'Không được tạo', 'code' => 'DENIED', 'is_active' => 1,
        ])->assertForbidden();
        $this->actingAs($this->userWithRole('staff'))->get(route('admin.room-types.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('user'))->get(route('admin.room-types.index'))->assertForbidden();

        $this->actingAs($admin)->put(route('admin.room-types.update', $type), [
            'name' => 'ScreenX Premium', 'code' => 'SCREENX', 'is_active' => 1, 'sort_order' => 45,
        ])->assertRedirect(route('admin.room-types.index'));
        $this->assertSame('ScreenX Premium', $type->fresh()->name);

        $this->actingAs($admin)->patch(route('admin.room-types.status', $type), ['is_active' => 0])->assertSessionHas('success');
        $this->assertFalse($type->fresh()->is_active);
        $this->actingAs($admin)->patch(route('admin.room-types.status', $type), ['is_active' => 1])->assertSessionHas('success');
        $this->assertTrue($type->fresh()->is_active);
    }

    public function test_dynamic_type_can_be_used_by_room_and_pricing_and_archive_preserves_history(): void
    {
        $admin = $this->userWithRole('admin');
        $type = RoomType::query()->create(['code' => 'SCREENX', 'name' => 'ScreenX', 'is_active' => true]);
        $cinema = Cinema::query()->where('status', 'active')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.rooms.store'), [
            'cinema_id' => $cinema->id, 'code' => 'SX1', 'name' => 'Phòng ScreenX',
            'room_type' => 'SCREENX', 'status' => 'inactive',
        ])->assertSessionHasNoErrors();
        $room = Room::query()->where('code', 'SX1')->sole();
        $this->assertSame($type->id, $room->room_type_id);

        $this->actingAs($admin)->post(route('admin.pricing-rules.store'), $this->rulePayload([
            'name' => 'Phụ thu ScreenX', 'rule_type' => 'room_type', 'room_type' => 'SCREENX',
        ]))->assertSessionHasNoErrors();

        $this->actingAs($admin)->patch(route('admin.room-types.status', $type), ['is_active' => 0]);
        $this->actingAs($admin)->get(route('admin.rooms.edit', $room))->assertOk()->assertSee('ScreenX')->assertSee('Đã ngừng sử dụng');
        $this->actingAs($admin)->get(route('admin.rooms.create'))->assertOk()->assertDontSee('value="SCREENX"', false);
        $this->actingAs($admin)->get(route('admin.pricing-rules.index'))->assertOk()->assertSee('ScreenX');

        $this->actingAs($admin)->put(route('admin.room-types.update', $type), [
            'name' => 'ScreenX', 'code' => 'SCREENX_NEW', 'is_active' => 0,
        ])->assertSessionHasErrors('code');
        $this->assertSame('SCREENX', $type->fresh()->code);
    }

    public function test_duplicate_codes_are_rejected_after_normalization(): void
    {
        $admin = $this->userWithRole('admin');
        RoomType::query()->create(['code' => 'DOLBY_CINEMA', 'name' => 'Dolby Cinema', 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.room-types.store'), [
            'name' => 'Bản sao', 'code' => 'dolby cinema', 'is_active' => 1,
        ])->assertSessionHasErrors('code');
    }

    public function test_dolby_cinema_pricing_is_generic_and_not_hardcoded(): void
    {
        $cinema = Cinema::factory()->create(['timezone' => 'Asia/Ho_Chi_Minh']);
        $type = RoomType::query()->create(['code' => 'DOLBY_CINEMA', 'name' => 'Dolby Cinema', 'is_active' => true]);
        $room = Room::factory()->create([
            'cinema_id' => $cinema->id, 'room_type_id' => $type->id, 'room_type' => $type->code,
        ]);
        CinemaPricingRule::query()->create($this->rulePayload(['name' => 'Giá chuẩn', 'amount_vnd' => 80_000]));
        CinemaPricingRule::query()->create($this->rulePayload([
            'name' => 'Dolby', 'rule_type' => 'room_type', 'room_type' => 'DOLBY_CINEMA', 'amount_vnd' => 40_000,
        ]));
        $showtime = new Showtime([
            'cinema_id' => $cinema->id, 'room_id' => $room->id,
            'show_date' => now()->addMonth()->toDateString(), 'show_time' => '19:00:00',
        ]);
        $showtime->setRelation('cinema', $cinema)->setRelation('room', $room);

        $this->assertSame(120_000, app(TicketPricingService::class)->calculate($showtime, 'normal', false)->finalAmount);
    }

    public function test_canonical_2d_3d_and_imax_types_remain_available_to_generic_pricing(): void
    {
        $cinema = Cinema::factory()->create(['timezone' => 'Asia/Ho_Chi_Minh']);
        CinemaPricingRule::query()->create($this->rulePayload(['name' => 'Giá chuẩn', 'amount_vnd' => 80_000]));

        foreach (['2D' => 5_000, '3D' => 15_000, 'IMAX' => 30_000] as $code => $surcharge) {
            $type = RoomType::query()->where('code', $code)->sole();
            $room = Room::factory()->create([
                'cinema_id' => $cinema->id, 'room_type_id' => $type->id, 'room_type' => $code,
            ]);
            CinemaPricingRule::query()->create($this->rulePayload([
                'name' => 'Phụ thu '.$code, 'rule_type' => 'room_type',
                'room_type' => $code, 'amount_vnd' => $surcharge,
            ]));
            $showtime = new Showtime([
                'cinema_id' => $cinema->id, 'room_id' => $room->id,
                'show_date' => now()->addMonth()->toDateString(), 'show_time' => '19:00:00',
            ]);
            $showtime->setRelation('cinema', $cinema)->setRelation('room', $room);

            $this->assertSame(80_000 + $surcharge, app(TicketPricingService::class)->calculate($showtime, 'normal', false)->finalAmount);
        }
    }

    public function test_representative_admin_pages_stay_within_query_budget(): void
    {
        $admin = $this->userWithRole('admin');
        $room = Room::factory()->create();
        $pricingRule = CinemaPricingRule::query()->create($this->rulePayload());
        DB::enableQueryLog();

        foreach ([
            route('admin.pricing-rules.index'),
            route('admin.pricing-rules.create'),
            route('admin.pricing-rules.edit', $pricingRule),
            route('admin.rooms.create'),
            route('admin.rooms.edit', $room),
            route('admin.room-types.index'),
        ] as $url) {
            DB::flushQueryLog();
            $this->actingAs($admin)->get($url)->assertOk();
            $queryCount = count(DB::getQueryLog());
            $this->assertLessThanOrEqual(30, $queryCount, "Query budget exceeded for {$url}");
        }
        DB::disableQueryLog();
    }

    public function test_pricing_edit_keeps_an_archived_room_type_selected_and_available(): void
    {
        $admin = $this->userWithRole('admin');
        $type = RoomType::query()->create([
            'code' => 'SCREENX_QA',
            'name' => 'ScreenX QA',
            'is_active' => false,
        ]);
        $rule = CinemaPricingRule::query()->create($this->rulePayload([
            'name' => 'Phụ thu ScreenX QA',
            'rule_type' => 'room_type',
            'room_type' => $type->code,
        ]));

        $response = $this->actingAs($admin)
            ->get(route('admin.pricing-rules.edit', $rule))
            ->assertOk();

        $response->assertSee('value="SCREENX_QA" selected', false)
            ->assertSee('ScreenX QA')
            ->assertSee('Đã ngừng sử dụng')
            ->assertSee('data-room-type-modal', false);
    }

    public function test_room_type_modal_has_accessible_opaque_autocomplete_safe_contract_and_is_reused(): void
    {
        $admin = $this->userWithRole('admin');
        $room = Room::factory()->create();
        $pricingRule = CinemaPricingRule::query()->create($this->rulePayload());

        foreach ([
            route('admin.pricing-rules.create'),
            route('admin.pricing-rules.edit', $pricingRule),
            route('admin.rooms.create'),
            route('admin.rooms.edit', $room),
        ] as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('role="dialog"', $html);
            $this->assertStringContainsString('aria-modal="true"', $html);
            $this->assertStringContainsString('aria-labelledby="add-room-type-', $html);
            $this->assertStringContainsString('aria-describedby="add-room-type-', $html);
            $this->assertStringContainsString('data-room-type-modal', $html);
            $this->assertStringContainsString('data-room-type-modal-id="add-room-type-', $html);
            $this->assertStringContainsString('room-type-modal-overlay', $html);
            $this->assertStringContainsString('room-type-modal-panel', $html);
            $this->assertStringContainsString('fixed inset-0 hidden place-items-center', $html);
            $this->assertStringContainsString('aria-label="Đóng"', $html);
            $this->assertStringContainsString('autocomplete="off"', $html);
            $this->assertStringContainsString('name="room_type_display_name"', $html);
            $this->assertStringContainsString('name="room_type_code"', $html);
            $this->assertStringContainsString('name="room_type_description"', $html);
            $this->assertStringContainsString('action="'.route('admin.room-types.store').'"', $html);
            $this->assertStringContainsString('name="_token"', $html);
            $this->assertStringContainsString('data-submit-loading', $html);
        }

        $pricingHtml = $this->actingAs($admin)->get(route('admin.pricing-rules.create'))->getContent();
        $pricingFormEnd = strpos($pricingHtml, '</form>', strpos($pricingHtml, 'data-pricing-rule-form'));
        $modalFormStart = strpos($pricingHtml, 'data-room-type-create-form');
        $this->assertIsInt($pricingFormEnd);
        $this->assertIsInt($modalFormStart);
        $this->assertGreaterThan($pricingFormEnd, $modalFormStart, 'Room-type form must be portaled outside the pricing form.');
    }

    public function test_pricing_form_exposes_all_conditional_disabled_state_contracts_without_engine_changes(): void
    {
        $admin = $this->userWithRole('admin');
        $response = $this->actingAs($admin)->get(route('admin.pricing-rules.create'))->assertOk();

        foreach (CinemaPricingRule::TYPES as $type) {
            $response->assertSee('value="'.$type.'"', false);
        }
        foreach (['seat_type', 'room_type', 'time_window', 'weekend', 'holiday'] as $type) {
            $response->assertSee('data-pricing-conditional="'.$type.'"', false);
        }
        $response->assertSee('data-pricing-highlight="cinema_adjustment"', false)
            ->assertSee('data-pricing-highlight="room_adjustment"', false)
            ->assertSee('data-pricing-amount-label', false)
            ->assertSee('Không áp dụng cho loại quy tắc này.');

        $javascript = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString('field.disabled = !relevant', $javascript);
        $this->assertStringContainsString("field.setAttribute('aria-disabled', String(!relevant))", $javascript);
        $this->assertStringContainsString("seat_type: 'seat_type'", $javascript);
        $this->assertStringContainsString("room_type: 'room_type'", $javascript);
        $this->assertStringContainsString("time_window: ['time_start', 'time_end']", $javascript);
        $this->assertStringContainsString("holiday: 'date_start'", $javascript);
        $this->assertStringContainsString('Mức phụ thu/điều chỉnh (VNĐ)', $javascript);
        $this->assertStringContainsString('genericFailureMessage', $javascript);
        $this->assertStringNotContainsString('data.message', $javascript);
        $this->assertStringNotContainsString('TicketPricingService', $javascript);
    }

    /** @param array<string, mixed> $overrides */
    private function rulePayload(array $overrides = []): array
    {
        return [
            'name' => 'Quy tắc kiểm thử', 'rule_type' => 'base', 'cinema_id' => null,
            'room_id' => null, 'seat_type' => null, 'room_type' => null,
            'amount_vnd' => 50_000, 'priority' => 0, 'status' => 'active',
            ...$overrides,
        ];
    }
}
