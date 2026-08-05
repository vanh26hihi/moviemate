<?php

namespace Database\Seeders;

use App\Models\Movie;
use App\Models\Room;
use App\Services\CinemaContext;
use App\Services\ShowtimeScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class ShowtimeSeeder extends Seeder
{
    public function run(): void
    {
        $movies = Movie::query()->where('status', '!=', 'stopped')->orderBy('id')->get();
        $cinema = app(CinemaContext::class)->current();
        $room = Room::query()->where('cinema_id', $cinema->id)->operational()
            ->whereHas('latestPublishedLayout')->where('code', 'P01')->firstOrFail();
        $schedule = app(ShowtimeScheduleService::class);
        $date = CarbonImmutable::now($schedule->timezone())->addDay()->startOfDay();
        $sequence = 0;

        foreach ($movies as $movie) {
            for ($i = 0; $i < 3; $i++) {
                $start = $date->addDays($sequence++)->setTime(14, 0);
                $schedule->schedule([
                    'movie_id' => $movie->id,
                    'room_id' => $room->id,
                    'show_date' => $start->toDateString(),
                    'show_time' => $start->format('H:i'),
                    'price' => 100000,
                    'vip_price' => 150000,
                    'status' => 'active',
                ]);
            }
        }
    }
}
