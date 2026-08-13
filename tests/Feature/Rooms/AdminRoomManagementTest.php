<?php

namespace Tests\Feature\Rooms;

use App\Models\Movie;
use App\Models\PresentationFormat;
use App\Models\Room;
use App\Models\Seat;
use App\Models\Showtime;
use App\Services\CinemaContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoomManagementTest extends TestCase
{
    use RefreshDatabase;

    private PresentationFormat $presentationFormat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        app()->setLocale('vi');
        $this->presentationFormat = PresentationFormat::query()->create([
            'code' => '2D_FORMAT', 'name' => '2D Format', 'is_active' => true, 'sort_order' => 10,
        ]);
    }

    public function test_room_routes_follow_current_authorization_matrix(): void
    {
        $room = $this->room();

        $this->get(route('admin.rooms.index'))->assertRedirect(route('login'));
        $this->actingAs($this->userWithRole('user'))->get(route('admin.rooms.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('staff'))->get(route('admin.rooms.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('manager'))->get(route('admin.rooms.index'))->assertOk();
        $this->actingAs($this->userWithRole('admin'))->get(route('admin.rooms.show', $room))->assertOk();

        foreach (['admin.rooms.index', 'admin.rooms.create', 'admin.rooms.store', 'admin.rooms.show', 'admin.rooms.edit', 'admin.rooms.update', 'admin.rooms.destroy', 'admin.rooms.status.update'] as $name) {
            $this->assertTrue(app('router')->getRoutes()->hasNamedRoute($name), "Thiếu route {$name}");
        }
    }

    public function test_index_is_fully_vietnamese_searchable_filterable_and_paginated(): void
    {
        $admin = $this->userWithRole('admin');
        $active = $this->room(['code' => 'P01', 'name' => 'Phòng Sao', 'status' => 'active']);
        $inactive = $this->room(['code' => 'P02', 'name' => 'Phòng Trăng', 'status' => 'inactive']);
        foreach (range(3, 18) as $number) {
            $this->room(['code' => sprintf('P%02d', $number), 'name' => "Phòng {$number}"]);
        }

        $this->actingAs($admin)->get(route('admin.rooms.index'))
            ->assertOk()
            ->assertSee('Quản lý phòng chiếu')
            ->assertSee('Tìm theo tên hoặc mã phòng…')
            ->assertSee('Mã phòng')
            ->assertSee('Ghế thường')
            ->assertSee('Suất chiếu sắp tới')
            ->assertSee('Đang hoạt động')
            ->assertSee('Ngừng hoạt động')
            ->assertSee(route('admin.rooms.show', $active), false)
            ->assertSee(route('admin.rooms.edit', $active), false)
            ->assertSee('rel="next"', false);

        $this->actingAs($admin)->get(route('admin.rooms.index', ['search' => 'P02', 'status' => 'inactive']))
            ->assertOk()->assertSee($inactive->name)->assertDontSee($active->name);
    }

    public function test_empty_index_has_a_vietnamese_empty_state(): void
    {
        $this->actingAs($this->userWithRole('admin'))->get(route('admin.rooms.index'))
            ->assertOk()->assertSee('Chưa có phòng chiếu phù hợp.');
    }

    public function test_create_form_and_validation_are_vietnamese_and_preserve_input(): void
    {
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->get(route('admin.rooms.create'))
            ->assertOk()->assertSee('Mã phòng')->assertSee('Tên phòng')->assertSee('Lưu phòng chiếu');

        $response = $this->from(route('admin.rooms.create'))->actingAs($manager)->post(route('admin.rooms.store'), [
            'code' => '',
            'name' => 'Phòng đang nhập',
            'room_type' => 'không hợp lệ',
            'status' => 'active',
            'presentation_format_ids' => [$this->presentationFormat->id],
        ]);
        $response->assertRedirect(route('admin.rooms.create'))
            ->assertSessionHasErrors(['code', 'room_type'])
            ->assertSessionHasInput('name', 'Phòng đang nhập')
            ->assertSessionHasErrors(['code' => 'Trường mã phòng là bắt buộc.']);

        $this->assertDatabaseMissing('rooms', ['name' => 'Phòng đang nhập']);
    }

    public function test_create_and_update_preserve_authoritative_seat_data(): void
    {
        $manager = $this->userWithRole('manager');
        $response = $this->actingAs($manager)->post(route('admin.rooms.store'), [
            'code' => 'p20', 'name' => 'Phòng Hai Mươi', 'room_type' => '2d',
            'status' => 'active', 'width_m' => '7.5', 'length_m' => '10',
            'presentation_format_ids' => [$this->presentationFormat->id],
        ]);
        $room = Room::query()->where('code', 'P20')->sole();
        $response->assertRedirect(route('admin.rooms.layout.show', $room));
        $this->assertSame(7_500, $room->width_mm);
        $this->assertSame(10_000, $room->length_mm);

        $seat = Seat::query()->create([
            'room_id' => $room->id, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1',
            'type' => 'normal', 'status' => 'active',
        ]);
        $this->actingAs($manager)->put(route('admin.rooms.update', $room), [
            'code' => 'P20', 'name' => 'Phòng 20', 'room_type' => '3D', 'status' => 'active',
            'width_m' => '8', 'length_m' => '11', 'layout_id' => 999999,
            'presentation_format_ids' => [$this->presentationFormat->id],
        ])->assertRedirect(route('admin.rooms.show', $room));

        $this->assertDatabaseHas('seats', ['id' => $seat->id, 'room_id' => $room->id]);
        $this->assertSame(8_000, $room->fresh()->width_mm);
        $this->assertSame(11_000, $room->fresh()->length_mm);
    }

    public function test_deactivation_is_safe_and_reactivation_is_supported(): void
    {
        $admin = $this->userWithRole('admin');
        $room = $this->room();
        $movie = Movie::query()->create(['title' => 'Phim thử nghiệm', 'slug' => 'phim-thu-nghiem']);
        $layout = $this->publishedRoomLayoutFixture($room);
        Showtime::query()->create([
            'movie_id' => $movie->id,
            'cinema_id' => $room->cinema_id,
            'room_id' => $room->id,
            'room_layout_id' => $layout->id,
            'presentation_format_id' => $this->presentationFormatFixture($movie, $room)->id,
            'show_date' => now()->addDay()->toDateString(),
            'show_time' => '20:00:00',
            'price' => 80000,
            'status' => 'active',
        ]);

        $this->actingAs($admin)->patch(route('admin.rooms.status.update', $room), ['status' => 'inactive'])
            ->assertSessionHasErrors('status');
        $this->assertSame('active', $room->fresh()->status);

        $room->showtimes()->update(['status' => 'cancelled']);
        $this->actingAs($admin)->patch(route('admin.rooms.status.update', $room), ['status' => 'inactive'])
            ->assertSessionHas('success');
        $this->assertSame('inactive', $room->fresh()->status);
        $this->actingAs($admin)->get(route('admin.rooms.edit', $room))->assertOk();
        $this->actingAs($admin)->patch(route('admin.rooms.status.update', $room), ['status' => 'active'])
            ->assertSessionHas('success');
        $this->assertSame('active', $room->fresh()->status);
    }

    public function test_room_with_showtime_history_cannot_be_deleted(): void
    {
        $admin = $this->userWithRole('admin');
        $room = $this->room();
        $movie = Movie::query()->create(['title' => 'Phim lịch sử', 'slug' => 'phim-lich-su']);
        $layout = $this->publishedRoomLayoutFixture($room);
        Showtime::query()->create([
            'movie_id' => $movie->id, 'cinema_id' => $room->cinema_id, 'room_id' => $room->id,
            'room_layout_id' => $layout->id,
            'presentation_format_id' => $this->presentationFormatFixture($movie, $room)->id,
            'show_date' => now()->subDay()->toDateString(), 'show_time' => '10:00:00',
            'price' => 70000, 'status' => 'finished',
        ]);

        $this->actingAs($admin)->delete(route('admin.rooms.destroy', $room))->assertStatus(409);
        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function room(array $attributes = []): Room
    {
        $room = Room::factory()->create([
            'cinema_id' => app(CinemaContext::class)->id(),
            ...$attributes,
        ]);
        $room->presentationCapabilities()->attach($this->presentationFormat);

        return $room;
    }
}
