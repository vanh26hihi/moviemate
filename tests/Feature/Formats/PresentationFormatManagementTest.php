<?php

namespace Tests\Feature\Formats;

use App\Models\PresentationFormat;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Showtimes\ShowtimeTestCase;

final class PresentationFormatManagementTest extends ShowtimeTestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_global_admin_can_view_create_update_and_archive_master_with_server_owned_actors(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get(route('admin.presentation-formats.index'))
            ->assertOk()->assertSee('Định dạng trình chiếu');
        $this->assertFalse(app('router')->getRoutes()->hasNamedRoute('admin.presentation-formats.destroy'));
        $this->post(route('admin.presentation-formats.store'), [
            'code' => ' test fmt ',
            'name' => 'Định dạng thử nghiệm',
            'description' => 'Kiểm tra master động.',
            'sort_order' => 30,
            'created_by_user_id' => 999999,
            'updated_by_user_id' => 999999,
        ])->assertRedirect(route('admin.presentation-formats.index'));

        $format = PresentationFormat::query()->where('code', 'TEST_FMT')->sole();
        $this->assertTrue($format->is_active);
        $this->assertSame($admin->id, $format->created_by_user_id);
        $this->assertSame($admin->id, $format->updated_by_user_id);

        $this->put(route('admin.presentation-formats.update', $format), [
            'code' => 'TEST_FMT',
            'name' => 'Định dạng thử nghiệm cập nhật',
            'description' => 'Mô tả mới.',
            'sort_order' => 35,
            'updated_by_user_id' => 999999,
        ])->assertRedirect(route('admin.presentation-formats.index'));
        $this->assertDatabaseHas('presentation_formats', [
            'id' => $format->id,
            'name' => 'Định dạng thử nghiệm cập nhật',
            'sort_order' => 35,
            'updated_by_user_id' => $admin->id,
        ]);

        $this->patch(route('admin.presentation-formats.archive', $format))->assertSessionHas('success');
        $this->assertDatabaseHas('presentation_formats', ['id' => $format->id, 'is_active' => false]);
        $this->assertDatabaseHas('activity_logs', ['subject_id' => (string) $format->id, 'action' => 'presentation_format.archived']);
    }

    public function test_unique_code_and_name_are_cleanly_enforced(): void
    {
        $admin = $this->userWithRole('admin');
        $this->format('2D', 'Hai chiều');

        $this->actingAs($admin)->post(route('admin.presentation-formats.store'), [
            'code' => '2D', 'name' => 'Khác',
        ])->assertSessionHasErrors('code');
        $this->post(route('admin.presentation-formats.store'), [
            'code' => 'OTHER', 'name' => 'Hai chiều',
        ])->assertSessionHasErrors('name');
        $this->assertSame(1, PresentationFormat::query()->count());
    }

    public function test_manager_cannot_view_or_mutate_global_master(): void
    {
        $manager = $this->userWithRole('manager');
        $format = $this->format('2D');

        $this->actingAs($manager)->get(route('admin.presentation-formats.index'))->assertForbidden();
        $this->post(route('admin.presentation-formats.store'), ['code' => 'NEW', 'name' => 'New'])->assertForbidden();
        $this->put(route('admin.presentation-formats.update', $format), ['code' => '2D', 'name' => 'Renamed'])->assertForbidden();
        $this->patch(route('admin.presentation-formats.archive', $format))->assertForbidden();
        $this->assertTrue($format->fresh()->is_active);
    }

    public function test_future_active_showtime_blocks_archive_transactionally(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 12:00:00', 'Asia/Ho_Chi_Minh'));
        $admin = $this->userWithRole('admin');
        $format = $this->format('3D');
        $showtime = $this->existing($this->movie(), $this->rooms->get('P01'), [
            'show_time' => '18:00:00',
            'presentation_format_id' => $format->id,
        ]);

        $this->actingAs($admin)->patch(route('admin.presentation-formats.archive', $format))
            ->assertSessionHasErrors(['format' => 'Không thể lưu trữ định dạng 3D vì còn suất chiếu tương lai đang sử dụng.']);

        $this->assertTrue($format->fresh()->is_active);
        $this->assertSame($format->id, $showtime->fresh()->presentation_format_id);
    }

    public function test_completed_and_cancelled_history_does_not_block_archive_and_keeps_foreign_keys(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-11 12:00:00', 'Asia/Ho_Chi_Minh'));
        $admin = $this->userWithRole('admin');
        $format = $this->format('3D');
        $completed = $this->existing($this->movie(), $this->rooms->get('P01'), ['presentation_format_id' => $format->id]);
        $cancelled = $this->existing($this->movie(), $this->rooms->get('P02'), [
            'status' => 'cancelled',
            'presentation_format_id' => $format->id,
        ]);

        $this->actingAs($admin)->patch(route('admin.presentation-formats.archive', $format))
            ->assertSessionHas('success');

        $this->assertFalse($format->fresh()->is_active);
        $this->assertSame($format->id, $completed->fresh()->presentation_format_id);
        $this->assertSame($format->id, $cancelled->fresh()->presentation_format_id);
    }

    public function test_pivots_alone_allow_archive_and_are_retained(): void
    {
        $admin = $this->userWithRole('admin');
        $format = $this->format('3D');
        $movie = $this->movie();
        $room = $this->rooms->get('P01');
        $movie->supportedPresentationFormats()->attach($format);
        $room->presentationCapabilities()->attach($format);

        $this->actingAs($admin)->patch(route('admin.presentation-formats.archive', $format))
            ->assertSessionHas('success');

        $this->assertFalse($format->fresh()->is_active);
        $this->assertTrue($movie->supportedPresentationFormats()->whereKey($format->id)->exists());
        $this->assertTrue($room->presentationCapabilities()->whereKey($format->id)->exists());
    }

    public function test_referenced_format_code_is_immutable_but_safe_metadata_remains_editable(): void
    {
        $admin = $this->userWithRole('admin');
        $format = $this->format('3D');
        $this->movie()->supportedPresentationFormats()->attach($format);

        $this->actingAs($admin)->put(route('admin.presentation-formats.update', $format), [
            'code' => 'NEW3D', 'name' => '3D mới', 'description' => null, 'sort_order' => 20,
        ])->assertSessionHasErrors('code');
        $this->assertSame('3D', $format->fresh()->code);

        $this->put(route('admin.presentation-formats.update', $format), [
            'code' => '3D', 'name' => 'Ba chiều', 'description' => 'Tên hiển thị mới.', 'sort_order' => 25,
        ])->assertRedirect(route('admin.presentation-formats.index'));
        $this->assertSame('Ba chiều', $format->fresh()->name);
    }

    public function test_management_form_and_list_queries_remain_bounded_with_many_formats(): void
    {
        $admin = $this->userWithRole('admin');
        $formats = collect(range(1, 24))->map(fn (int $number): PresentationFormat => $this->format(
            sprintf('FMT_%02d', $number),
            sprintf('Format %02d', $number),
        ));
        $movie = $this->movie();
        $movie->supportedPresentationFormats()->attach($formats->first());
        $room = $this->rooms->get('P01');
        $room->presentationCapabilities()->attach($formats->first());
        $this->actingAs($admin);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('admin.presentation-formats.index'))->assertOk();
        $masterListQueries = count(DB::getQueryLog());

        DB::flushQueryLog();
        $this->get(route('admin.movies.create'))->assertOk();
        $movieCreateQueries = count(DB::getQueryLog());

        DB::flushQueryLog();
        $this->get(route('admin.movies.edit', $movie))->assertOk();
        $movieEditQueries = count(DB::getQueryLog());

        DB::flushQueryLog();
        $this->get(route('admin.rooms.create'))->assertOk();
        $roomCreateQueries = count(DB::getQueryLog());

        DB::flushQueryLog();
        $this->get(route('admin.rooms.edit', $room))->assertOk();
        $roomEditQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(12, $masterListQueries);
        $this->assertLessThanOrEqual(18, $movieCreateQueries);
        $this->assertLessThanOrEqual(20, $movieEditQueries);
        $this->assertLessThanOrEqual(22, $roomCreateQueries);
        $this->assertLessThanOrEqual(22, $roomEditQueries);
    }

    private function format(string $code, ?string $name = null): PresentationFormat
    {
        return PresentationFormat::query()->create([
            'code' => $code,
            'name' => $name ?? $code,
            'is_active' => true,
            'sort_order' => 20,
        ]);
    }
}
