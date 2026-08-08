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
            $isDefenseRoom = app()->environment(['local', 'testing']) && $room->code === 'DEMO';
            $rows = $isDefenseRoom ? 4 : 3;
            $columns = $isDefenseRoom ? 8 : 4;
            $draft = $layouts->createBlankDraft($room, rows: $rows, columns: $columns);
            $cells = [];
            foreach (range(1, $rows) as $row) {
                foreach (range(1, $columns) as $column) {
                    $label = chr(64 + $row);
                    if ($isDefenseRoom && $column === 5) {
                        $cells[] = ['kind' => 'aisle', 'x' => $column, 'y' => $row];

                        continue;
                    }
                    $isCouple = $isDefenseRoom && $row === 3 && in_array($column, [1, 2], true);
                    $cells[] = [
                        'kind' => $isCouple ? 'couple' : 'normal', 'x' => $column, 'y' => $row,
                        'row' => $label, 'number' => $column,
                        'seat_code' => $label.$column, 'status' => 'active',
                        'pair_code' => $isCouple ? 'DEMO-C-PAIR-1' : null,
                        'pair_position' => $isCouple ? ($column === 1 ? 'left' : 'right') : null,
                    ];
                }
            }
            $layouts->saveDraft($draft, [
                'name' => 'Sơ đồ demo '.$room->code,
                'rows' => $rows, 'columns' => $columns, 'screen_position' => 'top', 'cells' => $cells,
            ]);
            $layouts->publish($draft->fresh());
        }
    }
}
