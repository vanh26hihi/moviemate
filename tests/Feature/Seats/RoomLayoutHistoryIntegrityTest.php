<?php

namespace Tests\Feature\Seats;

use App\Models\Movie;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\RoomLayoutCell;
use App\Models\Seat;
use App\Models\Showtime;
use App\Services\RoomLayoutService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use RuntimeException;
use Tests\TestCase;

final class RoomLayoutHistoryIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_requires_a_same_room_layout_reference_and_restricts_history_deletion(): void
    {
        $column = collect(Schema::getColumns('showtimes'))->firstWhere('name', 'room_layout_id');
        $index = collect(Schema::getIndexes('room_layouts'))->first(
            fn (array $candidate): bool => $candidate['columns'] === ['room_id', 'id'] && $candidate['unique'],
        );
        $foreign = collect(Schema::getForeignKeys('showtimes'))->first(
            fn (array $candidate): bool => $candidate['columns'] === ['room_id', 'room_layout_id'],
        );

        $this->assertFalse($column['nullable']);
        $this->assertSame('room_layouts_room_id_id_unique', $index['name']);
        $this->assertSame(['room_id', 'id'], $foreign['foreign_columns']);
        $this->assertSame('restrict', strtolower($foreign['on_delete']));
        $this->assertSame('restrict', strtolower($foreign['on_update']));

        [$room, $layout] = $this->layoutFixture(RoomLayout::STATUS_PUBLISHED);
        $showtime = $this->showtimeFixture($room, $layout);

        $this->assertQueryFails(fn () => DB::table('showtimes')->where('id', $showtime->id)->update(['room_layout_id' => null]));
        $invalidInsert = $showtime->getAttributes();
        unset($invalidInsert['id']);
        $invalidInsert['show_time'] = '11:00:00';
        $invalidInsert['room_layout_id'] = null;
        $this->assertQueryFails(fn () => DB::table('showtimes')->insert($invalidInsert));
        $this->assertQueryFails(fn () => DB::table('room_layouts')->where('id', $layout->id)->delete());
    }

    public function test_composite_foreign_key_rejects_cross_room_insert_and_updates(): void
    {
        [$roomA, $layoutA] = $this->layoutFixture(RoomLayout::STATUS_PUBLISHED);
        [$roomB, $layoutB] = $this->layoutFixture(RoomLayout::STATUS_PUBLISHED);
        $showtime = $this->showtimeFixture($roomA, $layoutA);

        $this->assertQueryFails(fn () => DB::table('showtimes')->where('id', $showtime->id)->update([
            'room_layout_id' => $layoutB->id,
        ]));
        $this->assertQueryFails(fn () => DB::table('showtimes')->where('id', $showtime->id)->update([
            'room_id' => $roomB->id,
        ]));

        $this->assertSame(1, DB::table('showtimes')->where('id', $showtime->id)->update([
            'room_id' => $roomB->id,
            'room_layout_id' => $layoutB->id,
        ]));
        $this->assertDatabaseHas('showtimes', [
            'id' => $showtime->id,
            'room_id' => $roomB->id,
            'room_layout_id' => $layoutB->id,
        ]);
    }

    public function test_database_triggers_exist_for_all_structural_mutations(): void
    {
        $names = collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'trigger'"))->pluck('name');

        $this->assertEqualsCanonicalizing([
            'room_layout_cells_prevent_immutable_insert',
            'room_layout_cells_prevent_immutable_update',
            'room_layout_cells_prevent_immutable_delete',
            'room_layouts_prevent_structural_mutation',
        ], $names->filter(fn (string $name): bool => str_starts_with($name, 'room_layout'))->all());
    }

    public function test_draft_cells_allow_raw_insert_update_and_delete(): void
    {
        [, $layout] = $this->layoutFixture(RoomLayout::STATUS_DRAFT);
        $cellId = DB::table('room_layout_cells')->insertGetId($this->cellAttributes($layout, 'blocked'));

        $this->assertSame(1, DB::table('room_layout_cells')->where('id', $cellId)->update(['cell_type' => 'aisle']));
        $this->assertSame(1, DB::table('room_layout_cells')->where('id', $cellId)->delete());
    }

    public function test_published_and_retired_cells_reject_raw_insert_update_and_delete(): void
    {
        foreach ([RoomLayout::STATUS_PUBLISHED, RoomLayout::STATUS_RETIRED] as $status) {
            [, $layout, $cell] = $this->layoutFixture($status, withCell: true);

            foreach (['seat', 'aisle', 'blocked'] as $type) {
                $attributes = $this->cellAttributes($layout, $type, 2, $type === 'seat');
                $this->assertQueryFails(fn () => DB::table('room_layout_cells')->insert($attributes));
            }

            $this->assertQueryFails(fn () => DB::table('room_layout_cells')->where('id', $cell->id)->update([
                'cell_type' => 'aisle',
                'seat_id' => null,
            ]));
            $this->assertQueryFails(fn () => DB::table('room_layout_cells')->where('id', $cell->id)->delete());
            $this->assertDatabaseHas('room_layout_cells', ['id' => $cell->id, 'cell_type' => 'blocked']);
        }
    }

    public function test_application_guard_rejects_every_cell_mutation_outside_draft(): void
    {
        foreach ([RoomLayout::STATUS_PUBLISHED, RoomLayout::STATUS_RETIRED] as $status) {
            [, $layout, $cell] = $this->layoutFixture($status, withCell: true);

            $this->assertLogicFails(fn () => RoomLayoutCell::query()->create($this->cellAttributes($layout, 'aisle', 2)));
            $this->assertLogicFails(function () use ($cell): void {
                $cell->cell_type = 'aisle';
                $cell->save();
            });
            $this->assertLogicFails(fn () => $cell->delete());
        }
    }

    public function test_non_draft_parent_structural_fields_and_reopening_are_database_forbidden(): void
    {
        [$otherRoom] = $this->layoutFixture(RoomLayout::STATUS_DRAFT);

        foreach ([RoomLayout::STATUS_PUBLISHED, RoomLayout::STATUS_RETIRED] as $status) {
            [, $layout] = $this->layoutFixture($status);
            foreach ([
                'room_id' => $otherRoom->id,
                'version' => $layout->version + 100,
                'rows' => $layout->rows + 1,
                'columns' => $layout->columns + 1,
                'screen_position' => 'bottom',
                'status' => RoomLayout::STATUS_DRAFT,
            ] as $field => $value) {
                $this->assertQueryFails(fn () => DB::table('room_layouts')->where('id', $layout->id)->update([$field => $value]));
            }
        }
    }

    public function test_published_layout_can_retire_without_changing_its_structure(): void
    {
        [, $layout] = $this->layoutFixture(RoomLayout::STATUS_PUBLISHED);
        $before = $layout->only(RoomLayout::STRUCTURAL_FIELDS);

        $layout->status = RoomLayout::STATUS_RETIRED;
        $layout->save();

        $this->assertSame(RoomLayout::STATUS_RETIRED, $layout->fresh()->status);
        $this->assertSame($before, $layout->fresh()->only(RoomLayout::STRUCTURAL_FIELDS));
        $layout->name = 'Retirement audit label';
        $layout->save();
        $this->assertSame('Retirement audit label', $layout->fresh()->name);
        $this->assertLogicFails(function () use ($layout): void {
            $layout->status = RoomLayout::STATUS_DRAFT;
            $layout->save();
        });
    }

    public function test_authoritative_draft_save_does_not_select_the_parent_once_per_cell(): void
    {
        $room = Room::factory()->create();
        foreach ([['normal', false], ['vip', false], ['couple', true]] as [$code, $isPair]) {
            DB::table('seat_types')->insert([
                'name' => ucfirst($code),
                'code' => $code,
                'slug' => $code,
                'price_modifier' => 0,
                'is_pair' => $isPair,
                'status' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $service = app(RoomLayoutService::class);
        $draft = $service->createBlankDraft($room, rows: 10, columns: 10);

        $one = $this->roomLayoutSelectCount(fn () => $service->saveDraft($draft, [
            'rows' => 10,
            'columns' => 10,
            'screen_position' => 'top',
            'cells' => [['kind' => 'blocked', 'x' => 1, 'y' => 1]],
        ]));
        $manyCells = collect(range(1, 10))->flatMap(fn (int $y) => collect(range(1, 10))->map(
            fn (int $x): array => ['kind' => 'blocked', 'x' => $x, 'y' => $y],
        ))->all();
        $draft->refresh();
        $many = $this->roomLayoutSelectCount(fn () => $service->saveDraft($draft, [
            'rows' => 10,
            'columns' => 10,
            'screen_position' => 'top',
            'cells' => $manyCells,
        ]));

        fwrite(STDERR, "PHASE6D_PARENT_SELECTS one={$one} many={$many}".PHP_EOL);
        $this->assertSame($one, $many);
        $this->assertLessThanOrEqual(2, $many);
        $this->assertSame(100, $draft->cells()->count());
    }

    public function test_cancelled_and_finished_showtimes_remain_pinned_when_v2_is_published(): void
    {
        [$room, $v1] = $this->layoutFixture(RoomLayout::STATUS_PUBLISHED);
        $cancelled = $this->showtimeFixture($room, $v1);
        $finished = $this->showtimeFixture($room, $v1);
        $cancelled->update(['status' => 'cancelled']);
        $finished->update(['status' => 'finished', 'show_time' => '12:00:00']);
        $v2 = RoomLayout::query()->create([
            'room_id' => $room->id,
            'version' => 2,
            'rows' => 2,
            'columns' => 3,
            'screen_position' => 'top',
            'status' => RoomLayout::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $this->assertSame($v1->id, $cancelled->fresh()->room_layout_id);
        $this->assertSame($v1->id, $finished->fresh()->room_layout_id);
        $this->assertNotSame($v2->id, $cancelled->fresh()->room_layout_id);
    }

    public function test_migration_preflight_refuses_null_history_without_guessing_a_layout(): void
    {
        $migration = require database_path('migrations/2026_08_14_200000_harden_room_layout_history_integrity.php');
        [$room, $layout] = $this->layoutFixture(RoomLayout::STATUS_PUBLISHED);
        $showtime = $this->showtimeFixture($room, $layout);
        $migration->down();
        DB::table('showtimes')->where('id', $showtime->id)->update(['room_layout_id' => null]);

        try {
            $migration->up();
            $this->fail('Invalid history must stop the hardening migration.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('null_layout=1', $exception->getMessage());
            $this->assertNull(DB::table('showtimes')->where('id', $showtime->id)->value('room_layout_id'));
        } finally {
            DB::table('showtimes')->where('id', $showtime->id)->update(['room_layout_id' => $layout->id]);
            $migration->up();
        }

        $this->assertFalse(collect(Schema::getColumns('showtimes'))->firstWhere('name', 'room_layout_id')['nullable']);
    }

    private function layoutFixture(string $status, bool $withCell = false): array
    {
        $room = Room::factory()->create();
        $layout = RoomLayout::query()->create([
            'room_id' => $room->id,
            'version' => 1,
            'name' => 'Integrity fixture',
            'rows' => 2,
            'columns' => 3,
            'screen_position' => 'top',
            'status' => RoomLayout::STATUS_DRAFT,
        ]);
        $cell = null;
        if ($withCell) {
            $cell = RoomLayoutCell::query()->create($this->cellAttributes($layout, 'blocked'));
        }
        if ($status !== RoomLayout::STATUS_DRAFT) {
            DB::table('room_layouts')->where('id', $layout->id)->update([
                'status' => $status,
                'published_at' => now(),
            ]);
            $layout->refresh();
        }

        return [$room, $layout, $cell];
    }

    private function cellAttributes(RoomLayout $layout, string $type, int $x = 1, bool $withSeat = false): array
    {
        $seatId = null;
        if ($withSeat) {
            $seatId = Seat::query()->create([
                'room_id' => $layout->room_id,
                'row' => 'A',
                'number' => $x,
                'seat_code' => "A{$x}",
                'type' => 'normal',
                'status' => 'active',
            ])->id;
        }

        return [
            'room_layout_id' => $layout->id,
            'x_position' => $x,
            'y_position' => 1,
            'cell_type' => $type,
            'seat_id' => $seatId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function showtimeFixture(Room $room, RoomLayout $layout): Showtime
    {
        $movie = Movie::query()->create([
            'title' => 'Integrity movie '.fake()->uuid(),
            'slug' => 'integrity-movie-'.fake()->uuid(),
            'duration' => 90,
            'age_rating' => 'P',
            'status' => 'now_showing',
        ]);

        return Showtime::query()->create([
            'movie_id' => $movie->id,
            'cinema_id' => $room->cinema_id,
            'room_id' => $room->id,
            'room_layout_id' => $layout->id,
            'presentation_format_id' => $this->presentationFormatFixture($movie, $room)->id,
            'show_date' => now()->addDay()->toDateString(),
            'show_time' => '10:00:00',
            'price' => 50_000,
            'vip_price' => 70_000,
            'status' => 'active',
        ]);
    }

    private function assertQueryFails(callable $operation): void
    {
        try {
            $operation();
            $this->fail('The database integrity guard should reject this write.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
    }

    private function assertLogicFails(callable $operation): void
    {
        try {
            $operation();
            $this->fail('The application integrity guard should reject this write.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }
    }

    private function roomLayoutSelectCount(callable $operation): int
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $operation();
        $count = collect(DB::getQueryLog())->filter(
            fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select')
                && str_contains(strtolower($query['query']), 'room_layouts'),
        )->count();
        DB::disableQueryLog();

        return $count;
    }
}
