<?php

namespace Tests\Feature\Showtimes;

use App\Models\Cinema;
use App\Models\Room;
use Illuminate\Support\Facades\Schema;

class ShowtimeValidationUiAndRbacTest extends ShowtimeTestCase
{
    public function test_schedule_lookup_index_matches_candidate_query_prefix(): void
    {
        $index = collect(Schema::getIndexes('showtimes'))
            ->firstWhere('name', 'showtimes_room_schedule_lookup_index');

        $this->assertNotNull($index);
        $this->assertSame(['room_id', 'show_date', 'show_time', 'status'], $index['columns']);
        $this->assertFalse($index['unique']);
    }

    public function test_missing_movie_room_and_invalid_date_time_status_return_vietnamese_errors(): void
    {
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];
        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)->post(route('admin.showtimes.store'), $this->payload($movie, $room, [
            'movie_id' => 999999,
            'room_id' => 999999,
            'show_date' => '10/06/2030',
            'show_time' => '25:70',
            'status' => 'draft',
        ]));

        $response->assertSessionHasErrors(['movie_id', 'room_id', 'show_date', 'show_time', 'status']);
        $errors = session('errors')->getBag('default');
        $this->assertStringContainsString('không tồn tại', $errors->first('movie_id'));
        $this->assertStringContainsString('không tồn tại', $errors->first('room_id'));
        $this->assertStringContainsString('định dạng', $errors->first('show_date'));
        $this->assertStringContainsString('định dạng', $errors->first('show_time'));
        $this->assertStringContainsString('không hợp lệ', $errors->first('status'));
    }

    public function test_inactive_archive_legacy_and_no_layout_rooms_are_rejected(): void
    {
        $movie = $this->movie(90);
        $admin = $this->userWithRole('admin');
        $inactive = Room::query()->create([
            'cinema_id' => $this->cinema->id, 'code' => 'INACTIVE', 'name' => 'Inactive',
            'room_type' => '2D', 'total_seats' => 0, 'status' => 'inactive',
        ]);
        $archive = Room::query()->create([
            'cinema_id' => $this->cinema->id, 'code' => 'ARCH-12', 'name' => 'Archive',
            'room_type' => '2D', 'total_seats' => 0, 'status' => 'inactive',
        ]);
        $noLayout = Room::query()->create([
            'cinema_id' => $this->cinema->id, 'code' => 'P04', 'name' => 'No layout',
            'room_type' => '2D', 'total_seats' => 0, 'status' => 'active',
        ]);
        $legacyCinema = Cinema::query()->create([
            'canonical_key' => 'legacy-cinema', 'name' => 'Legacy', 'address' => 'Legacy', 'city' => 'HCM',
            'status' => 'active', 'is_primary' => false,
        ]);
        $legacy = Room::query()->create([
            'cinema_id' => $legacyCinema->id, 'code' => 'LEGACY', 'name' => 'Legacy room',
            'room_type' => '2D', 'total_seats' => 0, 'status' => 'active',
        ]);

        foreach ([$inactive, $archive, $noLayout, $legacy] as $room) {
            $this->actingAs($admin)->post(route('admin.showtimes.store'), $this->payload($movie, $room))
                ->assertSessionHasErrors('room_id');
        }

        $this->assertDatabaseCount('showtimes', 0);
    }

    public function test_invalid_database_movie_runtimes_are_rejected(): void
    {
        $room = $this->rooms['P01'];
        $admin = $this->userWithRole('admin');

        foreach ([0, -1, 601] as $runtime) {
            $movie = $this->movie($runtime);
            $this->actingAs($admin)->post(route('admin.showtimes.store'), $this->payload($movie, $room))
                ->assertSessionHasErrors('movie_id');
        }

        $this->assertDatabaseCount('showtimes', 0);
    }

    public function test_stopped_movie_is_rejected_even_by_direct_request(): void
    {
        $movie = $this->movie(90, ['status' => 'stopped']);

        $this->actingAs($this->userWithRole('admin'))->post(route('admin.showtimes.store'), $this->payload($movie, $this->rooms['P01']))
            ->assertSessionHasErrors('movie_id');
        $this->assertDatabaseCount('showtimes', 0);
    }

    public function test_server_ignores_fake_derived_cinema_and_layout_fields(): void
    {
        $movie = $this->movie(120);
        $room = $this->rooms['P01'];
        $otherLayout = $this->rooms['P02']->latestPublishedLayout()->firstOrFail();
        $legacyCinema = Cinema::query()->create([
            'canonical_key' => 'legacy-tamper', 'name' => 'Legacy', 'address' => 'Legacy', 'city' => 'HCM',
            'status' => 'active', 'is_primary' => false,
        ]);

        $this->actingAs($this->userWithRole('admin'))->post(route('admin.showtimes.store'), [
            ...$this->payload($movie, $room),
            'runtime' => 1,
            'duration' => 1,
            'end_time' => '18:01',
            'end_at' => '2030-06-10 18:01:00',
            'operational_end' => '2030-06-10 18:01:00',
            'cleaning_buffer' => 0,
            'cinema_id' => $legacyCinema->id,
            'room_layout_id' => $otherLayout->id,
        ])->assertRedirect(route('admin.showtimes.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('showtimes', [
            'movie_id' => $movie->id,
            'cinema_id' => $this->cinema->id,
            'room_id' => $room->id,
            'room_layout_id' => $room->latestPublishedLayout()->firstOrFail()->id,
            'show_time' => '18:00:00',
        ]);
    }

    public function test_create_edit_and_index_render_runtime_buffer_layout_and_window_details(): void
    {
        $movie = $this->movie(120, ['title' => 'Phim UI']);
        $room = $this->rooms['P01'];
        $showtime = $this->existing($movie, $room, ['show_date' => '2030-06-10', 'show_time' => '23:30:00']);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get(route('admin.showtimes.create'))
            ->assertOk()
            ->assertSee('Phim UI — 120 phút')
            ->assertSee('Kiểm tra khung giờ vận hành')
            ->assertSee('Bắt đầu')
            ->assertSee('Vệ sinh')
            ->assertSee('Phòng sẵn sàng')
            ->assertSee('Asia/Ho_Chi_Minh')
            ->assertSee('sơ đồ phiên bản 1');

        $this->actingAs($admin)->get(route('admin.showtimes.edit', $showtime))
            ->assertOk()
            ->assertSee('value="23:30"', false)
            ->assertSee('Sơ đồ hiện tại: phiên bản 1')
            ->assertSee('Kết thúc phim');

        $this->actingAs($admin)->get(route('admin.showtimes.index'))
            ->assertOk()
            ->assertSee('01:30')
            ->assertSee('01:45')
            ->assertSee('11/06/2030 (+1 ngày)')
            ->assertSee('P01 · Phòng 1 · sơ đồ phiên bản 1');
    }

    public function test_conflict_message_is_vietnamese_and_rendered_on_form(): void
    {
        $movie = $this->movie(120, ['title' => 'Phim Đang Chiếu']);
        $room = $this->rooms['P01'];
        $this->existing($movie, $room);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->followingRedirects()->from(route('admin.showtimes.create'))
            ->post(route('admin.showtimes.store'), $this->payload($movie, $room, ['show_time' => '19:00']))
            ->assertOk()->assertSee('Phòng P01 đã có phim')->assertSee('phòng sẵn sàng lúc');
    }

    public function test_showtime_routes_keep_guest_customer_staff_manager_admin_and_inactive_rbac(): void
    {
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];
        $showtime = $this->existing($movie, $room, ['show_time' => '10:00:00']);
        $payload = $this->payload($movie, $room, ['show_time' => '18:00']);

        $this->post(route('admin.showtimes.store'), $payload)->assertRedirect(route('login'));
        $this->actingAs($this->userWithRole('user'))->post(route('admin.showtimes.store'), $payload)->assertForbidden();
        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->post(route('admin.showtimes.store'), $payload)->assertForbidden();
        $this->actingAs($staff)->put(route('admin.showtimes.update', $showtime), $payload)->assertForbidden();

        $manager = $this->userWithRole('manager');
        $this->actingAs($manager)->post(route('admin.showtimes.store'), $payload)
            ->assertRedirect(route('admin.showtimes.index'));
        $this->actingAs($manager)->put(route('admin.showtimes.update', $showtime), $this->payload($movie, $room, ['show_time' => '12:00']))
            ->assertRedirect(route('admin.showtimes.index'));

        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->put(route('admin.showtimes.update', $showtime), $this->payload($movie, $room, ['show_time' => '13:45']))
            ->assertRedirect(route('admin.showtimes.index'));

        foreach (['manager', 'admin'] as $role) {
            $inactive = $this->userWithRole($role, ['status' => 'inactive']);
            $this->actingAs($inactive)->post(route('admin.showtimes.store'), $payload)->assertRedirect(route('login'));
            $this->actingAs($inactive)->put(route('admin.showtimes.update', $showtime), $payload)->assertRedirect(route('login'));
        }
    }
}
