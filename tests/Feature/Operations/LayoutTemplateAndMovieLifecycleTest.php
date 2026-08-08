<?php

namespace Tests\Feature\Operations;

use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Room;
use App\Models\RoomLayoutTemplate;
use App\Models\RoomType;
use App\Models\Seat;
use App\Models\User;
use App\Services\ApplyRoomLayoutTemplateService;
use App\Services\MovieLifecycleService;
use App\Services\RoomLayoutService;
use Database\Seeders\RoomLayoutTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class LayoutTemplateAndMovieLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Cinema $cinema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->admin = $this->userWithRole('admin');
        $this->cinema = Cinema::query()->create([
            'code' => 'R7', 'name' => 'R7 Cinema', 'slug' => 'r7-cinema', 'address' => '1 Test',
            'city' => 'HCM', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh', 'is_primary' => true,
        ]);
        foreach (['normal', 'vip', 'couple'] as $type) {
            \DB::table('seat_types')->insert([
                'name' => ucfirst($type), 'code' => $type, 'slug' => $type,
                'status' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->actingAs($this->admin);
        $this->app['request']->setLaravelSession($this->app['session.store']);
    }

    public function test_template_clones_are_independent_and_reuse_only_exact_room_seat_identity(): void
    {
        $template = $this->template();
        $firstRoom = $this->room('A');
        $secondRoom = $this->room('B');
        $applicator = app(ApplyRoomLayoutTemplateService::class);

        $first = $applicator->apply($firstRoom, $template, 'Tiêu chuẩn khai trương phòng A', 'Khởi tạo', $this->admin, true);
        $second = $applicator->apply($secondRoom, $template, 'Tiêu chuẩn khai trương phòng B', null, $this->admin, true);

        $firstIds = $first->cells()->where('cell_type', 'seat')->pluck('seat_id')->sort()->values();
        $secondIds = $second->cells()->where('cell_type', 'seat')->pluck('seat_id')->sort()->values();
        $this->assertTrue($firstIds->intersect($secondIds)->isEmpty());
        $this->assertSame($template->name, $first->source_template_name_snapshot);

        $draft = $applicator->apply($firstRoom, $template, 'Bố trí giữ nguyên cho mùa hè', null, $this->admin);
        $this->assertSame($firstIds->all(), $draft->cells()->where('cell_type', 'seat')->pluck('seat_id')->sort()->values()->all());
        $this->assertSame(4, Seat::query()->count(), 'Applying the same template must not duplicate exact seats.');
    }

    public function test_conflicting_historical_label_is_rejected_without_mutating_published_seat(): void
    {
        $template = $this->template();
        $room = $this->room('C');
        $applicator = app(ApplyRoomLayoutTemplateService::class);
        $published = $applicator->apply($room, $template, 'Bố trí lịch sử phòng C', null, $this->admin, true);
        $a1 = $published->cells()->whereHas('seat', fn ($query) => $query->where('seat_code', 'A1'))->firstOrFail()->seat;

        $template->update(['columns' => 4]);
        $template->cells()->where('seat_label', 'A1')->update(['x_position' => 4]);
        try {
            $applicator->apply($room, $template->fresh(), 'Bố trí xung đột phòng C', null, $this->admin);
            $this->fail('A historical seat label must not be repurposed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('template_id', $exception->errors());
        }

        $this->assertDatabaseHas('seats', ['id' => $a1->id, 'seat_code' => 'A1', 'x_position' => 1]);
        $this->assertDatabaseCount('room_layouts', 1);
    }

    public function test_removed_position_is_retired_after_publish_but_historical_row_is_not_deleted(): void
    {
        $template = $this->template();
        $room = $this->room('D');
        $applicator = app(ApplyRoomLayoutTemplateService::class);
        $first = $applicator->apply($room, $template, 'Bố trí đầy đủ phòng D', null, $this->admin, true);
        $a2Id = $first->cells()->whereHas('seat', fn ($query) => $query->where('seat_code', 'A2'))->value('seat_id');
        $template->cells()->where('seat_label', 'A2')->delete();

        $draft = $applicator->apply($room, $template->fresh(), 'Bố trí rút gọn phòng D', 'Bỏ vị trí cuối', $this->admin);
        app(RoomLayoutService::class)->publish($draft, $this->admin->id);

        $this->assertDatabaseHas('seats', ['id' => $a2Id, 'status' => Seat::STATUS_RETIRED]);
        $this->assertDatabaseHas('room_layout_cells', ['room_layout_id' => $first->id, 'seat_id' => $a2Id]);
    }

    public function test_template_permissions_preview_and_archive_preserve_usage(): void
    {
        $template = $this->template();
        $manager = $this->userWithRole('manager');
        $this->actingAs($manager)->get(route('admin.layout-templates.index'))->assertOk()->assertSee($template->name);
        $this->actingAs($manager)->get(route('admin.layout-templates.create'))->assertForbidden();

        $this->actingAs($this->admin)->get(route('admin.layout-templates.show', $template))
            ->assertOk()->assertSee('MÀN HÌNH')->assertSee('A1');
        $this->post(route('admin.layout-templates.archive', $template))->assertRedirect();
        $this->assertDatabaseHas('room_layout_templates', ['id' => $template->id, 'status' => 'archived']);
    }

    public function test_shared_template_editor_preserves_dynamic_room_types_accessibility_and_serialized_geometry(): void
    {
        $roomType = RoomType::query()->create([
            'code' => 'SCREENX', 'name' => 'ScreenX động', 'is_active' => true, 'sort_order' => 10,
        ]);
        $layout = [
            'rows' => 2,
            'columns' => 4,
            'screen_position' => 'bottom',
            'cells' => [
                ['x_position' => 1, 'y_position' => 1, 'cell_type' => 'seat', 'seat_type' => 'normal', 'seat_label' => 'A1'],
                ['x_position' => 2, 'y_position' => 1, 'cell_type' => 'seat', 'seat_type' => 'vip', 'seat_label' => 'A2'],
                ['x_position' => 1, 'y_position' => 2, 'cell_type' => 'seat', 'seat_type' => 'couple', 'seat_label' => 'B1', 'pair_key' => 'PAIR-B-1'],
                ['x_position' => 2, 'y_position' => 2, 'cell_type' => 'seat', 'seat_type' => 'couple', 'seat_label' => 'B2', 'pair_key' => 'PAIR-B-1'],
                ['x_position' => 3, 'y_position' => 2, 'cell_type' => 'aisle'],
            ],
        ];

        $create = $this->get(route('admin.layout-templates.create'))->assertOk();
        $create->assertSee('data-layout-template-form', false)
            ->assertSee('data-layout-tool="normal" aria-pressed="true"', false)
            ->assertSee('data-layout-tool="vip"', false)
            ->assertSee('data-layout-tool="couple"', false)
            ->assertSee('data-layout-tool="aisle"', false)
            ->assertSee('data-layout-tool="empty"', false)
            ->assertSee('type="button" data-layout-tool', false)
            ->assertSee($roomType->name)
            ->assertSee('Mọi loại phòng')
            ->assertSee('Mã dùng để nhận diện mẫu trong hệ thống và không được trùng.');

        $this->post(route('admin.layout-templates.store'), [
            'code' => 'UX_SCREENX', 'name' => 'Mẫu ScreenX kiểm thử UX',
            'description' => 'Kiểm thử payload dùng chung.', 'room_type' => $roomType->code,
            'layout' => json_encode($layout),
        ])->assertRedirect();

        $template = RoomLayoutTemplate::query()->where('code', 'UX_SCREENX')->with('cells')->sole();
        $this->assertSame('bottom', $template->screen_position);
        $this->assertSame(5, $template->cells->count());
        $this->assertSame(2, $template->cells->where('seat_type', 'couple')->count());
        $this->assertSame(1, $template->cells->where('seat_type', 'couple')->pluck('pair_key')->unique()->count());

        $show = $this->get(route('admin.layout-templates.show', $template))->assertOk();
        $show->assertSee('Sơ đồ chỉ đọc')
            ->assertSee('Ghế đôi')
            ->assertSee('2 vị trí')
            ->assertSee('ScreenX động')
            ->assertSee('Mẫu này chưa được áp dụng cho phòng chiếu nào.')
            ->assertDontSee('data-layout-template-form', false)
            ->assertDontSee('data-layout-tool=', false);

        $this->get(route('admin.layout-templates.edit', $template))->assertOk()
            ->assertSee('data-layout-template-form', false)
            ->assertSee('UX_SCREENX')
            ->assertSee('ScreenX động');
        $this->put(route('admin.layout-templates.update', $template), [
            'code' => 'UX_SCREENX', 'name' => 'Mẫu ScreenX đã cập nhật',
            'description' => 'Vẫn giữ nguyên định dạng serialized layout.', 'room_type' => $roomType->code,
            'layout' => json_encode($layout),
        ])->assertRedirect(route('admin.layout-templates.show', $template));
        $this->assertDatabaseHas('room_layout_templates', ['id' => $template->id, 'name' => 'Mẫu ScreenX đã cập nhật']);
        $this->assertSame(2, $template->fresh('cells')->cells->where('seat_type', 'couple')->count());
    }

    public function test_applied_template_remains_editable_but_archived_template_keeps_existing_read_only_rule(): void
    {
        $template = $this->template();
        app(ApplyRoomLayoutTemplateService::class)->apply(
            $this->room('UX'), $template, 'Bố trí kiểm thử chính sách mẫu', null, $this->admin,
        );

        $this->get(route('admin.layout-templates.edit', $template))->assertOk();

        $template->update(['status' => RoomLayoutTemplate::STATUS_ARCHIVED]);
        $this->from(route('admin.layout-templates.show', $template))
            ->get(route('admin.layout-templates.edit', $template))
            ->assertRedirect(route('admin.layout-templates.show', $template))
            ->assertSessionHasErrors('template');
        $this->get(route('admin.layout-templates.show', $template))
            ->assertOk()->assertDontSee('Chỉnh sửa');
    }

    public function test_movie_lifecycle_is_authoritative_terminal_and_non_destructive(): void
    {
        $movie = Movie::query()->create(['title' => 'Lifecycle', 'slug' => 'lifecycle', 'duration' => 90, 'status' => Movie::STATUS_DRAFT]);
        $service = app(MovieLifecycleService::class);
        $service->transition($movie, Movie::STATUS_COMING_SOON, $this->admin);
        $service->transition($movie->fresh(), Movie::STATUS_NOW_SHOWING, $this->admin);
        $service->transition($movie->fresh(), Movie::STATUS_INACTIVE, $this->admin);
        $service->transition($movie->fresh(), Movie::STATUS_ARCHIVED, $this->admin);

        $this->get(route('user.movies.index'))->assertOk()->assertDontSee('Lifecycle');
        $this->expectException(ValidationException::class);
        try {
            $service->transition($movie->fresh(), Movie::STATUS_COMING_SOON, $this->admin);
        } finally {
            $this->assertDatabaseHas('movies', ['id' => $movie->id, 'status' => Movie::STATUS_ARCHIVED]);
            $this->assertDatabaseHas('activity_logs', ['subject_id' => (string) $movie->id, 'action' => 'movie.archived']);
        }
    }

    public function test_movie_model_refuses_hard_delete(): void
    {
        $movie = Movie::query()->create(['title' => 'History', 'slug' => 'history', 'status' => Movie::STATUS_INACTIVE]);
        try {
            $movie->delete();
            $this->fail('Movie deletion must be unavailable.');
        } catch (LogicException) {
            $this->assertDatabaseHas('movies', ['id' => $movie->id]);
        }
    }

    public function test_template_and_catalog_query_counts_remain_bounded_with_representative_data(): void
    {
        $this->seed(RoomLayoutTemplateSeeder::class);
        $template = RoomLayoutTemplate::query()->where('code', 'STANDARD_100')->firstOrFail();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('admin.layout-templates.index'))->assertOk();
        $indexQueries = count(DB::getQueryLog());

        DB::flushQueryLog();
        $this->get(route('admin.layout-templates.show', $template))->assertOk();
        $detailQueries = count(DB::getQueryLog());

        DB::flushQueryLog();
        $this->get(route('admin.layout-templates.create'))->assertOk();
        $createQueries = count(DB::getQueryLog());

        DB::flushQueryLog();
        $this->get(route('admin.layout-templates.edit', $template))->assertOk();
        $editQueries = count(DB::getQueryLog());

        DB::flushQueryLog();
        $room = $this->room('Q');
        app(ApplyRoomLayoutTemplateService::class)->apply(
            $room, $template, 'Tiêu chuẩn hiệu năng phòng Q', null, $this->admin, true,
        );
        $applyQueries = count(DB::getQueryLog());

        DB::flushQueryLog();
        $this->get(route('admin.rooms.show', $room))->assertOk();
        $roomQueries = count(DB::getQueryLog());

        Movie::query()->insert(collect(range(1, 20))->map(fn (int $number): array => [
            'title' => "Query Movie {$number}", 'slug' => "query-movie-{$number}",
            'status' => Movie::STATUS_NOW_SHOWING, 'created_at' => now(), 'updated_at' => now(),
        ])->all());
        DB::flushQueryLog();
        $this->get(route('admin.movies.index'))->assertOk();
        $adminMovieQueries = count(DB::getQueryLog());

        DB::flushQueryLog();
        $this->get(route('user.movies.index'))->assertOk();
        $customerMovieQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(15, $indexQueries);
        $this->assertLessThanOrEqual(20, $detailQueries);
        $this->assertLessThanOrEqual(20, $createQueries);
        $this->assertLessThanOrEqual(20, $editQueries);
        $this->assertLessThanOrEqual(30, $applyQueries);
        $this->assertLessThanOrEqual(25, $roomQueries);
        $this->assertLessThanOrEqual(15, $adminMovieQueries);
        $this->assertLessThanOrEqual(25, $customerMovieQueries);
    }

    private function room(string $suffix): Room
    {
        return Room::query()->create([
            'cinema_id' => $this->cinema->id, 'code' => 'R'.$suffix, 'name' => 'Room '.$suffix,
            'room_type' => '2D', 'status' => 'active', 'total_seats' => 0,
        ]);
    }

    private function template(): RoomLayoutTemplate
    {
        $template = RoomLayoutTemplate::query()->create([
            'code' => 'TWO_SEATS', 'name' => 'Mẫu hai ghế kiểm thử', 'room_type' => '2D',
            'rows' => 1, 'columns' => 3, 'screen_position' => 'top', 'status' => RoomLayoutTemplate::STATUS_ACTIVE,
        ]);
        $template->cells()->createMany([
            ['x_position' => 1, 'y_position' => 1, 'cell_type' => 'seat', 'seat_type' => 'normal', 'seat_label' => 'A1', 'seat_unit_key' => 'A1', 'metadata' => ['row' => 'A', 'number' => 1]],
            ['x_position' => 2, 'y_position' => 1, 'cell_type' => 'aisle'],
            ['x_position' => 3, 'y_position' => 1, 'cell_type' => 'seat', 'seat_type' => 'vip', 'seat_label' => 'A2', 'seat_unit_key' => 'A2', 'metadata' => ['row' => 'A', 'number' => 2]],
        ]);

        return $template;
    }
}
