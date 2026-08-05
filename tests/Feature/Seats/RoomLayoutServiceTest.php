<?php

namespace Tests\Feature\Seats;

use App\Models\Cinema;
use App\Models\Room;
use App\Models\RoomLayoutCell;
use App\Models\Seat;
use App\Models\Showtime;
use App\Services\CinemaContext;
use App\Services\RoomLayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class RoomLayoutServiceTest extends TestCase
{
    use RefreshDatabase;

    private RoomLayoutService $service;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RoomLayoutService::class);
        $cinema = Cinema::query()->where('canonical_key', CinemaContext::CANONICAL_KEY)->firstOrFail();
        $this->room = Room::query()->create([
            'cinema_id' => $cinema->id, 'code' => 'T01', 'name' => 'Test Room',
            'room_type' => '2D', 'total_seats' => 0, 'status' => 'active',
        ]);
        foreach ([['normal', false], ['vip', false], ['couple', true]] as [$code, $pair]) {
            DB::table('seat_types')->insert([
                'name' => ucfirst($code), 'code' => $code, 'slug' => $code,
                'price_modifier' => 0, 'is_pair' => $pair, 'status' => true,
                'sort_order' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function test_blank_draft_has_next_version_and_only_one_draft_is_allowed(): void
    {
        $draft = $this->service->createBlankDraft($this->room, null, 3, 4, 'bottom');
        $this->assertSame(1, $draft->version);
        $this->assertSame('draft', $draft->status);
        $this->assertSame('bottom', $draft->screen_position);

        $this->expectException(ValidationException::class);
        $this->service->createBlankDraft($this->room);
    }

    public function test_save_draft_supports_empty_aisle_normal_vip_and_maintenance(): void
    {
        $draft = $this->service->createBlankDraft($this->room, null, 2, 4);
        $saved = $this->service->saveDraft($draft, [
            'name' => 'Irregular', 'rows' => 2, 'columns' => 4, 'screen_position' => 'top',
            'cells' => [
                ['kind' => 'normal', 'x' => 1, 'y' => 1, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1', 'status' => 'active'],
                ['kind' => 'aisle', 'x' => 2, 'y' => 1],
                ['kind' => 'vip', 'x' => 4, 'y' => 1, 'row' => 'A', 'number' => 2, 'seat_code' => 'A2', 'status' => 'maintenance'],
                ['kind' => 'empty', 'x' => 3, 'y' => 2],
            ],
        ]);

        $this->assertCount(3, $saved->cells);
        $this->assertSame(2, $saved->cells->where('cell_type', 'seat')->count());
        $this->assertDatabaseHas('seats', ['seat_code' => 'A2', 'type' => 'vip', 'status' => 'maintenance']);
        $this->assertDatabaseMissing('room_layout_cells', ['x_position' => 3, 'y_position' => 2]);
    }

    public function test_duplicate_coordinate_duplicate_code_and_out_of_bounds_are_rejected(): void
    {
        $draft = $this->service->createBlankDraft($this->room, null, 2, 2);
        foreach ([
            [
                ['kind' => 'normal', 'x' => 1, 'y' => 1, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1'],
                ['kind' => 'aisle', 'x' => 1, 'y' => 1],
            ],
            [
                ['kind' => 'normal', 'x' => 1, 'y' => 1, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1'],
                ['kind' => 'vip', 'x' => 2, 'y' => 1, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1'],
            ],
            [['kind' => 'aisle', 'x' => 3, 'y' => 1]],
        ] as $cells) {
            try {
                $this->service->saveDraft($draft, ['rows' => 2, 'columns' => 2, 'screen_position' => 'top', 'cells' => $cells]);
                $this->fail('Expected invalid layout payload to be rejected.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_half_or_aisle_split_couple_pair_is_rejected(): void
    {
        $draft = $this->service->createBlankDraft($this->room, null, 1, 3);
        $half = [['kind' => 'couple', 'x' => 1, 'y' => 1, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1', 'pair_code' => 'A-P1', 'pair_position' => 'left']];
        try {
            $this->service->saveDraft($draft, ['rows' => 1, 'columns' => 3, 'screen_position' => 'top', 'cells' => $half]);
            $this->fail('Half pair must fail.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $split = [
            ['kind' => 'couple', 'x' => 1, 'y' => 1, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1', 'pair_code' => 'A-P1', 'pair_position' => 'left'],
            ['kind' => 'aisle', 'x' => 2, 'y' => 1],
            ['kind' => 'couple', 'x' => 3, 'y' => 1, 'row' => 'A', 'number' => 2, 'seat_code' => 'A2', 'pair_code' => 'A-P1', 'pair_position' => 'right'],
        ];
        $this->expectException(ValidationException::class);
        $this->service->saveDraft($draft, ['rows' => 1, 'columns' => 3, 'screen_position' => 'top', 'cells' => $split]);
    }

    public function test_publish_is_immutable_and_clone_increments_version(): void
    {
        $published = $this->publishSingleSeatLayout();
        $this->assertSame('published', $published->status);
        $this->assertNotNull($published->published_at);
        $this->assertSame(1, $this->room->fresh()->total_seats);

        try {
            $published->update(['name' => 'Changed']);
            $this->fail('Published layout must be immutable.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        $draft = $this->service->clonePublishedToDraft($this->room);
        $this->assertSame(2, $draft->version);
        $this->assertSame('draft', $draft->status);
        $this->assertCount(1, $draft->cells);
    }

    public function test_showtime_keeps_old_version_after_new_publish_and_blocks_hard_delete(): void
    {
        $v1 = $this->publishSingleSeatLayout();
        $movieId = DB::table('movies')->insertGetId([
            'title' => 'Layout Movie', 'slug' => 'layout-movie', 'duration' => 90,
            'age_rating' => 'P', 'status' => 'now_showing', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $showtime = Showtime::query()->create([
            'movie_id' => $movieId, 'cinema_id' => $this->room->cinema_id, 'room_id' => $this->room->id,
            'room_layout_id' => $v1->id, 'show_date' => now()->addDay()->toDateString(), 'show_time' => '10:00:00',
            'price' => 50000, 'vip_price' => 70000, 'status' => 'active',
        ]);

        $v2Draft = $this->service->clonePublishedToDraft($this->room);
        $v2 = $this->service->publish($v2Draft);
        $this->assertSame($v1->id, $showtime->fresh()->room_layout_id);
        $this->assertSame($v2->id, $this->service->latestPublishedFor($this->room)->id);

        $this->expectException(LogicException::class);
        $v1->delete();
    }

    public function test_cell_model_rejects_seat_from_another_room_and_out_of_bounds(): void
    {
        $draft = $this->service->createBlankDraft($this->room, null, 2, 2);
        $other = Room::query()->create([
            'cinema_id' => $this->room->cinema_id, 'code' => 'T02', 'name' => 'Other',
            'room_type' => '2D', 'total_seats' => 0, 'status' => 'active',
        ]);
        $seat = Seat::query()->create(['room_id' => $other->id, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1', 'type' => 'normal', 'status' => 'active']);

        try {
            RoomLayoutCell::query()->create(['room_layout_id' => $draft->id, 'x_position' => 1, 'y_position' => 1, 'cell_type' => 'seat', 'seat_id' => $seat->id]);
            $this->fail('Cross-room seat must fail.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        $this->expectException(LogicException::class);
        RoomLayoutCell::query()->create(['room_layout_id' => $draft->id, 'x_position' => 3, 'y_position' => 1, 'cell_type' => 'aisle']);
    }

    public function test_inactive_room_and_oversized_layout_are_rejected(): void
    {
        $this->room->update(['status' => 'inactive']);
        try {
            $this->service->createBlankDraft($this->room);
            $this->fail('Inactive room must fail.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->room->update(['status' => 'active']);
        $this->expectException(ValidationException::class);
        $this->service->createBlankDraft($this->room, null, 30, 41);
    }

    private function publishSingleSeatLayout()
    {
        $draft = $this->service->createBlankDraft($this->room, null, 1, 1);
        $this->service->saveDraft($draft, [
            'name' => 'Version 1', 'rows' => 1, 'columns' => 1, 'screen_position' => 'top',
            'cells' => [['kind' => 'normal', 'x' => 1, 'y' => 1, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1', 'status' => 'active']],
        ]);

        return $this->service->publish($draft);
    }
}
