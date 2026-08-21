<?php

namespace Tests\Feature\Ai;

use App\Ai\Tools\GetShowtimePrices;
use App\Models\Movie;
use App\Services\CustomerShowtimeReadService;
use App\Services\ShowtimeLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\Support\CreatesPublicDiscoveryFixtures;
use Tests\TestCase;

class AiShowtimeGroundingTest extends TestCase
{
    use CreatesPublicDiscoveryFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_ai_showtimes_follow_authoritative_start_plus_fifteen_and_safe_presentation(): void
    {
        $scenario = $this->publicScenario('AI-CUTOFF', 'AI Cutoff Cinema', '2030-06-01', ['show_time' => '10:00:00']);
        $filters = ['movie_id' => $scenario['movie']->id, 'cinema_code' => $scenario['cinema']->code, 'date' => '2030-06-01'];

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-01 10:14:59', 'Asia/Ho_Chi_Minh'));
        $open = app(CustomerShowtimeReadService::class)->find($filters);
        $this->assertCount(1, $open);
        $this->assertTrue($open->first()['bookable']);
        $this->assertSame(
            route('user.bookings.selectSeat', ['showtime' => $scenario['showtime']->id, 'cinema' => $scenario['cinema']->code]),
            $open->first()['booking_url'],
        );
        $this->assertSame(ShowtimeLifecycleService::BOOKING_CUTOFF_MINUTES, 15);

        $payload = json_encode($open->all(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('Phòng AI-CUTOFF', $payload);
        foreach (['room_id', 'room_name', 'payment', 'ticket', 'qr', 'staff'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, strtolower($payload));
        }

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-01 10:15:00', 'Asia/Ho_Chi_Minh'));
        $this->assertTrue(app(CustomerShowtimeReadService::class)->find($filters)->isEmpty());
    }

    public function test_cancelled_incomplete_and_upcoming_showtimes_never_become_ai_bookable(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-01 10:00:00', 'Asia/Ho_Chi_Minh'));
        $cancelled = $this->publicScenario('AI-CANCEL', 'AI Cancel Cinema', '2030-06-02', ['showtime_status' => 'cancelled']);
        $incomplete = $this->publicScenario('AI-NOPRICE', 'AI No Price Cinema', '2030-06-02', ['with_pricing' => false]);
        $upcomingMovie = Movie::query()->create([
            'title' => 'AI Stored Upcoming', 'slug' => 'ai-stored-upcoming', 'duration' => 100,
            'release_date' => '2029-01-01', 'status' => Movie::STATUS_COMING_SOON,
        ]);
        $upcoming = $this->publicScenario('AI-UPCOMING', 'AI Upcoming Cinema', '2030-06-02', ['movie' => $upcomingMovie]);

        $service = app(CustomerShowtimeReadService::class);
        foreach ([$cancelled, $incomplete, $upcoming] as $scenario) {
            $this->assertTrue($service->find([
                'movie_id' => $scenario['movie']->id,
                'cinema_code' => $scenario['cinema']->code,
                'date' => '2030-06-02',
            ])->isEmpty());
        }
    }

    public function test_showtime_price_tool_returns_only_immutable_logical_unit_snapshots(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-01 10:00:00', 'Asia/Ho_Chi_Minh'));
        $scenario = $this->publicScenario('AI-PRICE', 'AI Price Cinema', '2030-06-02');

        $payload = json_decode(app(GetShowtimePrices::class)->handle(new Request([
            'showtime_id' => $scenario['showtime']->id,
        ])), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame($scenario['showtime']->id, $payload['showtime_prices']['showtime_id']);
        $this->assertNull($payload['showtime_prices']['final_booking_total']);
        $this->assertSame(['seat_type_code', 'seat_type_name', 'logical_unit_seat_count', 'amount_vnd'], array_keys($payload['showtime_prices']['prices'][0]));
    }

    public function test_recommendation_cta_is_rendered_only_from_backend_bookability(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-01 10:00:00', 'Asia/Ho_Chi_Minh'));
        $scenario = $this->publicScenario('AI-CTA', 'AI CTA Cinema', '2030-06-02');

        $this->post(route('user.ai.recommend.submit'), [
            'mood' => 'happy', 'genres' => [], 'companion' => 'friends',
            'preferred_time' => 'tomorrow', 'location' => '',
        ])->assertOk()
            ->assertSee('data-ai-recommendation-booking-action', false)
            ->assertSee(route('user.bookings.selectSeat', [
                'showtime' => $scenario['showtime']->id,
                'cinema' => $scenario['cinema']->code,
            ]));

        $upcoming = Movie::query()->create([
            'title' => 'CTA Upcoming', 'slug' => 'cta-upcoming', 'duration' => 100,
            'status' => Movie::STATUS_COMING_SOON,
        ]);
        $recommendation = [
            'movie_id' => $upcoming->id, 'title' => $upcoming->title, 'slug' => $upcoming->slug,
            'status' => Movie::STATUS_COMING_SOON, 'poster' => null, 'duration' => 100,
            'age_rating' => 'P', 'country' => null, 'genres' => [], 'showtimes' => [],
            'bookable' => false, 'booking_url' => null, 'score' => 80, 'reason' => 'Chi tiết phim.',
        ];

        $this->withSession(['ai.recommend.result' => ['recommendations' => [$recommendation]]])
            ->get(route('user.ai.recommend'))->assertOk()
            ->assertDontSee('data-ai-recommendation-booking-action', false)
            ->assertSee('Chi tiết');

        $source = file_get_contents(app_path('Services/AiMovieRecommendationService.php'))
            .file_get_contents(app_path('Services/AiChatbotService.php'));
        $this->assertStringNotContainsString('subMinutes(30)', $source);
        $this->assertStringNotContainsString('subMinutes(30)', $source);
    }
}
