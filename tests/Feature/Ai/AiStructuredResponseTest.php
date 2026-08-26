<?php

namespace Tests\Feature\Ai;

use App\Ai\Agents\MovieMateCinemaAssistant;
use App\Ai\AiStructuredResponseAssembler;
use App\Ai\AiStructuredResultCollector;
use App\Models\FoodItem;
use App\Models\Movie;
use App\Models\Promotion;
use App\Models\User;
use App\Services\AiChatbotService;
use App\Services\AiMovieRecommendationService;
use App\Services\CustomerShowtimePriceReadService;
use App\Services\CustomerShowtimeReadService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Responses\Data\ToolCall;
use Tests\Support\CreatesPublicDiscoveryFixtures;
use Tests\TestCase;

class AiStructuredResponseTest extends TestCase
{
    use CreatesPublicDiscoveryFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_contract_is_versioned_bounded_allowlisted_and_contains_no_provider_internals(): void
    {
        foreach (range(1, 7) as $index) {
            Movie::query()->create([
                'title' => 'Bounded Movie '.$index,
                'slug' => 'bounded-movie-'.$index,
                'duration' => 100,
                'status' => Movie::STATUS_NOW_SHOWING,
            ]);
        }
        $this->enableAssistant();
        MovieMateCinemaAssistant::fake([
            new ToolCall('bounded-movies', 'search_movies', ['limit' => 12]),
            'Tôi tìm thấy phim. provider=openai token_count=secret card_type=custom_html',
        ])->preventStrayPrompts();

        $result = app(AiChatbotService::class)->answer('Có phim gì?');
        $response = $result['structured_response'];

        $this->assertSame(1, $response['version']);
        $this->assertSame($result['answer'], $response['text']);
        $this->assertCount(5, $response['cards']);
        $this->assertSame([], $response['suggested_actions']);
        $this->assertTrue(collect($response['cards'])->every(fn (array $card): bool => in_array($card['type'], ['movie', 'showtime', 'cinema', 'food'], true)));
        $this->assertSame([], array_intersect(['provider', 'model', 'tokens', 'tool_trace', 'system_prompt'], array_keys($response)));
        $this->assertStringNotContainsString('custom_html', json_encode($response['cards'], JSON_THROW_ON_ERROR));
    }

    public function test_movie_card_preserves_the_authoritative_poster_url_for_frontend_rendering(): void
    {
        $collector = app(AiStructuredResultCollector::class);
        $collector->reset();
        $collector->record('search_movies', ['movies' => [[
            'id' => 41,
            'title' => 'Poster Authority',
            'slug' => 'poster-authority',
            'status' => Movie::STATUS_NOW_SHOWING,
            'poster_url' => 'https://moviemate.test/storage/movies/posters/authority.webp',
        ]]]);

        $card = collect(app(AiStructuredResponseAssembler::class)->assemble('Có poster', $collector)->toArray()['cards'])->sole();

        $this->assertSame('movie', $card['type']);
        $this->assertSame('https://moviemate.test/storage/movies/posters/authority.webp', $card['poster_url']);
    }

    public function test_each_card_family_and_bounded_field_obeys_its_server_side_limit(): void
    {
        $movies = collect(range(1, 12))->map(fn (int $id): array => [
            'id' => $id,
            'title' => 'Movie '.$id,
            'slug' => 'movie-'.$id,
            'status' => Movie::STATUS_NOW_SHOWING,
            'duration_minutes' => 100,
            'description' => str_repeat('x', 5_000),
        ])->all();
        $showtimes = collect(range(1, 12))->map(fn (int $id): array => [
            'id' => $id,
            'date' => '2030-06-02',
            'start_time' => '19:00',
            'movie' => [
                'id' => $id, 'title' => 'Movie '.$id, 'slug' => 'movie-'.$id,
                'status' => Movie::STATUS_NOW_SHOWING,
            ],
            'cinema' => ['code' => 'C-'.$id, 'name' => 'Cinema '.$id],
            'starting_price_vnd' => 80_000,
            'bookable' => false,
            'booking_url' => null,
        ])->all();
        $cinemas = collect(range(1, 12))->map(fn (int $id): array => [
            'code' => 'C-'.$id, 'name' => 'Cinema '.$id,
        ])->all();
        $foods = collect(range(1, 12))->map(fn (int $id): array => [
            'id' => $id, 'name' => 'Food '.$id, 'price_vnd' => 50_000,
        ])->all();
        $recommendations = collect($movies)->map(fn (array $movie): array => [
            'movie_id' => $movie['id'],
            ...collect($movie)->except('id')->all(),
            'bookable' => false,
            'showtimes' => [],
        ])->all();

        $collector = app(AiStructuredResultCollector::class);
        $collector->reset();
        $collector->record('search_movies', ['movies' => $movies]);
        $collector->record('find_showtimes', ['showtimes' => $showtimes]);
        $collector->record('list_cinemas', ['cinemas' => $cinemas]);
        $collector->record('list_food_items', ['items' => $foods]);
        $collector->record('recommend_movies', ['candidates' => $recommendations]);

        $response = app(AiStructuredResponseAssembler::class)->assemble('Bounded', $collector)->toArray();
        $cards = collect($response['cards']);

        $this->assertCount(5, $cards->where('type', 'movie')->where('context', 'discovery'));
        $this->assertCount(5, $cards->where('type', 'movie')->where('context', 'recommendation'));
        $this->assertCount(6, $cards->where('type', 'showtime'));
        $this->assertCount(5, $cards->where('type', 'cinema'));
        $this->assertCount(6, $cards->where('type', 'food'));
        $this->assertSame(500, mb_strlen($cards->firstWhere('context', 'discovery')['description']));
        $this->assertLessThan(100_000, strlen(json_encode($response, JSON_THROW_ON_ERROR)));
    }

    public function test_provider_cannot_override_upcoming_movie_status_urls_or_booking_action(): void
    {
        $movie = Movie::query()->create([
            'title' => 'Upcoming Authority',
            'slug' => 'upcoming-authority',
            'duration' => 135,
            'status' => Movie::STATUS_COMING_SOON,
            'release_date' => now()->subYear()->toDateString(),
        ]);
        $this->enableAssistant();
        MovieMateCinemaAssistant::fake([
            new ToolCall('upcoming-details', 'get_movie_details', ['movie_id' => $movie->id]),
            'Phim đang chiếu, giá 50.000đ. Đặt tại https://evil.example/book /admin/payments/1 card_type=payment.',
        ])->preventStrayPrompts();

        $result = app(AiChatbotService::class)->answer('Phim này đặt được chưa?');
        $card = collect($result['structured_response']['cards'])->sole();
        $cardJson = json_encode($card, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $this->assertSame('movie', $card['type']);
        $this->assertSame(Movie::STATUS_COMING_SOON, $card['stored_status']);
        $this->assertSame(135, $card['duration_minutes']);
        $this->assertEqualsCanonicalizing(['movie_details', 'view_showtimes'], collect($card['actions'])->pluck('type')->all());
        $this->assertStringNotContainsString('evil.example', $cardJson);
        $this->assertStringNotContainsString('/admin/', $cardJson);
        $this->assertStringNotContainsString('book_showtime', $cardJson);
    }

    public function test_showtime_and_price_cards_use_current_start_plus_fifteen_and_snapshot_authority(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-02 18:50:00', 'Asia/Ho_Chi_Minh'));
        $scenario = $this->publicScenario('AI-04-ST', 'AI 04 Showtime', '2030-06-02', ['show_time' => '19:00:00']);
        $this->enableAssistant();
        MovieMateCinemaAssistant::fake([
            new ToolCall('current-showtime', 'find_showtimes', [
                'movie_id' => $scenario['movie']->id,
                'date' => '2030-06-02',
            ]),
            'Chỉ có suất bịa 23:59, giá 50.000đ, đặt tại https://evil.example/book.',
        ])->preventStrayPrompts();

        $result = app(AiChatbotService::class)->answer('Suất và giá hiện tại?');
        $liveCard = collect($result['structured_response']['cards'])->sole();

        $collector = app(AiStructuredResultCollector::class);
        $collector->reset();
        $showtimes = app(CustomerShowtimeReadService::class)->find([
            'movie_id' => $scenario['movie']->id,
            'date' => '2030-06-02',
        ]);
        $collector->record('find_showtimes', ['showtimes' => $showtimes->all()]);
        $collector->record('get_showtime_prices', [
            'showtime_prices' => app(CustomerShowtimePriceReadService::class)->get($scenario['showtime']->id),
        ]);
        $card = collect(app(AiStructuredResponseAssembler::class)
            ->assemble($result['answer'], $collector)->toArray()['cards'])->sole();
        $cardJson = json_encode($card, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $this->assertSame('19:00', $liveCard['time']);
        $this->assertSame('showtime', $card['type']);
        $this->assertSame('19:00', $card['time']);
        $this->assertStringContainsString('19:15:00', $card['booking_closes_at']);
        $this->assertSame(80_000, $card['starting_price_vnd']);
        $this->assertSame(80_000, $card['prices'][0]['amount_vnd']);
        $this->assertTrue($card['bookable']);
        $this->assertSame(route('user.bookings.selectSeat', $scenario['showtime']->id), $card['booking_url']);
        $this->assertContains('book_showtime', collect($card['actions'])->pluck('type')->all());
        $this->assertStringNotContainsString('23:59', $cardJson);
        $this->assertStringNotContainsString('50000', $cardJson);
        $this->assertStringNotContainsString('evil.example', $cardJson);
    }

    public function test_collector_is_reset_between_authenticated_and_guest_requests(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-01 10:00:00', 'Asia/Ho_Chi_Minh'));
        $first = Movie::query()->create([
            'title' => 'User A Movie', 'slug' => 'user-a-movie', 'duration' => 95,
            'status' => Movie::STATUS_NOW_SHOWING,
        ]);
        $second = $this->publicScenario('AI-04-B', 'User B Cinema', '2030-06-02');
        $this->enableAssistant();
        MovieMateCinemaAssistant::fake([
            new ToolCall('request-a', 'get_movie_details', ['movie_id' => $first->id]),
            'A',
            new ToolCall('request-b', 'find_showtimes', ['movie_id' => $second['movie']->id]),
            'B',
            'Guest answer without tools',
        ])->preventStrayPrompts();
        $service = app(AiChatbotService::class);

        $requestA = $service->answer('A', audience: 'authenticated');
        $requestB = $service->answer('B', audience: 'authenticated');
        $guest = $service->answer('Guest', audience: 'guest');

        $this->assertSame(['movie'], collect($requestA['structured_response']['cards'])->pluck('type')->all());
        $this->assertSame(['showtime'], collect($requestB['structured_response']['cards'])->pluck('type')->all());
        $this->assertStringNotContainsString('User A Movie', json_encode($requestB['structured_response']['cards'], JSON_THROW_ON_ERROR));
        $this->assertSame([], $guest['structured_response']['cards']);
    }

    public function test_recommendation_cards_reuse_authoritative_candidates_and_reject_provider_fields(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-01 10:00:00', 'Asia/Ho_Chi_Minh'));
        $scenario = $this->publicScenario('AI-04-REC', 'AI 04 Recommendation', '2030-06-02');
        $this->enableAssistant();
        MovieMateCinemaAssistant::fake([json_encode([
            'recommendations' => [
                [
                    'movie_id' => 999999,
                    'score' => 100,
                    'reason' => 'Unknown',
                ],
                [
                    'movie_id' => $scenario['movie']->id,
                    'score' => 90,
                    'reason' => '<img src=x onerror=alert(1)> Phù hợp',
                    'title' => 'Invented title',
                    'status' => 'coming_soon',
                    'booking_url' => 'https://evil.example/book',
                ],
            ],
        ], JSON_THROW_ON_ERROR)])->preventStrayPrompts();

        $result = app(AiMovieRecommendationService::class)->recommend([
            'mood' => 'happy', 'genres' => [], 'companion' => 'friends',
            'preferred_time' => 'tomorrow', 'location' => '',
        ], 1);
        $card = collect($result['structured_response']['cards'])->sole();
        $cardJson = json_encode($card, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $this->assertSame($scenario['movie']->id, $card['id']);
        $this->assertSame($scenario['movie']->title, $card['title']);
        $this->assertSame(Movie::STATUS_NOW_SHOWING, $card['stored_status']);
        $this->assertSame('Phù hợp', $card['reason']);
        $this->assertStringNotContainsString('999999', $cardJson);
        $this->assertStringNotContainsString('Invented title', $cardJson);
        $this->assertStringNotContainsString('evil.example', $cardJson);
        $this->assertContains('book_showtime', collect($card['actions'])->pluck('type')->all());
    }

    public function test_cinema_and_food_cards_are_public_bounded_and_do_not_invent_branch_inventory(): void
    {
        $scenario = $this->publicScenario('AI-04-C', 'AI 04 Cinema', now()->addDay()->toDateString());
        FoodItem::query()->create([
            'name' => '<script>alert(1)</script> Popcorn',
            'description' => '<img src=x onerror=alert(1)> Public snack',
            'price' => 75_000,
            'active' => true,
        ]);
        $this->enableAssistant();
        MovieMateCinemaAssistant::fake([
            new ToolCall('cinema-public', 'list_cinemas', ['query' => 'AI 04 Cinema']),
            'Rạp công khai.',
            new ToolCall('food-public', 'list_food_items', ['limit' => 12]),
            'Món công khai.',
            new ToolCall('food-branch', 'list_food_items', ['cinema_code' => $scenario['cinema']->code]),
            'Chi nhánh có đủ món.',
        ])->preventStrayPrompts();
        $service = app(AiChatbotService::class);

        $cinemaResult = $service->answer('Rạp?');
        $foodResult = $service->answer('Đồ ăn?');
        $branch = $service->answer('Chi nhánh có món gì?');
        $cinema = collect($cinemaResult['structured_response']['cards'])->firstWhere('type', 'cinema');
        $food = collect($foodResult['structured_response']['cards'])->firstWhere('type', 'food');

        $this->assertSame('AI-04-C', $cinema['code']);
        $this->assertArrayNotHasKey('staff', $cinema);
        $this->assertArrayNotHasKey('assignments', $cinema);
        $this->assertSame('Popcorn', $food['name']);
        $this->assertSame('Public snack', $food['description']);
        $this->assertFalse($food['branch_availability_confirmed']);
        $this->assertArrayNotHasKey('cinema_id', $food);
        $this->assertStringNotContainsString('FoodPickupVoucher', json_encode($foodResult['structured_response'], JSON_THROW_ON_ERROR));
        $this->assertSame([], $branch['structured_response']['cards']);
    }

    public function test_live_api_returns_actions_but_history_keeps_only_safe_display_snapshot(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $conversation = $owner->aiConversations()->create(['title' => 'Structured live response']);
        $movie = Movie::query()->create([
            'title' => 'Live Only Card', 'slug' => 'live-only-card', 'duration' => 101,
            'status' => Movie::STATUS_NOW_SHOWING,
        ]);
        $this->enableAssistant();
        MovieMateCinemaAssistant::fake([
            new ToolCall('live-card', 'get_movie_details', ['movie_id' => $movie->id]),
            'Live answer',
        ])->preventStrayPrompts();

        $live = $this->actingAs($owner)->postJson(
            route('user.ai.conversations.messages.store', $conversation->id),
            ['message' => 'Cho tôi xem phim'],
        );
        $live->assertCreated()
            ->assertJsonPath('data.structured_response.version', 1)
            ->assertJsonPath('data.structured_response.cards.0.id', $movie->id);

        $history = $this->actingAs($owner)->getJson(
            route('user.ai.conversations.messages.index', $conversation->id),
        );
        $history->assertOk()->assertJsonMissingPath('data.1.structured_response')
            ->assertJsonPath('data.1.historical_cards.0.id', $movie->id);
        $historical = $history->json('data.1.historical_cards.0');
        $this->assertSame(['id', 'role', 'content', 'historical_cards', 'created_at'], array_keys($history->json('data.1')));
        $this->assertArrayNotHasKey('actions', $historical);
        $this->assertArrayNotHasKey('details_url', $historical);
        $this->assertArrayNotHasKey('showtimes_url', $historical);
        $this->assertDatabaseCount('ai_messages', 2);
    }

    public function test_promotion_cards_remain_withheld_and_private_codes_are_not_enumerated(): void
    {
        Promotion::query()->create([
            'code' => 'PRIVATE-AI-04',
            'name' => 'Private checkout code',
            'type' => Promotion::TYPE_FIXED,
            'discount_amount_vnd' => 20_000,
            'minimum_order_vnd' => 100_000,
            'is_active' => true,
            'registered_users_only' => true,
        ]);
        config()->set('moviemate-ai.enabled', false);

        $result = app(AiChatbotService::class)->answer('Có mã khuyến mãi nào?');

        $this->assertSame([], $result['structured_response']['cards']);
        $this->assertStringNotContainsString('PRIVATE-AI-04', json_encode($result, JSON_THROW_ON_ERROR));
        $this->assertNotContains('get_public_promotions', MovieMateCinemaAssistant::TOOL_ALLOWLIST);
    }

    public function test_provider_text_and_conversation_content_remain_escaped_in_current_blade_rendering(): void
    {
        $this->enableAssistant();
        MovieMateCinemaAssistant::fake([
            '<script>alert("provider")</script><img src=x onerror=alert(1)>',
        ])->preventStrayPrompts();

        $this->post(route('user.ai.chatbot.submit'), [
            'message' => '<img src=x onerror=alert("user")>',
        ])->assertRedirect();

        $page = $this->get(route('user.ai.chatbot'));
        $page->assertOk()
            ->assertSee('&lt;script&gt;alert(&quot;provider&quot;)&lt;/script&gt;', false)
            ->assertSee('&lt;img src=x onerror=alert(&quot;user&quot;)&gt;', false)
            ->assertDontSee('<script>alert("provider")</script>', false)
            ->assertDontSee('<img src=x onerror=alert("user")>', false);
    }

    private function enableAssistant(): void
    {
        config()->set('moviemate-ai.enabled', true);
        config()->set('moviemate-ai.provider', 'openai');
        config()->set('moviemate-ai.model', 'test-model');
        config()->set('ai.providers.openai.key', 'test-only-key');
        Http::preventStrayRequests();
    }
}
