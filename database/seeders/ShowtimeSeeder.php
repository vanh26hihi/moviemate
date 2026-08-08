<?php

namespace Database\Seeders;

use App\Exceptions\ShowtimeScheduleException;
use App\Models\Cinema;
use App\Models\Movie;
use App\Services\ShowtimeScheduleService;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\RealMovieCatalog;
use Illuminate\Database\Seeder;

final class ShowtimeSeeder extends Seeder
{
    public function run(): void
    {
        $movies = Movie::query()->whereIn('slug', RealMovieCatalog::slugs(Movie::STATUS_NOW_SHOWING))
            ->where('status', Movie::STATUS_NOW_SHOWING)->orderBy('id')->get();
        if ($movies->isEmpty()) {
            return;
        }
        $schedule = app(ShowtimeScheduleService::class);
        foreach (Cinema::query()->active()->with(['rooms' => fn ($query) => $query->operational()->whereHas('latestPublishedLayout')->orderBy('id')])->get() as $cinemaOffset => $cinema) {
            if ($cinema->rooms->isEmpty()) {
                continue;
            }
            foreach (range(1, 10) as $dayOffset) {
                $date = CarbonImmutable::now($cinema->timezone)->addDays($dayOffset)->toDateString();
                foreach ($cinema->rooms as $roomOffset => $room) {
                    foreach (['09:00', '12:45', '16:30', '20:15'] as $slotOffset => $time) {
                        if ($room->showtimes()->whereDate('show_date', $date)->whereTime('show_time', $time)->exists()) {
                            continue;
                        }

                        $movie = $movies[($cinemaOffset + $roomOffset + $dayOffset + $slotOffset) % $movies->count()];
                        try {
                            $schedule->schedule([
                                'movie_id' => $movie->id, 'room_id' => $room->id,
                                'show_date' => $date, 'show_time' => $time,
                                'status' => 'active',
                            ]);
                        } catch (ShowtimeScheduleException) {
                            // Existing or longer films may occupy this window; preserve them and continue.
                        }
                    }
                }
            }
        }
    }
}
