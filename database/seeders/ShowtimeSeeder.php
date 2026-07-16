<?php

namespace Database\Seeders;

use App\Models\Showtime;
use App\Models\Movie;
use App\Models\Cinema;
use App\Models\Room;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ShowtimeSeeder extends Seeder
{
    public function run(): void
    {
        $movies = Movie::all();
        $cinemas = Cinema::all();

        foreach ($movies as $movie) {
            // Use first cinema and its first room for simplicity
            $cinema = $cinemas->first();
            if (! $cinema) {
                continue;
            }

            $room = $cinema->rooms()->first();

            if (!$room) {
                continue;
            }

            // Create three showtimes for each movie
            for ($i = 0; $i < 3; $i++) {
                $date = Carbon::now()->addDays($i + 1)->toDateString();
                $time = '14:00:00';

                Showtime::create([
                    'movie_id' => $movie->id,
                    'cinema_id' => $cinema->id,
                    'room_id' => $room->id,
                    'show_date' => $date,
                    'show_time' => $time,
                    'price' => 100000, // VND
                    'vip_price' => 150000,
                    'status' => 'active',
                ]);
            }
        }
    }
}
