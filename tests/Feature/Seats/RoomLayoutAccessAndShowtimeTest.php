<?php

namespace Tests\Feature\Seats;

use App\Models\Cinema;
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->cinema = Cinema::query()->where('canonical_key', CinemaContext::CANONICAL_KEY)->firstOrFail();
        foreach (['P01', 'P02', 'P03'] as $index => $code) {
            Room::query()->create([
                'cinema_id' => $this->cinema->id, 'code' => $code, 'name' => 'Phòng '.($index + 1),
                'room_type' => '2D', 'total_seats' => 0, 'status' => 'active',
            ]);
        }
        $this->artisan('moviemate:rebuild-seat-layouts', ['--force' => true])->assertSuccessful();
        $this->rooms = Room::query()->whereIn('code', ['P01', 'P02', 'P03'])->get()->keyBy('code');
        $this->movieId = DB::table('movies')->insertGetId([
            'title' => 'Dynamic Movie', 'slug' => 'dynamic-movie', 'duration' => 100,
            'age_rating' => 'P', 'status' => 'now_showing', 'created_at' => now(), 'updated_at' => now(),
        ]);
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

        $p01->assertSee('repeat(13', false)->assertSee('K12');
        $p02->assertSee('repeat(14', false)->assertSee('F6')->assertSee('Đang bảo trì');
        $p03->assertSee('repeat(13', false)->assertSee('104');
        $this->assertSame(113, $this->rooms['P03']->latestPublishedLayout()->first()->cells()->count());
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
    }

    public function test_room_without_published_layout_cannot_create_showtime_and_is_not_in_selector(): void
    {
        $room = Room::query()->create([
            'cinema_id' => $this->cinema->id, 'code' => 'P04', 'name' => 'No Layout',
            'room_type' => '2D', 'total_seats' => 0, 'status' => 'active',
        ]);
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->get(route('admin.showtimes.create'))->assertOk()->assertDontSee('P04');
        $this->actingAs($admin)->post(route('admin.showtimes.store'), $this->showtimePayload($room))
            ->assertSessionHasErrors('room_id');
        $this->assertDatabaseMissing('showtimes', ['room_id' => $room->id]);
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

    public function test_unused_room_with_layout_can_be_deleted_in_dependency_order(): void
    {
        $room = $this->rooms['P03'];
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->delete(route('admin.rooms.destroy', $room))
            ->assertRedirect(route('admin.rooms.index'));

        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
        $this->assertDatabaseMissing('room_layouts', ['room_id' => $room->id]);
        $this->assertDatabaseMissing('seats', ['room_id' => $room->id]);
    }

    private function showtimePayload(Room $room, array $overrides = []): array
    {
        return [
            'movie_id' => $this->movieId, 'room_id' => $room->id,
            'show_date' => now()->addDays(5)->toDateString(), 'show_time' => '10:00',
            'price' => 50000, 'vip_price' => 70000, 'status' => 'active', ...$overrides,
        ];
    }

    private function createShowtime(Room $room, int $layoutId): Showtime
    {
        return Showtime::query()->create([
            'movie_id' => $this->movieId, 'cinema_id' => $this->cinema->id, 'room_id' => $room->id,
            'room_layout_id' => $layoutId, 'show_date' => now()->addDays(5)->toDateString(), 'show_time' => '10:00:00',
            'price' => 50000, 'vip_price' => 70000, 'status' => 'active',
        ]);
    }
}
