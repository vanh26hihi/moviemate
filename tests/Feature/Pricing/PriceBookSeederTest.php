<?php

namespace Tests\Feature\Pricing;

use App\Models\PriceBook;
use App\Models\PriceBookVersion;
use Database\Seeders\CinemaSeeder;
use Database\Seeders\DemoCinemaLayoutSeeder;
use Database\Seeders\PresentationFormatSeeder;
use Database\Seeders\PriceBookSeeder;
use Database\Seeders\RoomSeeder;
use Database\Seeders\RoomTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceBookSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_book_seed_is_chain_wide_published_coherent_and_idempotent(): void
    {
        $this->seed([
            CinemaSeeder::class,
            RoomTypeSeeder::class,
            PresentationFormatSeeder::class,
            RoomSeeder::class,
            DemoCinemaLayoutSeeder::class,
            PriceBookSeeder::class,
        ]);
        $this->seed(PriceBookSeeder::class);

        $book = PriceBook::query()->with('versions.adjustments')->sole();
        $version = $book->versions->sole();
        $this->assertSame(PriceBook::CHAIN_CODE, $book->code);
        $this->assertSame(PriceBookVersion::STATUS_PUBLISHED, $version->status);
        $this->assertSame(80_000, $version->base_price_vnd);
        $this->assertSame('2026-01-01', $version->effective_from->toDateString());
        $this->assertSame('2030-01-01', $version->effective_until->toDateString());
        $this->assertSame(5, $version->adjustments->count());
        $this->assertSame([30_000, 80_000], $version->adjustments->where('dimension', 'seat_type')->pluck('amount_vnd')->sort()->values()->all());
        $this->assertSame(15_000, $version->adjustments->firstWhere('dimension', 'time_window')->amount_vnd);
        $this->assertSame(10_000, $version->adjustments->firstWhere('dimension', 'weekend')->amount_vnd);
        $this->assertSame(20_000, $version->adjustments->firstWhere('dimension', 'holiday')->amount_vnd);
        $this->assertSame(0, $version->adjustments->whereIn('dimension', ['room_type', 'cinema', 'room'])->count());
    }
}
