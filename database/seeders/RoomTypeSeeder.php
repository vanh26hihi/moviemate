<?php

namespace Database\Seeders;

use App\Models\RoomType;
use Illuminate\Database\Seeder;

final class RoomTypeSeeder extends Seeder
{
    public function run(): void
    {
        RoomType::query()->whereIn('code', ['2D', '3D'])->delete();

        foreach ([['STANDARD', 'Tiêu chuẩn', 10], ['IMAX', 'IMAX', 20]] as [$code, $name, $sortOrder]) {
            $roomType = RoomType::query()->firstOrNew(['code' => $code]);
            $roomType->forceFill([
                'slug' => $code,
                'name' => $name,
                'description' => null,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ])->save();
        }
    }
}
