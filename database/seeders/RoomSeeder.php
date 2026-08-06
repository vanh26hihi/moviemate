<?php

namespace Database\Seeders;

use App\Models\Cinema;
use App\Models\Room;
use Illuminate\Database\Seeder;

final class RoomSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Cinema::query()->active()->get() as $cinema) {
            $codes = $cinema->code === 'CG' ? ['P01', 'P02', 'P03'] : [$cinema->code.'01'];
            foreach ($codes as $index => $code) {
                Room::query()->updateOrCreate(
                    ['cinema_id' => $cinema->id, 'code' => $code],
                    [
                        'name' => 'Phòng '.($index + 1),
                        'room_type' => '2D',
                        'total_seats' => 0,
                        'status' => 'active',
                    ],
                );
            }
        }
    }
}
