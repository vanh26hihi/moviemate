<?php

namespace Database\Seeders;

use App\Models\PresentationFormat;
use Illuminate\Database\Seeder;

final class PresentationFormatSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => '2D', 'name' => '2D', 'description' => 'Trình chiếu phẳng hai chiều.', 'sort_order' => 10],
            ['code' => '3D', 'name' => '3D', 'description' => 'Trình chiếu lập thể ba chiều.', 'sort_order' => 20],
        ] as $definition) {
            PresentationFormat::query()->updateOrCreate(['code' => $definition['code']], [
                ...$definition,
                'is_active' => true,
            ]);
        }
    }
}
