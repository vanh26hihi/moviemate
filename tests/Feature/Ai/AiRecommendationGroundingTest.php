<?php

namespace Tests\Feature\Ai;

use App\Ai\Agents\MovieMateCinemaAssistant;
use App\Services\AiMovieRecommendationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesPublicDiscoveryFixtures;
use Tests\TestCase;

class AiRecommendationGroundingTest extends TestCase
{
    use CreatesPublicDiscoveryFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_fallback_candidates_are_real_and_have_authoritative_booking_actions(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-01 10:00:00', 'Asia/Ho_Chi_Minh'));
        $first = $this->publicScenario('AI-REC-1', 'AI Rec Cinema 1', '2030-06-02');
        $second = $this->publicScenario('AI-REC-2', 'AI Rec Cinema 2', '2030-06-02');

        $result = app(AiMovieRecommendationService::class)->recommend([
            'mood' => 'happy', 'genres' => [], 'companion' => 'friends',
            'preferred_time' => 'tomorrow', 'location' => '',
        ]);

        $this->assertSame('fallback', $result['source']);
        $this->assertEqualsCanonicalizing(
            [$first['movie']->id, $second['movie']->id],
            collect($result['recommendations'])->pluck('movie_id')->all(),
        );
        foreach ($result['recommendations'] as $recommendation) {
            $this->assertTrue($recommendation['bookable']);
            $this->assertNotEmpty($recommendation['booking_url']);
            $this->assertTrue(collect($recommendation['showtimes'])->every(fn (array $showtime): bool => $showtime['bookable'] === true));
        }
        $this->assertCount(2, $result['structured_response']['cards']);
        $this->assertTrue(collect($result['structured_response']['cards'])->every(
            fn (array $card): bool => $card['type'] === 'movie'
                && $card['context'] === 'recommendation'
                && collect($card['actions'])->contains('type', 'book_showtime'),
        ));
    }

    public function test_provider_ranking_cannot_create_a_movie_or_smuggle_an_unknown_id(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-01 10:00:00', 'Asia/Ho_Chi_Minh'));
        $scenario = $this->publicScenario('AI-REC-FAKE', 'AI Rec Fake Cinema', '2030-06-02');
        config()->set('moviemate-ai.enabled', true);
        config()->set('moviemate-ai.provider', 'openai');
        config()->set('moviemate-ai.model', 'test-model');
        config()->set('ai.providers.openai.key', 'test-only-key');
        Http::preventStrayRequests();
        MovieMateCinemaAssistant::fake([json_encode([
            'recommendations' => [
                ['movie_id' => 999999, 'score' => 100, 'reason' => 'Phim bịa'],
                ['movie_id' => $scenario['movie']->id, 'score' => 91, 'reason' => 'Phim thật từ MovieMate'],
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)])->preventStrayPrompts();

        $result = app(AiMovieRecommendationService::class)->recommend([
            'mood' => 'happy', 'genres' => [], 'companion' => 'friends',
            'preferred_time' => 'tomorrow', 'location' => '',
        ], 1);

        $this->assertSame('openai', $result['source']);
        $this->assertCount(1, $result['recommendations']);
        $this->assertSame($scenario['movie']->id, $result['recommendations'][0]['movie_id']);
        $this->assertSame('Phim thật từ MovieMate', $result['recommendations'][0]['reason']);
        $this->assertDatabaseCount('movies', 1);
        MovieMateCinemaAssistant::assertPrompted(fn ($prompt): bool => str_contains($prompt->prompt, 'available_movies'));
    }
}
