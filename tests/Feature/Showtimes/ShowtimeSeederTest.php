<?php

namespace Tests\Feature\Showtimes;

use App\Models\Cinema;
use App\Models\Movie;
use App\Models\PriceBook;
use App\Models\Room;
use App\Models\Showtime;
use App\Services\ShowtimeScheduleService;
use Carbon\CarbonImmutable;
use Database\Seeders\CinemaSeeder;
use Database\Seeders\DemoCinemaLayoutSeeder;
use Database\Seeders\GenreSeeder;
use Database\Seeders\MovieSeeder;
use Database\Seeders\PresentationFormatSeeder;
use Database\Seeders\PriceBookSeeder;
use Database\Seeders\RoomSeeder;
use Database\Seeders\RoomTypeSeeder;
use Database\Seeders\ShowtimeSeeder;
use Database\Seeders\Support\RealMovieCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ShowtimeSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-12 08:00:00', 'Asia/Ho_Chi_Minh'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_demo_showtimes_are_multi_date_and_idempotent_even_when_a_slot_already_exists(): void
    {
        $this->seed([
            GenreSeeder::class,
            CinemaSeeder::class,
            RoomTypeSeeder::class,
            PresentationFormatSeeder::class,
            RoomSeeder::class,
            MovieSeeder::class,
            DemoCinemaLayoutSeeder::class,
        ]);
        if (! PriceBook::query()->exists()) {
            $this->seed(PriceBookSeeder::class);
        }
        $this->seed(ShowtimeSeeder::class);
        $firstIds = Showtime::query()->orderBy('id')->pluck('id')->all();

        $this->seed(ShowtimeSeeder::class);

        $this->assertSame($firstIds, Showtime::query()->orderBy('id')->pluck('id')->all());
        Cinema::query()->active()->each(function (Cinema $cinema): void {
            $this->assertGreaterThanOrEqual(
                10,
                $cinema->showtimes()->where('status', 'active')->distinct()->count('show_date'),
            );
        });

        $this->assertSame(
            Room::query()->operational()->whereHas('latestPublishedLayout')->count(),
            Showtime::query()->where('status', 'active')->distinct()->count('room_id'),
        );
        $this->assertSame(2, Showtime::query()->join('rooms', 'rooms.id', '=', 'showtimes.room_id')->distinct()->count('rooms.room_type'));
        $this->assertSame(0, Showtime::query()->whereNull('presentation_format_id')->count());
        $this->assertGreaterThan(0, Showtime::query()->whereHas('presentationFormat', fn ($query) => $query->where('code', '3D'))->count());
        $this->assertSame(0, Showtime::query()->whereHas('movie', fn ($query) => $query->where('status', Movie::STATUS_COMING_SOON))->count());

        $schedule = app(ShowtimeScheduleService::class);
        Showtime::query()->with(['movie', 'room.cinema'])->orderBy('room_id')->orderBy('show_date')->orderBy('show_time')
            ->get()->groupBy('room_id')->each(function ($roomShowtimes) use ($schedule): void {
                $windows = $roomShowtimes->map(fn (Showtime $showtime) => $schedule->windowFor($showtime))->values();
                $hasOverlap = false;
                foreach ($windows as $index => $window) {
                    foreach ($windows->slice($index + 1) as $candidate) {
                        $hasOverlap = $hasOverlap || $window->overlaps($candidate);
                    }
                }
                $this->assertFalse($hasOverlap);
            });

        $this->assertNotEmpty(RealMovieCatalog::slugs(Movie::STATUS_NOW_SHOWING));
    }
}
