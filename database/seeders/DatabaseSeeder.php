<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            GenreSeeder::class,
            CinemaSeeder::class,
            RoomTypeSeeder::class,
            RoomSeeder::class,
            RoomLayoutTemplateSeeder::class,
            MovieSeeder::class,
            DemoCinemaLayoutSeeder::class,
            PricingRuleSeeder::class,
            ShowtimeSeeder::class,
            DemoUserSeeder::class,
            FoodItemSeeder::class,
            DiscountCodeSeeder::class,
        ]);
    }
}
