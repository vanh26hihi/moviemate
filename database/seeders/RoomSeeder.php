<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\Cinema;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $cinemas = Cinema::all();

        foreach ($cinemas as $cinema) {
            for ($i = 1; $i <= 2; $i++) {
                Room::create([
                    'cinema_id' => $cinema->id,
                    'name' => "Room {$i}",
                    'room_type' => '2D',
                    'total_seats' => 0, // will be updated after seats are created
                    'status' => 'active',
                ]);
            }
        }
    }
}