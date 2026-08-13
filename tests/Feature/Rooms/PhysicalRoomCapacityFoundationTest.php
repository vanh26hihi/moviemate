<?php

namespace Tests\Feature\Rooms;

use App\Models\Cinema;
use App\Models\Movie;
use App\Models\PresentationFormat;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\RoomLayoutCell;
use App\Models\RoomLayoutTemplate;
use App\Models\RoomLayoutTemplateCell;
use App\Models\Seat;
use App\Models\SeatIncident;
use App\Models\SeatIncidentSeat;
use App\Models\Showtime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PhysicalRoomCapacityFoundationTest extends TestCase
{
    use RefreshDatabase;

    private PresentationFormat $format;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        app()->setLocale('vi');
        $this->format = PresentationFormat::query()->create([
            'code' => 'PHASE6B_2D',
            'name' => '2D Phase 6B',
            'is_active' => true,
            'sort_order' => 10,
        ]);
    }

    public function test_schema_keeps_exact_nullable_dimensions_and_removes_ambiguous_capacity_and_area_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('rooms', ['id', 'cinema_id', 'room_type_id', 'width_mm', 'length_mm']));
        $this->assertFalse(Schema::hasColumn('rooms', 'total_seats'));
        $this->assertFalse(Schema::hasColumn('rooms', 'area_mm2'));
        $this->assertFalse(Schema::hasColumn('rooms', 'area_m2'));

        $columns = collect(Schema::getColumns('rooms'))->keyBy('name');
        $this->assertStringContainsString('int', strtolower((string) $columns['width_mm']['type_name']));
        $this->assertStringContainsString('int', strtolower((string) $columns['length_mm']['type_name']));
        $this->assertTrue($columns['width_mm']['nullable']);
        $this->assertTrue($columns['length_mm']['nullable']);
    }

    public function test_area_is_exact_derived_and_cannot_become_stale(): void
    {
        $room = Room::factory()->create(['width_mm' => 7_500, 'length_mm' => 10_000]);

        $this->assertSame(75_000_000, $room->areaMm2());
        $this->assertSame('7,50', $room->formattedWidthMeters());
        $this->assertSame('10,00', $room->formattedLengthMeters());
        $this->assertSame('75,00', $room->formattedAreaM2());
        $this->assertSame('7.5', $room->widthMetersForInput());

        $room->update(['width_mm' => 8_250]);
        $this->assertSame(82_500_000, $room->fresh()->areaMm2());
        $this->assertSame('82,50', $room->fresh()->formattedAreaM2());
    }

    public function test_active_create_requires_complete_valid_exact_meter_dimensions(): void
    {
        $manager = $this->userWithRole('manager');
        $valid = $this->roomPayload('DIM-OK', 'active', '7.5', '10.001');

        $response = $this->actingAs($manager)->post(route('admin.rooms.store'), $valid);
        $room = Room::query()->where('code', 'DIM-OK')->sole();
        $response->assertRedirect(route('admin.rooms.layout.show', $room));
        $this->assertSame(7_500, $room->width_mm);
        $this->assertSame(10_001, $room->length_mm);

        foreach ([
            'missing-width' => [null, '10', 'width_mm'],
            'missing-length' => ['8', null, 'length_mm'],
            'both-missing' => [null, null, 'width_mm'],
            'zero' => ['0', '10', 'width_mm'],
            'negative' => ['-1', '10', 'width_mm'],
            'non-numeric' => ['wide', '10', 'width_mm'],
            'comma-decimal' => ['7,5', '10', 'width_mm'],
            'sub-millimeter' => ['7.5001', '10', 'width_mm'],
            'technical-overflow' => ['3000000.001', '10', 'width_mm'],
        ] as $case => [$width, $length, $errorKey]) {
            $payload = $this->roomPayload('BAD-'.strtoupper(substr(md5($case), 0, 8)), 'active', $width, $length);
            $this->post(route('admin.rooms.store'), $payload)->assertSessionHasErrors($errorKey);
        }
    }

    public function test_inactive_rooms_allow_only_both_null_or_both_valid_dimensions(): void
    {
        $manager = $this->userWithRole('manager');
        $this->actingAs($manager)
            ->post(route('admin.rooms.store'), $this->roomPayload('INCOMPLETE', 'inactive', null, null))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('rooms', ['code' => 'INCOMPLETE', 'width_mm' => null, 'length_mm' => null]);

        $this->post(route('admin.rooms.store'), $this->roomPayload('INACTIVE-DIM', 'inactive', '8', '12'))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('rooms', ['code' => 'INACTIVE-DIM', 'width_mm' => 8_000, 'length_mm' => 12_000]);

        $this->post(route('admin.rooms.store'), $this->roomPayload('PARTIAL-W', 'inactive', '8', null))
            ->assertSessionHasErrors('length_mm');
        $this->post(route('admin.rooms.store'), $this->roomPayload('PARTIAL-L', 'inactive', null, '12'))
            ->assertSessionHasErrors('width_mm');
    }

    public function test_activation_and_updates_enforce_dimensions_server_side(): void
    {
        $manager = $this->userWithRole('manager');
        $room = Room::factory()->inactiveIncomplete()->create();
        $room->presentationCapabilities()->attach($this->format);

        $this->actingAs($manager)->patch(route('admin.rooms.status.update', $room), ['status' => 'active'])
            ->assertSessionHasErrors('status');
        $this->assertSame('inactive', $room->fresh()->status);

        $this->put(route('admin.rooms.update', $room), $this->updatePayload($room, 'inactive', '8', '10'))
            ->assertRedirect(route('admin.rooms.show', $room));
        $this->patch(route('admin.rooms.status.update', $room), ['status' => 'active'])->assertSessionHas('success');
        $this->assertSame('active', $room->fresh()->status);

        $this->put(route('admin.rooms.update', $room), $this->updatePayload($room, 'active', null, null))
            ->assertSessionHasErrors(['width_mm', 'length_mm']);
        $this->assertTrue($room->fresh()->hasCompletePhysicalDimensions());

        $this->put(route('admin.rooms.update', $room), $this->updatePayload($room, 'inactive', null, null))
            ->assertRedirect(route('admin.rooms.show', $room));
        $this->assertNull($room->fresh()->width_mm);
        $this->assertNull($room->fresh()->length_mm);
    }

    public function test_dimension_update_is_audited_and_independent_of_grid_seats_and_showtime_layout(): void
    {
        $manager = $this->userWithRole('manager');
        $room = Room::factory()->create(['width_mm' => 8_000, 'length_mm' => 12_000]);
        $room->presentationCapabilities()->attach($this->format);
        [$layout, $seats] = $this->publishedLayout($room, 8, 12);
        $movie = Movie::query()->create(['title' => 'Physical independence', 'slug' => 'physical-independence']);
        $showtime = Showtime::query()->create([
            'movie_id' => $movie->id,
            'cinema_id' => $room->cinema_id,
            'room_id' => $room->id,
            'presentation_format_id' => $this->format->id,
            'room_layout_id' => $layout->id,
            'show_date' => now()->addDay()->toDateString(),
            'show_time' => '20:00:00',
            'price' => 80_000,
            'status' => 'active',
        ]);
        $cellSnapshot = $layout->cells()->orderBy('id')->get()->map->only(['id', 'x_position', 'y_position', 'cell_type', 'seat_id'])->all();
        $seatSnapshot = $seats->map->only(['id', 'row', 'number', 'seat_code', 'type', 'status'])->all();

        $this->actingAs($manager)->put(route('admin.rooms.update', $room), $this->updatePayload($room, 'active', '10', '15'))
            ->assertRedirect(route('admin.rooms.show', $room));

        $this->assertSame([8, 12], [$layout->fresh()->rows, $layout->fresh()->columns]);
        $this->assertSame($cellSnapshot, $layout->cells()->orderBy('id')->get()->map->only(['id', 'x_position', 'y_position', 'cell_type', 'seat_id'])->all());
        $this->assertSame($seatSnapshot, Seat::query()->whereIn('id', $seats->pluck('id'))->orderBy('id')->get()->map->only(['id', 'row', 'number', 'seat_code', 'type', 'status'])->all());
        $this->assertSame($layout->id, $showtime->fresh()->room_layout_id);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'room.physical_dimensions_updated',
            'subject_id' => (string) $room->id,
        ]);
    }

    public function test_physical_operational_pricing_and_incident_counts_remain_distinct(): void
    {
        $room = Room::factory()->create();
        [$layout, $seats] = $this->publishedLayout($room, 1, 3);
        $seats[2]->update(['status' => 'maintenance']);

        $physicalCount = $layout->cells()->where('cell_type', 'seat')->count();
        $operationalCount = $layout->cells()->where('cell_type', 'seat')
            ->whereHas('seat', fn ($query) => $query->where('status', 'active'))->count();
        $pricingUnits = $seats->where('type', '!=', 'couple')->count()
            + $seats->where('type', 'couple')->pluck('pair_code')->unique()->count();

        $this->assertSame(3, $physicalCount);
        $this->assertSame(2, $operationalCount);
        $this->assertSame(2, $pricingUnits);

        $incident = SeatIncident::query()->create([
            'cinema_id' => $room->cinema_id,
            'room_id' => $room->id,
            'status' => 'open',
            'reason' => 'seat_broken',
        ]);
        SeatIncidentSeat::query()->create([
            'seat_incident_id' => $incident->id,
            'seat_id' => $seats[0]->id,
            'active_lock_key' => SeatIncidentSeat::ACTIVE_LOCK_KEY,
        ]);

        $this->assertSame(3, $layout->cells()->where('cell_type', 'seat')->count());
        $this->assertSame(3, $layout->fresh()->cells()->count());
    }

    public function test_maintenance_does_not_reduce_ten_position_physical_count(): void
    {
        $room = Room::factory()->create();
        $layout = RoomLayout::query()->create([
            'room_id' => $room->id,
            'version' => 1,
            'name' => 'Ten physical positions',
            'rows' => 1,
            'columns' => 10,
            'screen_position' => 'top',
            'status' => 'draft',
        ]);

        foreach (range(1, 10) as $number) {
            $seat = Seat::query()->create([
                'room_id' => $room->id,
                'row' => 'A',
                'number' => $number,
                'seat_code' => 'A'.$number,
                'type' => 'normal',
                'status' => $number === 1 ? 'maintenance' : 'active',
            ]);
            RoomLayoutCell::query()->create([
                'room_layout_id' => $layout->id,
                'x_position' => $number,
                'y_position' => 1,
                'cell_type' => 'seat',
                'seat_id' => $seat->id,
            ]);
        }
        $layout->update(['status' => 'published', 'published_at' => now()]);

        $this->assertSame(10, $layout->cells()->where('cell_type', 'seat')->count());
        $this->assertSame(9, $layout->cells()->where('cell_type', 'seat')
            ->whereHas('seat', fn ($query) => $query->where('status', 'active'))->count());
    }

    public function test_room_pages_separate_physical_dimensions_logical_grid_and_physical_seat_count(): void
    {
        $admin = $this->userWithRole('admin');
        $room = Room::factory()->create(['width_mm' => 7_500, 'length_mm' => 10_000]);
        $room->presentationCapabilities()->attach($this->format);
        $this->publishedLayout($room, 1, 3);

        $this->actingAs($admin)->get(route('admin.rooms.index'))
            ->assertOk()
            ->assertSee('Sức chứa vật lý')
            ->assertSee('Lưới logic: 1 hàng × 3 cột');

        $this->get(route('admin.rooms.show', $room))
            ->assertOk()
            ->assertSee('Kích thước phòng')
            ->assertSee('7,50 m × 10,00 m')
            ->assertSee('Diện tích mặt bằng')
            ->assertSee('75,00 m²')
            ->assertSee('Lưới logic')
            ->assertSee('1 hàng × 3 cột')
            ->assertSee('Sức chứa vật lý');
    }

    public function test_no_layout_room_still_has_area_and_never_synthesizes_capacity_from_it(): void
    {
        $room = Room::factory()->create(['width_mm' => 20_000, 'length_mm' => 30_000]);

        $this->actingAs($this->userWithRole('admin'))->get(route('admin.rooms.show', $room))
            ->assertOk()
            ->assertSee('20,00 m × 30,00 m')
            ->assertSee('600,00 m²')
            ->assertSee('Chưa có sơ đồ ghế đã phát hành');

        $this->assertNull($room->latestPublishedLayout);
    }

    public function test_template_couple_pair_is_three_physical_positions_but_two_pricing_units(): void
    {
        $template = RoomLayoutTemplate::query()->create([
            'code' => 'PHASE6B_COUNT',
            'name' => 'Phase 6B count',
            'rows' => 1,
            'columns' => 3,
            'screen_position' => 'top',
            'status' => RoomLayoutTemplate::STATUS_ACTIVE,
        ]);
        foreach ([
            [1, 'normal', 'A1', 'A1', null],
            [2, 'couple', 'A2', 'PAIR-1', 'PAIR-1'],
            [3, 'couple', 'A3', 'PAIR-1', 'PAIR-1'],
        ] as [$x, $type, $label, $unit, $pair]) {
            RoomLayoutTemplateCell::query()->create([
                'room_layout_template_id' => $template->id,
                'x_position' => $x,
                'y_position' => 1,
                'cell_type' => 'seat',
                'seat_type' => $type,
                'seat_label' => $label,
                'seat_unit_key' => $unit,
                'pair_key' => $pair,
            ]);
        }

        $this->actingAs($this->userWithRole('admin'))->get(route('admin.layout-templates.show', $template))
            ->assertOk()
            ->assertSee('Vị trí ghế vật lý')
            ->assertSee('3 vị trí')
            ->assertSee('Đơn vị tính giá')
            ->assertSeeInOrder(['Đơn vị tính giá', '2', 'đơn vị']);
    }

    public function test_manager_cannot_update_physical_dimensions_outside_assigned_cinema(): void
    {
        $manager = $this->userWithRole('manager');
        $foreignCinema = Cinema::factory()->create(['is_primary' => false]);
        $room = Room::factory()->create(['cinema_id' => $foreignCinema->id, 'width_mm' => 8_000, 'length_mm' => 10_000]);
        $room->presentationCapabilities()->attach($this->format);

        $this->actingAs($manager)->put(route('admin.rooms.update', $room), $this->updatePayload($room, 'active', '9', '11'))
            ->assertNotFound();
        $this->assertSame([8_000, 10_000], [$room->fresh()->width_mm, $room->fresh()->length_mm]);
    }

    public function test_room_list_and_detail_capacity_queries_are_bounded(): void
    {
        $admin = $this->userWithRole('admin');
        $first = Room::factory()->create();
        $this->publishedLayout($first, 1, 3);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->actingAs($admin)->get(route('admin.rooms.index'))->assertOk();
        $oneRoomQueries = count(DB::getQueryLog());

        foreach (range(2, 10) as $index) {
            $room = Room::factory()->create(['code' => 'QC'.str_pad((string) $index, 2, '0', STR_PAD_LEFT)]);
            $this->publishedLayout($room, 1, 3);
        }

        DB::flushQueryLog();
        $this->get(route('admin.rooms.index'))->assertOk();
        $tenRoomQueries = count(DB::getQueryLog());
        DB::flushQueryLog();
        $this->get(route('admin.rooms.show', $first))->assertOk();
        $detailQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual($oneRoomQueries + 1, $tenRoomQueries);
        $this->assertLessThanOrEqual(20, $detailQueries);
        fwrite(STDOUT, "PHASE6B_QUERY_COUNTS one={$oneRoomQueries} ten={$tenRoomQueries} detail={$detailQueries}\n");
    }

    /** @return array<string, mixed> */
    private function roomPayload(string $code, string $status, ?string $width, ?string $length): array
    {
        $payload = [
            'code' => $code,
            'name' => 'Phòng '.$code,
            'room_type' => '2D',
            'status' => $status,
            'width_m' => $width,
            'length_m' => $length,
        ];

        if ($status === 'active') {
            $payload['presentation_format_ids'] = [$this->format->id];
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function updatePayload(Room $room, string $status, ?string $width, ?string $length): array
    {
        return [
            'code' => $room->code,
            'name' => $room->name,
            'room_type' => $room->room_type,
            'status' => $status,
            'width_m' => $width,
            'length_m' => $length,
            'presentation_format_ids' => $room->presentationCapabilities()->pluck('presentation_formats.id')->all(),
        ];
    }

    /** @return array{RoomLayout, Collection<int, Seat>} */
    private function publishedLayout(Room $room, int $rows, int $columns): array
    {
        $layout = RoomLayout::query()->create([
            'room_id' => $room->id,
            'version' => 1,
            'name' => 'Phase 6B layout',
            'rows' => $rows,
            'columns' => $columns,
            'screen_position' => 'top',
            'status' => 'draft',
        ]);
        $seats = collect([
            Seat::query()->create([
                'room_id' => $room->id, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1',
                'type' => 'normal', 'status' => 'active',
            ]),
            Seat::query()->create([
                'room_id' => $room->id, 'row' => 'A', 'number' => 2, 'seat_code' => 'A2',
                'type' => 'couple', 'pair_code' => 'PAIR-1', 'pair_position' => 'left', 'status' => 'active',
            ]),
            Seat::query()->create([
                'room_id' => $room->id, 'row' => 'A', 'number' => 3, 'seat_code' => 'A3',
                'type' => 'couple', 'pair_code' => 'PAIR-1', 'pair_position' => 'right', 'status' => 'active',
            ]),
        ]);
        foreach ($seats as $index => $seat) {
            RoomLayoutCell::query()->create([
                'room_layout_id' => $layout->id,
                'x_position' => $index + 1,
                'y_position' => 1,
                'cell_type' => 'seat',
                'seat_id' => $seat->id,
            ]);
        }
        $layout->update(['status' => 'published', 'published_at' => now()]);

        return [$layout->fresh(), $seats];
    }
}
