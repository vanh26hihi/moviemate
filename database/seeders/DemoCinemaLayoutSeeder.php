<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Services\RoomLayoutService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class DemoCinemaLayoutSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        foreach ([
            'normal' => ['Normal', 0, false],
            'vip' => ['VIP', 20000, false],
            'couple' => ['Couple', 40000, true],
        ] as $code => [$name, $modifier, $pair]) {
            DB::table('seat_types')->updateOrInsert(['code' => $code], [
                'name' => $name, 'slug' => $code, 'price_modifier' => $modifier,
                'is_pair' => $pair, 'status' => true, 'sort_order' => 0,
                'updated_at' => $now, 'created_at' => $now,
            ]);
        }

        $layouts = app(RoomLayoutService::class);
        foreach (Room::query()->operational()->orderBy('id')->get() as $room) {
            if ($room->latestPublishedLayout()->exists()) {
                continue;
            }
            $draft = $layouts->createBlankDraft($room, rows: 3, columns: 4);
            $cells = [];
            foreach (range(1, 3) as $row) {
                foreach (range(1, 4) as $column) {
                    $label = chr(64 + $row);
                    $cells[] = [
                        'kind' => 'normal', 'x' => $column, 'y' => $row,
                        'row' => $label, 'number' => $column,
                        'seat_code' => $label.$column, 'status' => 'active',
                    ];
                }
            }
            $layouts->saveDraft($draft, [
                'name' => 'Sơ đồ demo '.$room->code,
                'rows' => 3, 'columns' => 4, 'screen_position' => 'top', 'cells' => $cells,
            ]);
            $layouts->publish($draft->fresh());
        }
    }
}
