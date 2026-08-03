<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SeatSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->warn('SeatSeeder đã deprecated. Dùng moviemate:rebuild-seat-layouts sau khi review dry-run.');
    }
}
