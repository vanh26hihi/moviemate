<?php

namespace Tests\Feature\Showtimes;

use App\Models\Cinema;
use App\Models\Showtime;
use Database\Seeders\CinemaSeeder;
use Database\Seeders\DemoCinemaLayoutSeeder;
use Database\Seeders\GenreSeeder;
use Database\Seeders\MovieSeeder;
use Database\Seeders\PricingRuleSeeder;
use Database\Seeders\RoomSeeder;
use Database\Seeders\ShowtimeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ShowtimeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_showtimes_are_multi_date_and_idempotent_even_when_a_slot_already_exists(): void
    {
        $this->seed([
            GenreSeeder::class,
            CinemaSeeder::class,
            RoomSeeder::class,
            MovieSeeder::class,
            DemoCinemaLayoutSeeder::class,
            PricingRuleSeeder::class,
            ShowtimeSeeder::class,
        ]);
        $firstIds = Showtime::query()->orderBy('id')->pluck('id')->all();

        $this->seed(ShowtimeSeeder::class);

        $this->assertSame($firstIds, Showtime::query()->orderBy('id')->pluck('id')->all());
        Cinema::query()->active()->each(function (Cinema $cinema): void {
            $this->assertGreaterThanOrEqual(
                3,
                $cinema->showtimes()->where('status', 'active')->distinct()->count('show_date'),
            );
        });
    }
}
