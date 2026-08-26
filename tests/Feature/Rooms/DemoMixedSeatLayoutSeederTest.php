<?php

namespace Tests\Feature\Rooms;

use App\Models\Room;
use App\Models\Showtime;
use Database\Seeders\CinemaSeeder;
use Database\Seeders\DemoCinemaLayoutSeeder;
use Database\Seeders\GenreSeeder;
use Database\Seeders\MovieSeeder;
use Database\Seeders\PresentationFormatSeeder;
use Database\Seeders\PriceBookSeeder;
use Database\Seeders\RoomSeeder;
use Database\Seeders\RoomTypeSeeder;
use Database\Seeders\ShowtimeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DemoMixedSeatLayoutSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_operational_room_layout_contains_normal_vip_and_complete_couple_pairs(): void
    {
        $this->seed([
            CinemaSeeder::class,
            RoomTypeSeeder::class,
            PresentationFormatSeeder::class,
            RoomSeeder::class,
            DemoCinemaLayoutSeeder::class,
        ]);

        $rooms = Room::query()->operational()->with('latestPublishedLayout.cells.seat')->get();

        $this->assertNotEmpty($rooms);
        foreach ($rooms as $room) {
            $layout = $room->latestPublishedLayout;
            $this->assertNotNull($layout, "Room {$room->code} must have a published layout.");

            $seats = $layout->cells->where('cell_type', 'seat')->pluck('seat')->filter();
            $this->assertSame(
                ['couple', 'normal', 'vip'],
                $seats->pluck('type')->unique()->sort()->values()->all(),
                "Room {$room->code} must expose all demo seat types.",
            );

            $couples = $seats->where('type', 'couple')->groupBy('pair_code');
            $this->assertNotEmpty($couples, "Room {$room->code} must contain couple seats.");
            $couples->each(function ($pair) use ($room): void {
                $this->assertCount(2, $pair, "Room {$room->code} has an incomplete couple pair.");
                $this->assertSame(['left', 'right'], $pair->pluck('pair_position')->sort()->values()->all());
            });
        }
    }

    public function test_every_seeded_showtime_uses_a_published_layout_with_all_demo_seat_types(): void
    {
        $this->seed([
            GenreSeeder::class,
            CinemaSeeder::class,
            RoomTypeSeeder::class,
            PresentationFormatSeeder::class,
            RoomSeeder::class,
            MovieSeeder::class,
            DemoCinemaLayoutSeeder::class,
            PriceBookSeeder::class,
            ShowtimeSeeder::class,
        ]);

        $showtimes = Showtime::query()->with('roomLayout.cells.seat')->get();

        $this->assertNotEmpty($showtimes);
        foreach ($showtimes as $showtime) {
            $this->assertSame('published', $showtime->roomLayout?->status);
            $types = $showtime->roomLayout->cells
                ->where('cell_type', 'seat')
                ->pluck('seat.type')
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
            $this->assertSame(['couple', 'normal', 'vip'], $types, "Showtime {$showtime->id} must use a mixed-seat layout.");
        }
    }
}
