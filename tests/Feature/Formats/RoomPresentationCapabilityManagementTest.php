<?php

namespace Tests\Feature\Formats;

use App\Models\Cinema;
use App\Models\PresentationFormat;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\UserCinemaAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Showtimes\ShowtimeTestCase;

final class RoomPresentationCapabilityManagementTest extends ShowtimeTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RoomType::query()->updateOrCreate(['code' => 'STANDARD'], ['name' => 'Tiêu chuẩn', 'is_active' => true, 'sort_order' => 10]);
        RoomType::query()->updateOrCreate(['code' => 'IMAX'], ['name' => 'IMAX', 'is_active' => true, 'sort_order' => 20]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_manager_can_create_room_with_multiple_capabilities_independent_of_room_type(): void
    {
        $manager = $this->userWithRole('manager');
        $twoD = $this->format('2D');
        $threeD = $this->format('3D');

        $this->actingAs($manager)->get(route('admin.rooms.create'))
            ->assertOk()->assertSee('Loại phòng')->assertSee('Khả năng trình chiếu')->assertSee('2D')->assertSee('3D');
        $this->post(route('admin.rooms.store'), [
            'code' => 'imax01',
            'name' => 'Phòng IMAX đa định dạng',
            'room_type' => 'IMAX',
            'status' => 'active',
            'presentation_format_ids' => [$twoD->id, $threeD->id],
        ])->assertSessionHasNoErrors();

        $room = Room::query()->where('code', 'IMAX01')->sole();
        $this->assertSame('IMAX', $room->room_type);
        $this->assertSame('IMAX', $room->roomType?->code);
        $this->assertSame(['2D', '3D'], $room->presentationCapabilities()->orderBy('sort_order')->pluck('code')->all());
    }

    public function test_unknown_archived_and_duplicate_capability_ids_fail_without_creating_room(): void
    {
        $manager = $this->userWithRole('manager');
        $active = $this->format('2D');
        $archived = $this->format('3D', false);
        $this->actingAs($manager);

        $this->post(route('admin.rooms.store'), $this->roomPayload('UNK', [999999]))
            ->assertSessionHasErrors('presentation_format_ids.0');
        $this->post(route('admin.rooms.store'), $this->roomPayload('ARC', [$archived->id]))
            ->assertSessionHasErrors('presentation_format_ids');
        $this->post(route('admin.rooms.store'), $this->roomPayload('DUP', [$active->id, $active->id]))
            ->assertSessionHasErrors('presentation_format_ids.1');

        $room = $this->rooms->get('P01');
        $room->presentationCapabilities()->attach($active);
        $this->put(route('admin.rooms.update', $room), $this->updatePayload($room, [$active->id, $archived->id]))
            ->assertSessionHasErrors('presentation_format_ids');

        $this->assertDatabaseMissing('rooms', ['code' => 'UNK']);
        $this->assertDatabaseMissing('rooms', ['code' => 'ARC']);
        $this->assertDatabaseMissing('rooms', ['code' => 'DUP']);
        $this->assertFalse($room->presentationCapabilities()->whereKey($archived->id)->exists());
    }

    public function test_future_active_showtime_blocks_capability_removal_and_rolls_back_room_metadata(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 12:00:00', 'Asia/Ho_Chi_Minh'));
        $manager = $this->userWithRole('manager');
        $twoD = $this->format('2D');
        $threeD = $this->format('3D');
        $room = $this->rooms->get('P01');
        $room->presentationCapabilities()->attach([$twoD->id, $threeD->id]);
        $movie = $this->movie();
        $showtime = $this->existing($movie, $room);
        DB::table('showtimes')->where('id', $showtime->id)->update(['presentation_format_id' => $threeD->id]);

        $this->actingAs($manager)->put(route('admin.rooms.update', $room), [
            'code' => $room->code,
            'name' => 'Tên không được lưu',
            'room_type' => 'IMAX',
            'status' => 'active',
            'cleaning_buffer_minutes' => 77,
            'presentation_format_ids' => [$twoD->id],
        ])->assertSessionHasErrors([
            'presentation_format_ids' => 'Không thể bỏ khả năng 3D vì phòng còn suất chiếu tương lai sử dụng định dạng này.',
        ]);

        $this->assertNotSame('Tên không được lưu', $room->fresh()->name);
        $this->assertSame('2D', $room->fresh()->room_type);
        $this->assertNotSame(77, $room->fresh()->cleaning_buffer_minutes);
        $this->assertSame([$twoD->id, $threeD->id], $room->presentationCapabilities()->orderBy('presentation_formats.id')->pluck('presentation_formats.id')->all());
        $this->assertSame($threeD->id, $showtime->fresh()->presentation_format_id);
    }

    public function test_completed_history_allows_capability_detach_and_keeps_showtime_format(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 12:00:00', 'Asia/Ho_Chi_Minh'));
        $manager = $this->userWithRole('manager');
        $twoD = $this->format('2D');
        $threeD = $this->format('3D');
        $room = $this->rooms->get('P01');
        $room->presentationCapabilities()->attach([$twoD->id, $threeD->id]);
        $showtime = $this->existing($this->movie(), $room, ['show_date' => '2030-06-09']);
        DB::table('showtimes')->where('id', $showtime->id)->update(['presentation_format_id' => $threeD->id]);

        $this->actingAs($manager)->put(route('admin.rooms.update', $room), $this->updatePayload($room, [$twoD->id]))
            ->assertRedirect(route('admin.rooms.show', $room));

        $this->assertFalse($room->presentationCapabilities()->whereKey($threeD->id)->exists());
        $this->assertSame($threeD->id, $showtime->fresh()->presentation_format_id);
    }

    public function test_cancelled_future_showtime_allows_capability_detach_and_keeps_showtime_format(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 12:00:00', 'Asia/Ho_Chi_Minh'));
        $manager = $this->userWithRole('manager');
        $twoD = $this->format('2D');
        $threeD = $this->format('3D');
        $room = $this->rooms->get('P01');
        $room->presentationCapabilities()->attach([$twoD->id, $threeD->id]);
        $showtime = $this->existing($this->movie(), $room, ['status' => 'cancelled']);
        DB::table('showtimes')->where('id', $showtime->id)->update(['presentation_format_id' => $threeD->id]);

        $this->actingAs($manager)->put(route('admin.rooms.update', $room), $this->updatePayload($room, [$twoD->id]))
            ->assertRedirect(route('admin.rooms.show', $room));

        $this->assertFalse($room->presentationCapabilities()->whereKey($threeD->id)->exists());
        $this->assertSame($threeD->id, $showtime->fresh()->presentation_format_id);
    }

    public function test_inactive_room_may_be_incomplete_but_active_create_and_reactivation_require_capability(): void
    {
        $manager = $this->userWithRole('manager');
        $this->actingAs($manager)->post(route('admin.rooms.store'), [
            'code' => 'ACTIVE0', 'name' => 'Active zero', 'room_type' => 'STANDARD', 'status' => 'active',
        ])->assertSessionHasErrors('presentation_format_ids');

        $this->post(route('admin.rooms.store'), [
            'code' => 'INACTIVE0', 'name' => 'Inactive zero', 'room_type' => 'STANDARD', 'status' => 'inactive',
        ])->assertSessionHasNoErrors();
        $room = Room::query()->where('code', 'INACTIVE0')->sole();
        $this->assertSame(0, $room->presentationCapabilities()->count());

        $this->patch(route('admin.rooms.status.update', $room), ['status' => 'active'])
            ->assertSessionHasErrors(['status' => 'Phòng phải có ít nhất một khả năng trình chiếu đang sử dụng trước khi kích hoạt.']);
        $this->assertSame('inactive', $room->fresh()->status);

        $room->presentationCapabilities()->attach($this->format('2D'));
        $this->patch(route('admin.rooms.status.update', $room), ['status' => 'active'])->assertSessionHas('success');
        $this->assertSame('active', $room->fresh()->status);
    }

    public function test_archived_current_capability_is_visible_and_preserved_on_unrelated_edit(): void
    {
        $manager = $this->userWithRole('manager');
        $active = $this->format('2D');
        $archived = $this->format('3D', false);
        $room = $this->rooms->get('P01');
        $room->presentationCapabilities()->attach([$active->id, $archived->id]);

        $this->actingAs($manager)->get(route('admin.rooms.edit', $room))
            ->assertOk()->assertSee('Đã lưu trữ · bỏ chọn để gỡ khả năng')->assertSee('value="'.$archived->id.'"', false);
        $this->put(route('admin.rooms.update', $room), $this->updatePayload($room, [$active->id, $archived->id]))
            ->assertRedirect(route('admin.rooms.show', $room));

        $this->assertSame([$active->id, $archived->id], $room->presentationCapabilities()->orderBy('presentation_formats.id')->pluck('presentation_formats.id')->all());
    }

    public function test_manager_cannot_mutate_room_capabilities_outside_assigned_cinema(): void
    {
        $manager = $this->userWithRole('manager');
        $format = $this->format('2D');
        $foreignCinema = Cinema::factory()->create(['status' => 'active', 'archived_at' => null]);
        $foreignRoom = Room::factory()->create([
            'cinema_id' => $foreignCinema->id,
            'room_type' => 'STANDARD',
            'room_type_id' => RoomType::query()->where('code', 'STANDARD')->value('id'),
        ]);
        $foreignRoom->presentationCapabilities()->attach($format);
        $this->assertFalse(UserCinemaAssignment::query()->where('user_id', $manager->id)->where('cinema_id', $foreignCinema->id)->exists());

        $this->actingAs($manager)->put(route('admin.rooms.update', $foreignRoom), $this->updatePayload($foreignRoom, [$format->id]))
            ->assertNotFound();
        $this->assertSame($format->id, $foreignRoom->presentationCapabilities()->sole()->id);
    }

    private function format(string $code, bool $active = true): PresentationFormat
    {
        return PresentationFormat::query()->create([
            'code' => $code, 'name' => $code, 'is_active' => $active,
            'sort_order' => $code === '2D' ? 10 : 20,
        ]);
    }

    /** @param list<int> $formatIds */
    private function roomPayload(string $code, array $formatIds): array
    {
        return [
            'code' => $code, 'name' => "Phòng {$code}", 'room_type' => 'STANDARD',
            'status' => 'active', 'presentation_format_ids' => $formatIds,
        ];
    }

    /** @param list<int> $formatIds */
    private function updatePayload(Room $room, array $formatIds): array
    {
        return [
            'code' => $room->code, 'name' => $room->name, 'room_type' => 'STANDARD',
            'status' => $room->status, 'presentation_format_ids' => $formatIds,
        ];
    }
}
