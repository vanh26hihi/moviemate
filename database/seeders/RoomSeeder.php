<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Services\CinemaContext;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $cinema = app(CinemaContext::class)->current();
        if ($cinema->rooms()->exists()) {
            return;
        }

        foreach ([1, 2, 3] as $number) {
            Room::query()->create([
                'cinema_id' => $cinema->id,
                'code' => 'P0'.$number,
                'name' => 'Phòng '.$number,
                'room_type' => '2D',
                'total_seats' => 0,
                'status' => 'active',
            ]);
        }
    }
}
