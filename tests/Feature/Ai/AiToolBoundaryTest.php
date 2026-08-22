<?php

namespace Tests\Feature\Ai;

use App\Ai\Agents\MovieMateCinemaAssistant;
use App\Ai\Tools\SearchMovies;
use App\Models\Cinema;
use App\Models\FoodItem;
use App\Services\AiChatbotService;
use App\Services\PublicFoodReadService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Providers\Tools\ProviderTool;
use Laravel\Ai\Tools\Request;
use Laravel\Ai\Tools\ToolNameResolver;
use ReflectionClass;
use Tests\TestCase;

class AiToolBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_exposes_only_the_explicit_read_tool_allowlist(): void
    {
        $agent = app(MovieMateCinemaAssistant::class);
        $tools = collect($agent->tools());

        $this->assertSame([
            'search_movies', 'get_movie_details', 'find_showtimes', 'list_cinemas',
            'list_food_items', 'get_showtime_prices', 'recommend_movies',
        ], $tools->map(fn (Tool $tool): string => ToolNameResolver::resolve($tool))->all());
        $this->assertTrue($tools->every(fn ($tool): bool => $tool instanceof Tool && ! $tool instanceof ProviderTool));
        $this->assertNotInstanceOf(Conversational::class, $agent);

        $reflection = new ReflectionClass($agent);
        $this->assertSame(4, $reflection->getAttributes(MaxSteps::class)[0]->newInstance()->value);
        $this->assertSame(1400, $reflection->getAttributes(MaxTokens::class)[0]->newInstance()->value);

        $forbiddenArguments = ['user_id', 'table', 'column', 'sql', 'model', 'class', 'service', 'method'];
        $factory = new JsonSchemaTypeFactory;
        foreach ($tools as $tool) {
            $this->assertEmpty(array_intersect($forbiddenArguments, array_keys($tool->schema($factory))));
        }

        $source = collect((new ReflectionClass(MovieMateCinemaAssistant::class))->getFileName())
            ->map(fn (string $file): string => file_get_contents($file))->first();
        foreach (['RemembersConversations', 'WebSearch', 'WebFetch', 'FileSearch', 'FileStorage', 'MCP', 'AgentTool'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }
        $this->assertStringNotContainsString('get_public_promotions', implode(',', $tools->map(fn ($tool) => ToolNameResolver::resolve($tool))->all()));
    }

    public function test_tools_reject_unknown_or_authority_broadening_arguments(): void
    {
        $tool = app(SearchMovies::class);

        foreach (['user_id', 'table', 'model', 'class'] as $argument) {
            try {
                $tool->handle(new Request([$argument => 'users']));
                $this->fail("Tool accepted forbidden argument {$argument}.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('arguments', $exception->errors());
            }
        }
    }

    public function test_food_reading_mirrors_public_catalog_but_never_claims_branch_availability(): void
    {
        $cinema = Cinema::factory()->create(['code' => 'AI-FOOD', 'status' => 'active', 'archived_at' => null]);
        FoodItem::query()->create(['name' => 'Bắp chung', 'price' => 50_000, 'active' => true]);
        FoodItem::query()->create(['cinema_id' => $cinema->id, 'name' => 'Nước chi nhánh', 'price' => 30_000, 'active' => true]);
        FoodItem::query()->create(['name' => 'Món ẩn', 'price' => 10_000, 'active' => false]);

        $service = app(PublicFoodReadService::class);
        $global = $service->list();
        $branch = $service->list(cinemaCode: $cinema->code);

        $this->assertCount(2, $global['items']);
        $this->assertFalse($global['branch_availability_confirmed']);
        $this->assertSame([], $branch['items']);
        $this->assertFalse($branch['branch_availability_confirmed']);
        $this->assertDatabaseCount('food_items', 3);
    }

    public function test_sdk_fake_handles_enabled_chatbot_without_any_live_http_request(): void
    {
        config()->set('moviemate-ai.enabled', true);
        config()->set('moviemate-ai.provider', 'openai');
        config()->set('moviemate-ai.model', 'test-model');
        config()->set('ai.providers.openai.key', 'test-only-key');
        Http::preventStrayRequests();
        MovieMateCinemaAssistant::fake(['Câu trả lời đã fake.'])->preventStrayPrompts();

        $result = app(AiChatbotService::class)->answer('Hôm nay có gì?');

        $this->assertSame('Câu trả lời đã fake.', $result['answer']);
        $this->assertSame('openai', $result['source']);
        MovieMateCinemaAssistant::assertPrompted('Hôm nay có gì?');
    }

    public function test_assistant_instructions_ground_relative_dates_in_authoritative_server_time(): void
    {
        config()->set('app.timezone', 'Asia/Ho_Chi_Minh');
        $this->travelTo(CarbonImmutable::parse('2026-08-22 14:35:00', 'Asia/Ho_Chi_Minh'));

        $instructions = app(MovieMateCinemaAssistant::class)->instructions();

        $this->assertStringContainsString('2026-08-22 14:35:00 +07:00 (Asia/Ho_Chi_Minh)', $instructions);
        foreach (['hôm nay', 'tối nay', 'cuối tuần này', 'never guess'] as $groundedPhrase) {
            $this->assertStringContainsString($groundedPhrase, $instructions);
        }
    }

    public function test_missing_credential_degrades_without_a_provider_request_or_secret_leak(): void
    {
        config()->set('moviemate-ai.enabled', true);
        config()->set('moviemate-ai.provider', 'openai');
        config()->set('ai.providers.openai.key', null);
        Http::preventStrayRequests();

        $result = app(AiChatbotService::class)->answer('Bạn hỗ trợ gì?');

        $this->assertSame('fallback', $result['source']);
        $this->assertStringNotContainsString('key', strtolower($result['answer'].$result['message']));
        MovieMateCinemaAssistant::assertNeverPrompted();
    }
}
