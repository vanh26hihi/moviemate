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
            PresentationFormatSeeder::class,
            RoomSeeder::class,
            RoomLayoutTemplateSeeder::class,
            MovieSeeder::class,
            DemoCinemaLayoutSeeder::class,
            PriceBookSeeder::class,
            ShowtimeSeeder::class,
            DemoUserSeeder::class,
            FoodItemSeeder::class,
            PromotionSeeder::class,
            Phase1TicketOperationsSeeder::class,
        ]);
    }
}
