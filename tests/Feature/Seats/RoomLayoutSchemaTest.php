<?php

namespace Tests\Feature\Seats;

use App\Models\Cinema;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\RoomLayoutCell;
use App\Models\Seat;
use App\Services\CinemaContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class RoomLayoutSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_two_schema_contains_versioned_layout_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('room_layouts', [
            'room_id', 'version', 'name', 'rows', 'columns', 'screen_position', 'status',
            'published_at', 'created_by', 'updated_by',
        ]));
        $this->assertTrue(Schema::hasColumns('room_layout_cells', [
            'room_layout_id', 'x_position', 'y_position', 'cell_type', 'seat_id',
        ]));
        $this->assertTrue(Schema::hasColumn('showtimes', 'room_layout_id'));
    }

    public function test_database_enforces_unique_version_coordinate_and_seat_per_layout(): void
    {
        $cinema = Cinema::query()->where('canonical_key', CinemaContext::CANONICAL_KEY)->firstOrFail();
        $room = Room::query()->create([
            'cinema_id' => $cinema->id, 'code' => 'S01', 'name' => 'Schema Room',
            'room_type' => '2D', 'width_mm' => 8_000, 'length_mm' => 10_000, 'status' => 'active',
        ]);
        $layout = RoomLayout::query()->create([
            'room_id' => $room->id, 'version' => 1, 'rows' => 2, 'columns' => 2,
            'screen_position' => 'top', 'status' => 'draft',
        ]);
        $seat = Seat::query()->create([
            'room_id' => $room->id, 'row' => 'A', 'number' => 1, 'seat_code' => 'A1',
            'type' => 'normal', 'status' => 'active',
        ]);
        RoomLayoutCell::query()->create([
            'room_layout_id' => $layout->id, 'x_position' => 1, 'y_position' => 1,
            'cell_type' => 'seat', 'seat_id' => $seat->id,
        ]);

        try {
            RoomLayout::query()->create([
                'room_id' => $room->id, 'version' => 1, 'rows' => 2, 'columns' => 2,
                'screen_position' => 'top', 'status' => 'draft',
            ]);
            $this->fail('Duplicate version must fail.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
        try {
            RoomLayoutCell::query()->create([
                'room_layout_id' => $layout->id, 'x_position' => 1, 'y_position' => 1,
                'cell_type' => 'aisle', 'seat_id' => null,
            ]);
            $this->fail('Duplicate coordinate must fail.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->expectException(QueryException::class);
        RoomLayoutCell::query()->create([
            'room_layout_id' => $layout->id, 'x_position' => 2, 'y_position' => 1,
            'cell_type' => 'seat', 'seat_id' => $seat->id,
        ]);
    }

    public function test_blocked_is_a_persisted_structural_cell_without_a_seat(): void
    {
        $cinema = Cinema::query()->where('canonical_key', CinemaContext::CANONICAL_KEY)->firstOrFail();
        $room = Room::query()->create([
            'cinema_id' => $cinema->id, 'code' => 'S02', 'name' => 'Blocked Schema Room',
            'room_type' => '2D', 'width_mm' => 8_000, 'length_mm' => 10_000, 'status' => 'active',
        ]);
        $layout = RoomLayout::query()->create([
            'room_id' => $room->id, 'version' => 1, 'rows' => 1, 'columns' => 2,
            'screen_position' => 'top', 'status' => 'draft',
        ]);
        $blocked = RoomLayoutCell::query()->create([
            'room_layout_id' => $layout->id, 'x_position' => 1, 'y_position' => 1,
            'cell_type' => RoomLayoutCell::TYPE_BLOCKED, 'seat_id' => null,
        ]);

        $this->assertSame(['seat', 'aisle', 'blocked'], RoomLayoutCell::CELL_TYPES);
        $this->assertNull($blocked->seat_id);
        $this->assertDatabaseHas('room_layout_cells', [
            'id' => $blocked->id, 'cell_type' => 'blocked', 'seat_id' => null,
        ]);

        $seat = Seat::query()->create([
            'room_id' => $room->id, 'row' => 'A', 'number' => 2, 'seat_code' => 'A2',
            'type' => 'normal', 'status' => 'active',
        ]);
        $this->expectException(LogicException::class);
        RoomLayoutCell::query()->create([
            'room_layout_id' => $layout->id, 'x_position' => 2, 'y_position' => 1,
            'cell_type' => RoomLayoutCell::TYPE_BLOCKED, 'seat_id' => $seat->id,
        ]);
    }

    public function test_blocked_enum_rollback_refuses_to_discard_existing_blocked_rows(): void
    {
        $cinema = Cinema::query()->where('canonical_key', CinemaContext::CANONICAL_KEY)->firstOrFail();
        $room = Room::query()->create([
            'cinema_id' => $cinema->id, 'code' => 'S03', 'name' => 'Rollback Guard Room',
            'room_type' => '2D', 'width_mm' => 8_000, 'length_mm' => 10_000, 'status' => 'active',
        ]);
        $layout = RoomLayout::query()->create([
            'room_id' => $room->id, 'version' => 1, 'rows' => 1, 'columns' => 1,
            'screen_position' => 'top', 'status' => 'draft',
        ]);
        RoomLayoutCell::query()->create([
            'room_layout_id' => $layout->id, 'x_position' => 1, 'y_position' => 1,
            'cell_type' => RoomLayoutCell::TYPE_BLOCKED, 'seat_id' => null,
        ]);
        $migration = require database_path('migrations/2026_08_14_100000_add_blocked_room_layout_cell_type.php');

        $this->expectException(\RuntimeException::class);
        $migration->down();
    }
}
