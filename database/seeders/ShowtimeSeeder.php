<?php

namespace Database\Seeders;

use App\Exceptions\ShowtimeScheduleException;
use App\Models\Cinema;
use App\Models\Movie;
use App\Services\ShowtimeScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

final class ShowtimeSeeder extends Seeder
{
    public function run(): void
    {
        $movies = Movie::query()->whereIn('status', Movie::SCHEDULABLE_STATUSES)->orderBy('id')->limit(3)->get();
        if ($movies->isEmpty()) {
            return;
        }
        $schedule = app(ShowtimeScheduleService::class);
        foreach (Cinema::query()->active()->with(['rooms' => fn ($query) => $query->operational()->whereHas('latestPublishedLayout')->orderBy('id')])->get() as $offset => $cinema) {
            if ($cinema->rooms->isEmpty()) {
                continue;
            }
            foreach (range(1, 3) as $dayOffset) {
                $date = CarbonImmutable::now($cinema->timezone)->addDays($dayOffset)->toDateString();
                if ($cinema->showtimes()->where('status', 'active')->whereDate('show_date', $date)->exists()) {
                    continue;
                }
                $movie = $movies[($offset + $dayOffset - 1) % $movies->count()];
                foreach ($cinema->rooms as $room) {
                    foreach ([10, 14, 18, 21] as $hour) {
                        try {
                            $schedule->schedule([
                                'movie_id' => $movie->id, 'room_id' => $room->id,
                                'show_date' => $date, 'show_time' => sprintf('%02d:00', $hour),
                                'status' => 'active',
                            ]);

                            continue 3;
                        } catch (ShowtimeScheduleException) {
                            // Try the next demo-safe slot; existing schedules remain untouched.
                        }
                    }
                }
            }
        }
    }
}
