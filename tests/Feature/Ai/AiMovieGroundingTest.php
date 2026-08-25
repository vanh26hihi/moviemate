<?php

namespace Tests\Feature\Ai;

use App\Models\Movie;
use App\Services\CustomerMovieReadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiMovieGroundingTest extends TestCase
{
    use RefreshDatabase;

    public function test_stored_status_and_unique_identity_remain_authoritative_with_duplicate_titles(): void
    {
        $upcoming = Movie::query()->create([
            'title' => 'Trùng tên', 'slug' => 'trung-ten-upcoming', 'duration' => 95,
            'release_date' => now()->subYear()->toDateString(), 'status' => Movie::STATUS_COMING_SOON,
        ]);
        $showing = Movie::query()->create([
            'title' => 'Trùng tên', 'slug' => 'trung-ten-showing', 'duration' => 110,
            'release_date' => now()->addYear()->toDateString(), 'status' => Movie::STATUS_NOW_SHOWING,
        ]);

        $service = app(CustomerMovieReadService::class);
        $results = $service->search(['query' => 'Trùng tên']);

        $this->assertCount(2, $results);
        $this->assertSame(Movie::STATUS_COMING_SOON, $results->firstWhere('id', $upcoming->id)['status']);
        $this->assertSame(Movie::STATUS_NOW_SHOWING, $results->firstWhere('id', $showing->id)['status']);
        $this->assertSame($upcoming->id, $service->details(slug: 'trung-ten-upcoming')['id']);
        $this->assertSame($showing->id, $service->details(movieId: $showing->id)['id']);
        $this->assertFalse($service->details(movieId: $upcoming->id)['booking_available']);
    }

    public function test_movie_results_are_capped_and_only_expose_customer_safe_fields(): void
    {
        foreach (range(1, 15) as $index) {
            Movie::query()->create([
                'title' => "AI Movie {$index}", 'slug' => "ai-movie-{$index}",
                'duration' => 80 + $index, 'status' => Movie::STATUS_NOW_SHOWING,
            ]);
        }

        $results = app(CustomerMovieReadService::class)->search(limit: 99);
        $this->assertCount(CustomerMovieReadService::MAX_RESULTS, $results);
        $this->assertSame([
            'id', 'title', 'slug', 'status', 'allows_customer_booking', 'genres', 'duration_minutes', 'age_rating',
            'country', 'release_date', 'poster_url', 'details_url', 'showtimes_url',
        ], array_keys($results->first()));

        $payload = json_encode($results->all(), JSON_THROW_ON_ERROR);
        foreach (['email', 'password', 'payment', 'qr', 'ticket', 'staff', 'created_at', 'updated_at'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, strtolower($payload));
        }
    }
}
