<?php

namespace Database\Seeders;

use App\Models\Cinema;
use App\Models\Movie;
use App\Services\ShowtimeScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

final class ShowtimeSeeder extends Seeder
{
    public function run(): void
    {
        $movie = Movie::query()->where('status', '!=', 'stopped')->orderBy('id')->first();
        if (! $movie) {
            return;
        }
        $schedule = app(ShowtimeScheduleService::class);
        foreach (Cinema::query()->active()->with(['rooms' => fn ($query) => $query->operational()->whereHas('latestPublishedLayout')->orderBy('id')])->get() as $offset => $cinema) {
            $room = $cinema->rooms->first();
            if (! $room || $room->showtimes()->whereDate('show_date', '>=', now()->toDateString())->exists()) {
                continue;
            }
            $start = CarbonImmutable::now($cinema->timezone)->addDays($offset + 1)->setTime(14, 0);
            $schedule->schedule([
                'movie_id' => $movie->id, 'room_id' => $room->id,
                'show_date' => $start->toDateString(), 'show_time' => $start->format('H:i'),
                'price' => 100000, 'vip_price' => 150000, 'status' => 'active',
            ]);
        }
    }
}
