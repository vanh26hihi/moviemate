<?php

namespace Tests\Feature\Movies;

use App\Models\Movie;
use App\Models\Review;
use Database\Seeders\GenreSeeder;
use Database\Seeders\MovieSeeder;
use Database\Seeders\PresentationFormatSeeder;
use Database\Seeders\Support\RealMovieCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RealMovieCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_catalog_is_deterministic_distinct_and_offline_safe(): void
    {
        $movies = collect(RealMovieCatalog::movies());

        $this->assertCount(23, $movies);
        $this->assertCount(16, $movies->where('status', Movie::STATUS_NOW_SHOWING));
        $this->assertCount(7, $movies->where('status', Movie::STATUS_COMING_SOON));
        $this->assertSame($movies->count(), $movies->pluck('provider_id')->unique()->count());
        $this->assertSame($movies->count(), $movies->pluck('poster')->unique()->count());
        $this->assertSame($movies->count(), $movies->pluck('cover_image')->unique()->count());

        foreach ($movies as $movie) {
            $this->assertSame(RealMovieCatalog::PROVIDER, $movie['provider']);
            $this->assertSame($movie['poster'], Movie::trustedRemoteImageUrl($movie['poster']));
            $this->assertSame($movie['cover_image'], Movie::trustedRemoteImageUrl($movie['cover_image']));
            $this->assertNotSame($movie['poster'], $movie['cover_image']);
            $this->assertStringNotContainsString('generated', strtolower($movie['poster'].$movie['cover_image']));
            $this->assertStringNotContainsString('ai-art', strtolower($movie['poster'].$movie['cover_image']));
            $this->assertStringNotContainsString('momo', strtolower($movie['poster'].$movie['cover_image']));
        }
    }

    public function test_seeder_preserves_fictional_ids_without_real_media_and_starts_real_ratings_at_zero(): void
    {
        $this->seed([GenreSeeder::class, PresentationFormatSeeder::class, MovieSeeder::class]);

        $fictionalTitles = [
            'The Great Adventure', 'Love in Spring', 'Laugh Out Loud', 'Space Odyssey',
            'Haunted Night', 'Family Tales', 'Future Tech', 'Mystery Manor',
        ];
        $fictionalIds = Movie::query()->whereIn('title', $fictionalTitles)->pluck('id', 'slug')->all();

        $this->seed([GenreSeeder::class, PresentationFormatSeeder::class, MovieSeeder::class]);

        $this->assertSame(31, Movie::query()->count());
        $this->assertSame($fictionalIds, Movie::query()->whereIn('title', $fictionalTitles)->pluck('id', 'slug')->all());
        $this->assertSame(8, Movie::query()->whereIn('title', $fictionalTitles)->where('status', Movie::STATUS_INACTIVE)->count());
        $this->assertSame(8, Movie::query()->whereIn('title', $fictionalTitles)->whereNull('poster')->whereNull('cover_image')->count());

        foreach (RealMovieCatalog::movies() as $definition) {
            $movie = Movie::query()->where('slug', Str::slug($definition['title']))
                ->withAvg('reviews', 'rating')->withCount('reviews')->sole();
            $this->assertSame($definition['poster'], $movie->poster_url);
            $this->assertSame($definition['cover_image'], $movie->cover_url);
            $this->assertSame(0, $movie->reviews_count);
            $this->assertNull($movie->reviews_avg_rating);
        }

        $this->assertSame(0, Review::query()->count());
    }
}
