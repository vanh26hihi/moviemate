<?php

namespace Database\Seeders;

use App\Models\Cinema;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

final class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $includeDefenseData = app()->environment(['local', 'testing']);

        foreach (Cinema::query()->active()->get() as $cinema) {
            $rooms = $cinema->code === 'CG'
                ? [['P01', '2D'], ['P02', $includeDefenseData ? '3D' : '2D'], ['P03', $includeDefenseData ? 'IMAX' : '2D']]
                : [[$cinema->code.'01', '2D']];
            if ($cinema->code === 'CG' && $includeDefenseData) {
                $rooms[] = ['DEMO', '3D'];
            }

            foreach ($rooms as $index => [$code, $roomType]) {
                Room::query()->updateOrCreate(
                    ['cinema_id' => $cinema->id, 'code' => $code],
                    [
                        'name' => $code === 'DEMO' ? 'Phòng demo bảo vệ' : 'Phòng '.($index + 1),
                        'room_type' => $roomType,
                        'room_type_id' => RoomType::query()->where('code', $roomType)->value('id'),
                        'total_seats' => 0,
                        'status' => 'active',
                    ],
                );
            }
        }
    }
}
