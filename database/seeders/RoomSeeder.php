<?php

namespace Database\Seeders;

use App\Models\Cinema;
use App\Models\PresentationFormat;
use App\Models\Room;
use App\Models\RoomType;
use Database\Seeders\Support\DemoPresentationFormatConfiguration;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Seeder;

final class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $includeDefenseData = app()->environment(['local', 'testing']);

        foreach (Cinema::query()->active()->get() as $cinema) {
            $rooms = $cinema->code === 'CG'
                ? [['P01', 'STANDARD'], ['P02', 'STANDARD'], ['P03', $includeDefenseData ? 'IMAX' : 'STANDARD']]
                : [[$cinema->code.'01', 'STANDARD']];
            if ($cinema->code === 'CG' && $includeDefenseData) {
                $rooms[] = ['DEMO', 'STANDARD'];
            }

            foreach ($rooms as $index => [$code, $roomType]) {
                $roomTypeId = RoomType::query()->where('code', $roomType)->value('id')
                    ?? throw (new ModelNotFoundException)->setModel(RoomType::class, [$roomType]);
                $room = Room::query()->updateOrCreate(
                    ['cinema_id' => $cinema->id, 'code' => $code],
                    [
                        'name' => $code === 'DEMO' ? 'Phòng demo bảo vệ' : 'Phòng '.($index + 1),
                        'room_type' => $roomType,
                        'room_type_id' => $roomTypeId,
                        'total_seats' => 0,
                        'status' => 'active',
                    ],
                );

                $capabilityIds = collect(DemoPresentationFormatConfiguration::roomCapabilityCodes($room->setRelation('cinema', $cinema)))
                    ->map(function (string $formatCode): int {
                        return PresentationFormat::query()->where('code', $formatCode)->value('id')
                            ?? throw (new ModelNotFoundException)->setModel(PresentationFormat::class, [$formatCode]);
                    });
                $room->presentationCapabilities()->sync($capabilityIds);
            }
        }
    }
}
