<?php

namespace Tests\Feature\Seats;

use App\Models\Booking;
use App\Models\BookingSeat;
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

    public function test_couple_pair_requires_matching_status_and_correct_left_right_order(): void
    {
        $draft = $this->service->createBlankDraft($this->room, null, 1, 2);
        $invalid = [
            ['kind' => 'couple', 'x' => 1, 'y' => 1, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1', 'status' => 'active', 'pair_code' => 'A-P1', 'pair_position' => 'right'],
            ['kind' => 'couple', 'x' => 2, 'y' => 1, 'row' => 'A', 'number' => 2, 'seat_code' => 'A2', 'status' => 'maintenance', 'pair_code' => 'A-P1', 'pair_position' => 'left'],
        ];

        try {
            $this->service->saveDraft($draft, ['rows' => 1, 'columns' => 2, 'screen_position' => 'top', 'cells' => $invalid]);
            $this->fail('An inconsistent pair must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cells.0', $exception->errors());
            $this->assertArrayHasKey('cells.1', $exception->errors());
        }
    }

    public function test_booked_couple_cannot_be_converted_but_booking_history_is_preserved(): void
    {
        $draft = $this->service->createBlankDraft($this->room, null, 1, 2);
        $pair = [
            ['kind' => 'couple', 'x' => 1, 'y' => 1, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1', 'status' => 'active', 'pair_code' => 'A-P1', 'pair_position' => 'left'],
            ['kind' => 'couple', 'x' => 2, 'y' => 1, 'row' => 'A', 'number' => 2, 'seat_code' => 'A2', 'status' => 'active', 'pair_code' => 'A-P1', 'pair_position' => 'right'],
        ];
        $this->service->saveDraft($draft, ['rows' => 1, 'columns' => 2, 'screen_position' => 'top', 'cells' => $pair]);
        $movieId = DB::table('movies')->insertGetId([
            'title' => 'History Movie', 'slug' => 'history-movie', 'duration' => 90,
            'age_rating' => 'P', 'status' => 'now_showing', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $showtime = Showtime::query()->create([
            'movie_id' => $movieId, 'cinema_id' => $this->room->cinema_id, 'room_id' => $this->room->id,
            'room_layout_id' => $draft->id, 'show_date' => now()->addDay()->toDateString(), 'show_time' => '10:00:00',
            'price' => 50000, 'vip_price' => 70000, 'status' => 'active',
        ]);
        $booking = Booking::query()->create([
            'showtime_id' => $showtime->id, 'booking_code' => 'PAIR-HISTORY-1', 'total_amount' => 100000,
            'payment_status' => 'paid', 'booking_status' => 'paid',
        ]);
        $left = Seat::query()->where('room_id', $this->room->id)->where('seat_code', 'A1')->firstOrFail();
        BookingSeat::query()->create([
            'booking_id' => $booking->id, 'showtime_id' => $showtime->id, 'seat_id' => $left->id, 'price' => 50000,
        ]);

        $normal = [
            ['kind' => 'normal', 'x' => 1, 'y' => 1, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1', 'status' => 'active'],
            ['kind' => 'normal', 'x' => 2, 'y' => 1, 'row' => 'A', 'number' => 2, 'seat_code' => 'A2', 'status' => 'active'],
        ];
        try {
            $this->service->saveDraft($draft->fresh(), ['rows' => 1, 'columns' => 2, 'screen_position' => 'top', 'cells' => $normal]);
            $this->fail('A booked pair must not be converted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cells.0', $exception->errors());
        }

        try {
            $this->service->saveDraft($draft->fresh(), ['rows' => 1, 'columns' => 2, 'screen_position' => 'top', 'cells' => []]);
            $this->fail('A booked historical seat must not be removed.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Không thể xóa ghế A1', $exception->errors()['cells'][0]);
        }

        $this->assertDatabaseHas('booking_seats', ['booking_id' => $booking->id, 'seat_id' => $left->id]);
        $this->assertDatabaseHas('seats', ['id' => $left->id, 'type' => 'couple', 'pair_code' => 'A-P1']);
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

    public function test_freeform_move_preserves_seat_identity_and_repeated_save_does_not_duplicate_new_codes(): void
    {
        $draft = $this->service->createBlankDraft($this->room, null, 1, 4);
        $saved = $this->service->saveDraft($draft, [
            'rows' => 1, 'columns' => 4, 'screen_position' => 'top',
            'cells' => [
                ['kind' => 'normal', 'x' => 2, 'y' => 1, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1'],
                ['kind' => 'aisle', 'x' => 3, 'y' => 1],
            ],
        ]);
        $a1 = $saved->cells->firstWhere('seat.seat_code', 'A1')->seat;

        $payload = [
            'schema_version' => 3, 'rows' => 1, 'columns' => 6, 'screen_position' => 'top',
            'cells' => [
                ['kind' => 'normal', 'seat_id' => $a1->id, 'x' => 3, 'y' => 1, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1'],
                ['kind' => 'aisle', 'x' => 4, 'y' => 1],
                ['kind' => 'vip', 'x' => 1, 'y' => 1, 'row' => 'A', 'number' => 2, 'seat_code' => 'A2'],
                ['kind' => 'normal', 'x' => 6, 'y' => 1, 'row' => 'A', 'number' => 3, 'seat_code' => 'A3'],
            ],
        ];
        $moved = $this->service->saveDraft($saved, $payload);
        $this->service->saveDraft($moved, $payload);

        $this->assertDatabaseHas('seats', ['id' => $a1->id, 'seat_code' => 'A1', 'x_position' => 3]);
        $this->assertSame(1, Seat::query()->where('room_id', $this->room->id)->where('seat_code', 'A2')->count());
        $this->assertSame(1, Seat::query()->where('room_id', $this->room->id)->where('seat_code', 'A3')->count());
        $this->assertSame([1, 2, 3], Seat::query()->where('room_id', $this->room->id)->orderBy('seat_code')->pluck('number')->all());
    }

    public function test_canvas_cannot_shrink_over_a_meaningful_cell(): void
    {
        $draft = $this->service->createBlankDraft($this->room, null, 1, 4);
        $saved = $this->service->saveDraft($draft, [
            'rows' => 1, 'columns' => 4, 'screen_position' => 'top',
            'cells' => [['kind' => 'aisle', 'x' => 4, 'y' => 1]],
        ]);

        $this->expectException(ValidationException::class);
        $this->service->saveDraft($saved, [
            'rows' => 1, 'columns' => 3, 'screen_position' => 'top', 'cells' => [],
        ]);
    }

    public function test_stale_freeform_draft_is_rejected(): void
    {
        $draft = $this->service->createBlankDraft($this->room, null, 1, 2);

        try {
            $this->service->saveDraft($draft, [
                'schema_version' => 3,
                'expected_updated_at' => '2000-01-01 00:00:00.000000',
                'rows' => 1, 'columns' => 2, 'screen_position' => 'top', 'cells' => [],
            ]);
            $this->fail('A stale draft must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('phiên quản trị khác', $exception->errors()['layout'][0]);
        }
    }

    public function test_publish_capacity_counts_only_active_usable_seat_positions(): void
    {
        $draft = $this->service->createBlankDraft($this->room, null, 1, 4);
        $this->service->saveDraft($draft, [
            'rows' => 1, 'columns' => 4, 'screen_position' => 'top',
            'cells' => [
                ['kind' => 'normal', 'x' => 1, 'y' => 1, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1', 'status' => 'active'],
                ['kind' => 'vip', 'x' => 2, 'y' => 1, 'row' => 'A', 'number' => 2, 'seat_code' => 'A2', 'status' => 'maintenance'],
                ['kind' => 'normal', 'x' => 3, 'y' => 1, 'row' => 'A', 'number' => 3, 'seat_code' => 'A3', 'status' => 'inactive'],
                ['kind' => 'aisle', 'x' => 4, 'y' => 1],
            ],
        ]);

        $this->service->publish($draft->fresh());

        $this->assertSame(1, $this->room->fresh()->total_seats);
    }

    public function test_seat_code_must_match_its_logical_row(): void
    {
        $draft = $this->service->createBlankDraft($this->room, null, 2, 1);

        $this->expectException(ValidationException::class);
        $this->service->saveDraft($draft, [
            'rows' => 2, 'columns' => 1, 'screen_position' => 'top',
            'cells' => [['kind' => 'normal', 'x' => 1, 'y' => 2, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1']],
        ]);
    }

    private function publishSingleSeatLayout()
    {
        $draft = $this->service->createBlankDraft($this->room, null, 1, 1);
        $this->service->saveDraft($draft, [
            'name' => 'Sơ đồ kiểm thử một ghế', 'rows' => 1, 'columns' => 1, 'screen_position' => 'top',
            'cells' => [['kind' => 'normal', 'x' => 1, 'y' => 1, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1', 'status' => 'active']],
        ]);

        return $this->service->publish($draft);
    }
}
