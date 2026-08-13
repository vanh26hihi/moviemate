<?php

namespace Tests\Feature\Seats;

use App\Models\Cinema;
use App\Models\CinemaPricingRule;
use App\Models\PresentationFormat;
use App\Models\Room;
use App\Models\Showtime;
use App\Services\CinemaContext;
use App\Services\RoomLayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoomLayoutAccessAndShowtimeTest extends TestCase
{
    use RefreshDatabase;

    private Cinema $cinema;

    private $rooms;

    private int $movieId;

    private int $presentationFormatId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->cinema = Cinema::query()->where('canonical_key', CinemaContext::CANONICAL_KEY)->firstOrFail();
        CinemaPricingRule::query()->create(['name' => 'Giá cơ bản layout test', 'rule_type' => 'base', 'cinema_id' => $this->cinema->id, 'amount_vnd' => 50_000, 'priority' => 100, 'status' => 'active']);
        CinemaPricingRule::query()->create(['name' => 'VIP layout test', 'rule_type' => 'seat_type', 'cinema_id' => $this->cinema->id, 'seat_type' => 'vip', 'amount_vnd' => 20_000, 'priority' => 100, 'status' => 'active']);
        foreach (['P01', 'P02', 'P03'] as $index => $code) {
            Room::query()->create([
                'cinema_id' => $this->cinema->id, 'code' => $code, 'name' => 'Phòng '.($index + 1),
                'room_type' => '2D', 'width_mm' => 8_000, 'length_mm' => 10_000, 'status' => 'active',
            ]);
        }
        $this->artisan('moviemate:rebuild-seat-layouts', ['--initialize-empty' => true])->assertSuccessful();
        $this->rooms = Room::query()->whereIn('code', ['P01', 'P02', 'P03'])->get()->keyBy('code');
        $this->movieId = DB::table('movies')->insertGetId([
            'title' => 'Dynamic Movie', 'slug' => 'dynamic-movie', 'duration' => 100,
            'age_rating' => 'P', 'status' => 'now_showing', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $format = PresentationFormat::query()->create([
            'code' => '2D', 'name' => '2D', 'is_active' => true, 'sort_order' => 10,
        ]);
        $this->presentationFormatId = $format->id;
        DB::table('movie_presentation_formats')->insert([
            'movie_id' => $this->movieId,
            'presentation_format_id' => $format->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->rooms->each(fn (Room $room) => $room->presentationCapabilities()->attach($format));
    }

    public function test_guest_customer_and_inactive_user_cannot_enter_editor(): void
    {
        $room = $this->rooms['P01'];
        $this->get(route('admin.rooms.layout.show', $room))->assertRedirect(route('login'));
        $this->actingAs($this->userWithRole('user'))->get(route('admin.rooms.layout.show', $room))->assertForbidden();
        $inactive = $this->userWithRole('admin', ['status' => 'inactive']);
        $this->actingAs($inactive)->get(route('admin.rooms.layout.show', $room))->assertRedirect(route('login'));
    }

    public function test_staff_can_preview_but_cannot_save_or_publish(): void
    {
        $room = $this->rooms['P01'];
        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->get(route('staff.rooms.layout.preview', $room))
            ->assertOk()->assertSee('P01')->assertSee('phiên bản 1');
        $this->actingAs($staff)->post(route('admin.rooms.layout.draft', $room))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.rooms.layout.publish', $room))->assertForbidden();
    }

    public function test_manager_and_admin_can_create_draft_and_publish(): void
    {
        $manager = $this->userWithRole('manager');
        $room = $this->rooms['P01'];
        $this->actingAs($manager)->get(route('admin.rooms.layout.show', $room))->assertOk();
        $this->actingAs($manager)->post(route('admin.rooms.layout.draft', $room))->assertRedirect();
        $this->actingAs($manager)->post(route('admin.rooms.layout.publish', $room))->assertRedirect();
        $this->assertDatabaseHas('room_layouts', ['room_id' => $room->id, 'version' => 2, 'status' => 'published']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'room_layout.published', 'actor_user_id' => $manager->id]);
        $this->assertSame(1, DB::table('activity_logs')->where('action', 'room_layout.published')->count());

        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->post(route('admin.rooms.layout.draft', $this->rooms['P02']))->assertRedirect();
        $this->assertDatabaseHas('room_layouts', ['room_id' => $this->rooms['P02']->id, 'version' => 2, 'status' => 'draft']);
    }

    public function test_three_room_previews_use_real_distinct_grids_and_irregular_empty_cells(): void
    {
        $staff = $this->userWithRole('staff');
        $p01 = $this->actingAs($staff)->get(route('staff.rooms.layout.preview', $this->rooms['P01']))->assertOk();
        $p02 = $this->actingAs($staff)->get(route('staff.rooms.layout.preview', $this->rooms['P02']))->assertOk();
        $p03 = $this->actingAs($staff)->get(route('staff.rooms.layout.preview', $this->rooms['P03']))->assertOk();

        $p01->assertSee('repeat(13', false)->assertSee('K1–K2')->assertSee('col-span-2', false)->assertSee('K12');
        $p02->assertSee('repeat(14', false)->assertSee('F6')->assertSee('Đang bảo trì');
        $p03->assertSee('repeat(13', false)->assertSee('104');
        $this->assertSame(113, $this->rooms['P03']->latestPublishedLayout()->first()->cells()->count());
    }

    public function test_editor_restores_invalid_draft_and_exposes_accessible_cell_errors(): void
    {
        $manager = $this->userWithRole('manager');
        $room = $this->rooms['P01'];
        $this->actingAs($manager)->post(route('admin.rooms.layout.draft', $room))->assertRedirect();
        $payload = [
            'name' => 'Bản nháp lỗi', 'rows' => 1, 'columns' => 2, 'screen_position' => 'top',
            'cells' => [[
                'kind' => 'couple', 'type' => 'couple', 'x' => 1, 'y' => 1,
                'row' => 'A', 'number' => 1, 'seat_code' => 'A1', 'status' => 'active',
                'pair_code' => 'A-INCOMPLETE', 'pair_position' => 'left',
            ]],
        ];

        $response = $this->actingAs($manager)
            ->from(route('admin.rooms.layout.show', $room))
            ->followingRedirects()
            ->patch(route('admin.rooms.layout.update', $room), ['layout' => json_encode($payload)]);

        $response->assertOk()
            ->assertSee('layoutServerErrors', false)
            ->assertSee('role="alert"', false)
            ->assertSee('Bản nháp lỗi')
            ->assertSee('A-INCOMPLETE')
            ->assertSee('aria-invalid', false)
            ->assertSee('ph-warning-octagon', false);
    }

    public function test_stable_seat_code_validation_message_is_rendered_once_without_error_flash(): void
    {
        $manager = $this->userWithRole('manager');
        $room = $this->rooms['P01'];
        $this->actingAs($manager)->post(route('admin.rooms.layout.draft', $room))->assertRedirect();
        $draft = $room->draftLayout()->with('cells.seat')->firstOrFail();
        $cells = $draft->cells->map(function ($cell): array {
            if ($cell->cell_type === 'aisle') {
                return ['kind' => 'aisle', 'x' => $cell->x_position, 'y' => $cell->y_position];
            }

            $seat = $cell->seat;

            return [
                'kind' => $seat->type, 'type' => $seat->type, 'seat_id' => $seat->id,
                'x' => $cell->x_position, 'y' => $cell->y_position,
                'row' => $seat->row, 'number' => $seat->seat_code === 'A1' ? 24 : $seat->number,
                'seat_code' => $seat->seat_code === 'A1' ? 'A24' : $seat->seat_code,
                'status' => $seat->status, 'pair_code' => $seat->pair_code,
                'pair_position' => $seat->pair_position,
            ];
        })->all();
        $payload = [
            'schema_version' => 3, 'name' => 'Bản nháp giữ nguyên khi lỗi',
            'rows' => $draft->rows, 'columns' => 24, 'screen_position' => $draft->screen_position,
            'cells' => $cells,
        ];
        $message = 'Không thể đổi mã ghế A1 thành A24. Mã ghế đã tạo phải được giữ ổn định.';

        $response = $this->actingAs($manager)
            ->from(route('admin.rooms.layout.show', $room))
            ->followingRedirects()
            ->patch(route('admin.rooms.layout.update', $room), ['layout' => json_encode($payload)])
            ->assertOk()
            ->assertSessionMissing('error')
            ->assertSee('Không thể hoàn tất thao tác với sơ đồ ghế.')
            ->assertSee('layoutServerErrors', false)
            ->assertSee('aria-invalid', false)
            ->assertSee('Bản nháp giữ nguyên khi lỗi')
            ->assertSee('A24');

        $text = preg_replace('/\s+/u', ' ', strip_tags($response->getContent())) ?? '';
        $this->assertSame(1, substr_count($text, $message));
        $this->assertSame(1, substr_count($text, 'Không thể hoàn tất thao tác với sơ đồ ghế.'));
        $this->assertDatabaseHas('seats', ['room_id' => $room->id, 'seat_code' => 'A1']);
        $this->assertDatabaseMissing('seats', ['room_id' => $room->id, 'seat_code' => 'A24']);
    }

    public function test_editor_exposes_freeform_canvas_row_modes_and_server_summary(): void
    {
        $manager = $this->userWithRole('manager');
        $room = $this->rooms['P01'];
        $this->actingAs($manager)->post(route('admin.rooms.layout.draft', $room))->assertRedirect();

        $this->actingAs($manager)->get(route('admin.rooms.layout.show', $room))
            ->assertOk()
            ->assertSee('Chiều rộng vùng thiết kế')
            ->assertSee('Mỗi hàng có thể sử dụng số ô khác nhau')
            ->assertSee('Mở rộng bên trái')
            ->assertSee('Mở rộng hàng')
            ->assertSee('Di chuyển hàng')
            ->assertSee('Thêm 2 ô bên trái')
            ->assertSee('Thêm hàng phía sau')
            ->assertSee('Hoàn tác thao tác gần nhất')
            ->assertSee('Tách ghế đôi')
            ->assertSee('data-tool="blocked"', false)
            ->assertSee('Vật cản cố định')
            ->assertSee('Tóm tắt sơ đồ do máy chủ tính toán')
            ->assertSee('expected_updated_at', false)
            ->assertSee('seat_id', false);
    }

    public function test_showtime_create_ignores_client_layout_id_and_assigns_latest_published(): void
    {
        $admin = $this->userWithRole('admin');
        $room = $this->rooms['P01'];
        $expectedLayout = $room->latestPublishedLayout()->first();
        $this->actingAs($admin)->post(route('admin.showtimes.store'), $this->showtimePayload($room, ['room_layout_id' => 999999]))
            ->assertRedirect(route('admin.showtimes.index'));

        $this->assertDatabaseHas('showtimes', [
            'room_id' => $room->id,
            'room_layout_id' => $expectedLayout->id,
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'showtime.created', 'actor_user_id' => $admin->id]);
    }

    public function test_room_without_published_layout_cannot_create_showtime_and_is_not_in_selector(): void
    {
        $room = Room::query()->create([
            'cinema_id' => $this->cinema->id, 'code' => 'P04', 'name' => 'No Layout',
            'room_type' => '2D', 'width_mm' => 8_000, 'length_mm' => 10_000, 'status' => 'active',
        ]);
        $room->presentationCapabilities()->attach($this->presentationFormatId);
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->get(route('admin.showtimes.create'))->assertOk()->assertDontSee('P04');
        $this->actingAs($admin)->post(route('admin.showtimes.store'), $this->showtimePayload($room))
            ->assertSessionHasErrors('room_id');
        $this->assertDatabaseMissing('showtimes', ['room_id' => $room->id]);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'showtime.created']);
    }

    public function test_showtime_update_keeps_layout_for_same_room_and_switches_when_room_changes(): void
    {
        $room = $this->rooms['P01'];
        $v1 = $room->latestPublishedLayout()->first();
        $showtime = $this->createShowtime($room, $v1->id);
        $service = app(RoomLayoutService::class);
        $v2 = $service->publish($service->clonePublishedToDraft($room));
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->put(route('admin.showtimes.update', $showtime), $this->showtimePayload($room, ['show_time' => '11:00']))->assertRedirect();
        $this->assertSame($v1->id, $showtime->fresh()->room_layout_id);

        $newRoom = $this->rooms['P02'];
        $this->actingAs($admin)->put(route('admin.showtimes.update', $showtime), $this->showtimePayload($newRoom, ['show_time' => '12:00']))->assertRedirect();
        $this->assertSame($newRoom->latestPublishedLayout()->first()->id, $showtime->fresh()->room_layout_id);
        $this->assertNotSame($v2->id, $showtime->fresh()->room_layout_id);
    }

    public function test_unused_room_with_layout_cannot_be_deleted_and_history_is_preserved(): void
    {
        $room = $this->rooms['P03'];
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->delete(route('admin.rooms.destroy', $room))
            ->assertStatus(409);

        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
        $this->assertDatabaseHas('room_layouts', ['room_id' => $room->id]);
        $this->assertDatabaseHas('seats', ['room_id' => $room->id]);
    }

    private function showtimePayload(Room $room, array $overrides = []): array
    {
        return [
            'movie_id' => $this->movieId, 'presentation_format_id' => $this->presentationFormatId, 'room_id' => $room->id,
            'show_date' => now()->addDays(5)->toDateString(), 'show_time' => '10:00',
            'price' => 50000, 'vip_price' => 70000, 'status' => 'active', ...$overrides,
        ];
    }

    private function createShowtime(Room $room, int $layoutId): Showtime
    {
        return Showtime::query()->create([
            'movie_id' => $this->movieId, 'cinema_id' => $this->cinema->id, 'room_id' => $room->id,
            'room_layout_id' => $layoutId, 'presentation_format_id' => $this->presentationFormatId,
            'show_date' => now()->addDays(5)->toDateString(), 'show_time' => '10:00:00',
            'price' => 50000, 'vip_price' => 70000, 'status' => 'active',
        ]);
    }
}
