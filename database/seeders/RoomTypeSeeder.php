<?php

namespace Database\Seeders;

use App\Models\RoomType;
use Illuminate\Database\Seeder;

final class RoomTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['2D', 10], ['3D', 20], ['IMAX', 30]] as [$code, $sortOrder]) {
            RoomType::query()->updateOrCreate(['code' => $code], [
                'name' => $code,
                'description' => null,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]);
        }
    }
}
