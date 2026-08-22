<?php

namespace Database\Seeders;

use App\Models\RoomLayoutTemplate;
use App\Services\RoomLayoutTemplateGeometry;
use Illuminate\Database\Seeder;

class RoomLayoutTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            ['code' => 'STANDARD_100', 'name' => 'Tiêu chuẩn 100 ghế', 'rows' => 10, 'columns' => 12, 'aisles' => [6, 7], 'room_type' => 'STANDARD'],
            ['code' => 'VIP_80', 'name' => 'VIP 80 ghế', 'rows' => 8, 'columns' => 12, 'aisles' => [6, 7], 'room_type' => null],
            ['code' => 'COMPACT_48', 'name' => 'Phòng nhỏ 48 ghế', 'rows' => 6, 'columns' => 9, 'aisles' => [5], 'room_type' => null],
        ];
        $geometry = app(RoomLayoutTemplateGeometry::class);
        foreach ($definitions as $definition) {
            $template = RoomLayoutTemplate::query()->firstOrCreate(['code' => $definition['code']], [
                'name' => $definition['name'], 'description' => 'Mẫu khởi tạo R7; có thể chỉnh sửa trước khi áp dụng.',
                'room_type' => $definition['room_type'], 'rows' => $definition['rows'], 'columns' => $definition['columns'],
                'screen_position' => 'top', 'status' => RoomLayoutTemplate::STATUS_ACTIVE,
            ]);
            $needsCoupleUpgrade = in_array($definition['code'], ['STANDARD_100', 'VIP_80'], true)
                && ! $template->cells()->where('seat_type', 'couple')->exists();
            if ($template->cells()->exists() && ! $needsCoupleUpgrade) {
                continue;
            }
            if ($needsCoupleUpgrade && $template->roomLayouts()->exists()) {
                continue;
            }
            if ($needsCoupleUpgrade) {
                $template->cells()->delete();
            }
            $cells = [];
            for ($y = 1; $y <= $definition['rows']; $y++) {
                for ($x = 1; $x <= $definition['columns']; $x++) {
                    if (in_array($x, $definition['aisles'], true)) {
                        $cells[] = ['x' => $x, 'y' => $y, 'cell_type' => 'aisle'];

                        continue;
                    }
                    $couplePair = $y === $definition['rows'] && in_array($definition['code'], ['STANDARD_100', 'VIP_80'], true)
                        ? match ($x) {
                            1, 2 => 'PAIR-1', 3, 4 => 'PAIR-2', 8, 9 => 'PAIR-3', 10, 11 => 'PAIR-4', default => null
                        }
                    : null;
                    $seatType = $couplePair ? 'couple' : ($definition['code'] === 'VIP_80' ? 'vip' : 'normal');
                    $cells[] = ['x' => $x, 'y' => $y, 'cell_type' => 'seat', 'seat_type' => $seatType,
                        'seat_label' => chr(64 + $y).$x, 'pair_key' => $couplePair ? $definition['code'].'-'.$couplePair : null];
                }
            }
            $normalized = $geometry->normalize(['rows' => $definition['rows'], 'columns' => $definition['columns'], 'screen_position' => 'top', 'cells' => $cells]);
            $template->cells()->createMany($normalized['cells']);
        }
    }
}
