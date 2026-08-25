<?php

namespace Tests\Feature\Ai;

use App\Ai\Agents\MovieMateCinemaAssistant;
use App\Ai\AiHistoricalStructuredPayload;
use App\Ai\AiStructuredResponseAssembler;
use App\Ai\AiStructuredResultCollector;
use App\Ai\MovieGenreLocalizer;
use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiPresentationPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_backed_movie_text_is_concise_and_does_not_duplicate_provider_prose(): void
    {
        $collector = app(AiStructuredResultCollector::class);
        $collector->reset();
        $collector->record('search_movies', ['movies' => [[
            'id' => 41,
            'title' => 'Phim không được lặp trong phần giới thiệu',
            'slug' => 'phim-khong-lap',
            'status' => Movie::STATUS_NOW_SHOWING,
            'genres' => ['Drama', 'Romance'],
            'duration_minutes' => 120,
            'age_rating' => 'T13',
        ]]]);

        $response = app(AiStructuredResponseAssembler::class)->assemble(
            "1. **Phim không được lặp trong phần giới thiệu**\n**Thể loại:** Drama, Romance\n**Thời lượng:** 120 phút",
            $collector,
        )->toArray();

        $this->assertSame('Mình tìm thấy 1 phim trên MovieMate:', $response['text']);
        $this->assertLessThanOrEqual(180, mb_strlen($response['text']));
        $this->assertStringNotContainsString('Thể loại', $response['text']);
        $this->assertSame(['Chính kịch', 'Lãng mạn'], $response['cards'][0]['genres']);
        $this->assertEqualsCanonicalizing(
            ['movie_details', 'view_showtimes'],
            collect($response['cards'][0]['actions'])->pluck('type')->all(),
        );
    }

    public function test_card_backed_recommendation_has_a_short_vietnamese_introduction(): void
    {
        $response = app(AiStructuredResponseAssembler::class)->assembleRecommendations(
            'Một danh sách phim rất dài do nhà cung cấp tạo ra.',
            [[
                'movie_id' => 42,
                'title' => 'Phim lãng mạn',
                'slug' => 'phim-lang-man',
                'status' => Movie::STATUS_NOW_SHOWING,
                'genres' => ['Romance'],
                'bookable' => false,
                'showtimes' => [],
                'reason' => 'Phù hợp với sở thích lãng mạn.',
            ]],
        )->toArray();

        $this->assertSame('Mình tìm thấy 1 phim phù hợp với bạn từ dữ liệu MovieMate:', $response['text']);
        $this->assertSame('recommendation', $response['cards'][0]['context']);
        $this->assertSame(['Lãng mạn'], $response['cards'][0]['genres']);
    }

    public function test_non_card_conversation_keeps_its_useful_text(): void
    {
        $collector = app(AiStructuredResultCollector::class);
        $collector->reset();
        $text = "**MovieMate có thể hỗ trợ:**\n- Tra cứu phim.\n- Tìm suất chiếu.";

        $response = app(AiStructuredResponseAssembler::class)->assemble($text, $collector)->toArray();

        $this->assertSame($text, $response['text']);
        $this->assertSame([], $response['cards']);
    }

    public function test_showtime_cards_hide_intermediate_movie_discovery_cards(): void
    {
        $collector = app(AiStructuredResultCollector::class);
        $collector->reset();
        $movie = [
            'id' => 45,
            'title' => 'Phim trung gian',
            'slug' => 'phim-trung-gian',
            'status' => Movie::STATUS_NOW_SHOWING,
        ];
        $collector->record('search_movies', ['movies' => [$movie]]);
        $collector->record('find_showtimes', ['showtimes' => [[
            'id' => 81,
            'date' => '2026-08-23',
            'start_time' => '20:15',
            'movie' => $movie,
            'cinema' => ['code' => 'CG', 'name' => 'MovieMate Cầu Giấy'],
            'starting_price_vnd' => 90_000,
            'bookable' => false,
        ]]]);

        $response = app(AiStructuredResponseAssembler::class)->assemble('Danh sách dài', $collector)->toArray();

        $this->assertSame('Mình tìm thấy 1 suất chiếu phù hợp:', $response['text']);
        $this->assertSame(['showtime'], collect($response['cards'])->pluck('type')->all());
        $this->assertSame('20:15', $response['cards'][0]['time']);
        $this->assertSame(90_000, $response['cards'][0]['starting_price_vnd']);
    }

    public function test_genre_localization_is_centralized_and_preserves_unknown_values(): void
    {
        $genres = app(MovieGenreLocalizer::class);

        $this->assertSame(['Chính kịch', 'Lãng mạn'], $genres->localizeList(['Drama', 'Romance']));
        $this->assertSame(['Hài', 'Chính kịch', 'Gia đình'], $genres->localizeList(['Comedy', 'Drama', 'Family']));
        $this->assertSame(['Thể loại riêng'], $genres->localizeList(['Thể loại riêng']));
    }

    public function test_historical_movie_keeps_only_a_valid_existing_poster_and_never_restores_actions(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('movies/posters/authority.webp', 'poster');

        $payload = app(AiHistoricalStructuredPayload::class)->forStorage([
            'cards' => [[
                'type' => 'movie',
                'id' => 43,
                'title' => 'Poster lịch sử',
                'stored_status' => Movie::STATUS_NOW_SHOWING,
                'poster_url' => '/storage/movies/posters/authority.webp',
                'genres' => ['Comedy', 'Drama', 'Family'],
                'actions' => [['type' => 'book_showtime', 'url' => '/dat-ve/43']],
            ]],
        ]);

        $card = $payload['cards'][0];
        $this->assertSame('/storage/movies/posters/authority.webp', $card['poster_url']);
        $this->assertSame(['Hài', 'Chính kịch', 'Gia đình'], $card['genres']);
        $this->assertArrayNotHasKey('actions', $card);

        $unsafe = app(AiHistoricalStructuredPayload::class)->forStorage([
            'cards' => [[
                'type' => 'movie',
                'id' => 44,
                'title' => 'Poster không tin cậy',
                'stored_status' => Movie::STATUS_NOW_SHOWING,
                'poster_url' => 'https://evil.example/poster.webp',
            ]],
        ]);
        $this->assertArrayNotHasKey('poster_url', $unsafe['cards'][0]);
    }

    public function test_frontend_uses_safe_small_markdown_and_one_time_poster_fallbacks(): void
    {
        $format = file_get_contents(resource_path('js/ai/format.js'));
        $cards = file_get_contents(resource_path('js/ai/cards.js'));
        $chat = file_get_contents(resource_path('js/ai-chat.js'));
        $source = $format.$cards.$chat;

        $this->assertStringContainsString("document.createElement('strong')", $format);
        $this->assertStringContainsString("document.createElement('ul')", $format);
        $this->assertStringContainsString("document.createElement('li')", $format);
        $this->assertStringContainsString('strong.textContent', $format);
        $this->assertStringContainsString('document.createTextNode', $format);
        $this->assertStringContainsString('renderAssistantText(body, visible)', $chat);
        $this->assertStringNotContainsString('innerHTML', $source);
        $this->assertStringNotContainsString('insertAdjacentHTML', $source);
        $this->assertStringContainsString("image.addEventListener('error'", $cards);
        $this->assertStringContainsString('{once: true}', $cards);
        $this->assertStringContainsString('fallback.hidden = false', $cards);
        $this->assertStringContainsString('card.poster_url', $cards);
        $this->assertStringContainsString('`${match[3]}/${match[2]}/${match[1]}`', $cards);
    }

    public function test_customer_instructions_require_vietnamese_card_first_answers_without_technical_terms(): void
    {
        $instructions = app(MovieMateCinemaAssistant::class)->instructions();

        foreach (['Reply in Vietnamese by default', 'zero to two short introductory sentences', '180 visible characters', 'Never repeat the cards', 'Never output raw HTML'] as $policy) {
            $this->assertStringContainsString($policy, $instructions);
        }
    }
}
