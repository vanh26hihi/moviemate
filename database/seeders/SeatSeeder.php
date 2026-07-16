<?php

namespace Database\Seeders;

use App\Models\Seat;
use App\Models\Room;
use Illuminate\Database\Seeder;

class SeatSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = Room::all();

        $rows = range('A', 'H');
        $seatsPerRow = 12;
        $vipRows = ['E', 'F', 'G'];

        foreach ($rooms as $room) {
            $total = 0;
            foreach ($rows as $row) {
                for ($num = 1; $num <= $seatsPerRow; $num++) {
                    $type = in_array($row, $vipRows) ? 'vip' : 'normal';
                    Seat::create([
                        'room_id' => $room->id,
                        'row' => $row,
                        'number' => $num,
                        'seat_code' => $row . $num,
                        'type' => $type,
                        'status' => 'active',
                    ]);
                    $total++;
                }
            }
            // Update total seats for the room
            $room->update(['total_seats' => $total]);
        }
    }
}