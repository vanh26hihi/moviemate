<?php

namespace Tests\Feature\Ai;

use App\Ai\Agents\MovieMateCinemaAssistant;
use App\Ai\AiConversationContext;
use App\Ai\Contracts\AiTextStreamer;
use App\Ai\Gateways\NineRouterGateway;
use App\Ai\MovieMateAiRuntime;
use App\Ai\Providers\NineRouterProvider;
use App\Models\Movie;
use App\Services\AiChatbotService;
use App\Services\AiChatStreamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AiManager;
use Laravel\Ai\Providers\GeminiProvider;
use Laravel\Ai\Providers\OpenAiProvider;
use RuntimeException;
use Tests\TestCase;

class NineRouterProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_nine_router_is_a_server_owned_openai_compatible_provider(): void
    {
        config()->set('ai.providers.nine_router', [
            'driver' => 'nine_router',
            'key' => 'test-only-nine-router-key',
            'url' => 'http://127.0.0.1:20128/v1',
        ]);
        config()->set('moviemate-ai.enabled', true);
        config()->set('moviemate-ai.provider', 'nine_router');
        config()->set('moviemate-ai.model', 'server-selected-model');

        $manager = app(AiManager::class);
        $manager->forgetInstance(['openai', 'gemini', 'nine_router']);
        $provider = $manager->textProvider('nine_router');
        $runtime = app(MovieMateAiRuntime::class);

        $this->assertSame(['openai', 'gemini', 'nine_router'], MovieMateAiRuntime::SUPPORTED_PROVIDERS);
        $this->assertInstanceOf(NineRouterProvider::class, $provider);
        $this->assertInstanceOf(NineRouterGateway::class, $provider->textGateway());
        $this->assertSame('nine_router', $provider->name());
        $this->assertSame('nine_router', $provider->driver());
        $this->assertSame('http://127.0.0.1:20128/v1', $provider->additionalConfiguration()['url']);
        $this->assertTrue($runtime->enabledAndConfigured());
        $this->assertSame('nine_router', $runtime->provider());
        $this->assertSame('server-selected-model', $runtime->model());

        $this->assertInstanceOf(OpenAiProvider::class, $manager->textProvider('openai'));
        $this->assertInstanceOf(GeminiProvider::class, $manager->textProvider('gemini'));

        config()->set('moviemate-ai.provider', 'not-allowed');
        $this->assertSame('openai', $runtime->provider());
    }

    public function test_nine_router_executes_the_existing_tool_loop_without_real_network_requests(): void
    {
        Movie::query()->create([
            'title' => 'Nine Router Grounded Movie',
            'slug' => 'nine-router-grounded-movie',
            'duration' => 105,
            'status' => 'now_showing',
        ]);
        $this->enableNineRouter('tool-compatible-model');

        $requestNumber = 0;
        Http::preventStrayRequests();
        Http::fake(function (Request $request) use (&$requestNumber) {
            $this->assertSame('http://127.0.0.1:20128/v1/responses', $request->url());
            $requestNumber++;

            if ($requestNumber === 1) {
                return Http::response($this->toolCallResponse('search_movies', '{"limit":5}'));
            }

            if ($requestNumber === 2) {
                return Http::response($this->completedResponse('Phim này đang chiếu tại MovieMate.'));
            }

            throw new RuntimeException('Unexpected provider request.');
        });

        $result = app(AiChatbotService::class)->answer('MovieMate có phim gì đang chiếu?');
        $recorded = Http::recorded();

        $this->assertTrue($result['assistant_completed'], json_encode($result));
        $this->assertSame('nine_router', $result['source']);
        $this->assertSame('Phim này đang chiếu tại MovieMate.', $result['answer']);
        $this->assertSame('Nine Router Grounded Movie', $result['structured_response']['cards'][0]['title']);
        $this->assertCount(2, $recorded);

        $firstBody = $recorded[0][0]->data();
        $this->assertSame('tool-compatible-model', $firstBody['model']);
        $this->assertFalse($firstBody['stream']);
        $this->assertTrue($recorded[0][0]->hasHeader('Accept', 'application/json'));
        $this->assertSame(MovieMateCinemaAssistant::TOOL_ALLOWLIST, array_column($firstBody['tools'], 'name'));
        $this->assertArrayNotHasKey('provider', $firstBody);
        $this->assertArrayNotHasKey('url', $firstBody);
        $this->assertArrayNotHasKey('api_key', $firstBody);

        $followUpBody = $recorded[1][0]->data();
        $this->assertSame('tool-compatible-model', $followUpBody['model']);
        $this->assertFalse($followUpBody['stream']);
        $this->assertArrayNotHasKey('previous_response_id', $followUpBody);
        $functionCall = collect($followUpBody['input'])->firstWhere('type', 'function_call');
        $toolOutput = collect($followUpBody['input'])->firstWhere('type', 'function_call_output');
        $this->assertSame('search_movies', $functionCall['name']);
        $this->assertSame('call_nine_router_search', $toolOutput['call_id']);
        $this->assertStringContainsString('Nine Router Grounded Movie', $toolOutput['output']);
    }

    public function test_nine_router_streaming_transport_stays_explicit_and_executes_tools(): void
    {
        Movie::query()->create([
            'title' => 'Nine Router Stream Movie',
            'slug' => 'nine-router-stream-movie',
            'duration' => 99,
            'status' => 'now_showing',
        ]);
        $this->enableNineRouter('stream-compatible-model');

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:20128/v1/chat/completions' => Http::sequence([
                Http::response($this->ssePayload([
                    [
                        'id' => 'chatcmpl-stream-tool',
                        'object' => 'chat.completion.chunk',
                        'model' => 'stream-compatible-model',
                        'choices' => [[
                            'index' => 0,
                            'delta' => [
                                'role' => 'assistant',
                                'tool_calls' => [[
                                    'index' => 0,
                                    'id' => 'call_stream_search',
                                    'type' => 'function',
                                    'function' => ['name' => 'search_movies', 'arguments' => ''],
                                ]],
                            ],
                            'finish_reason' => null,
                        ]],
                    ],
                    [
                        'id' => 'chatcmpl-stream-tool',
                        'object' => 'chat.completion.chunk',
                        'model' => 'stream-compatible-model',
                        'choices' => [[
                            'index' => 0,
                            'delta' => ['tool_calls' => [[
                                'index' => 0,
                                'function' => ['arguments' => '{"limit":5}'],
                            ]]],
                            'finish_reason' => null,
                        ]],
                    ],
                    [
                        'id' => 'chatcmpl-stream-tool',
                        'object' => 'chat.completion.chunk',
                        'model' => 'stream-compatible-model',
                        'choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'tool_calls']],
                    ],
                ]), 200, ['Content-Type' => 'text/event-stream']),
                Http::response($this->ssePayload([
                    [
                        'id' => 'chatcmpl-stream-final',
                        'object' => 'chat.completion.chunk',
                        'model' => 'stream-compatible-model',
                        'choices' => [[
                            'index' => 0,
                            'delta' => ['role' => 'assistant', 'content' => 'Đã tìm thấy phim đang chiếu.'],
                            'finish_reason' => null,
                        ]],
                    ],
                    [
                        'id' => 'chatcmpl-stream-final',
                        'object' => 'chat.completion.chunk',
                        'model' => 'stream-compatible-model',
                        'choices' => [['index' => 0, 'delta' => [], 'finish_reason' => 'stop']],
                    ],
                ]), 200, ['Content-Type' => 'text/event-stream']),
            ]),
        ]);

        $stream = app(AiChatStreamService::class)->stream(
            'Phim nào đang chiếu?',
            AiConversationContext::empty(),
            'guest',
        );
        $deltas = '';
        foreach ($stream as $delta) {
            $deltas .= $delta;
        }
        $result = $stream->getReturn();
        $recorded = Http::recorded();

        $this->assertSame('Đã tìm thấy phim đang chiếu.', $deltas);
        $this->assertSame('nine_router', $result['source']);
        $this->assertSame('Nine Router Stream Movie', $result['structured_response']['cards'][0]['title']);
        $this->assertCount(2, $recorded);
        $this->assertTrue($recorded[0][0]->data()['stream']);
        $this->assertTrue($recorded[1][0]->data()['stream']);
        $this->assertSame(
            MovieMateCinemaAssistant::TOOL_ALLOWLIST,
            array_column(array_column($recorded[0][0]->data()['tools'], 'function'), 'name'),
        );
        $toolMessage = collect($recorded[1][0]->data()['messages'])->firstWhere('role', 'tool');
        $this->assertStringContainsString('Nine Router Stream Movie', $toolMessage['content']);
    }

    public function test_client_cannot_override_any_nine_router_runtime_setting(): void
    {
        Http::preventStrayRequests();

        foreach ([
            'provider' => 'nine_router',
            'model' => 'client-selected-model',
            'base_url' => 'https://evil.example/v1',
            'api_key' => 'client-supplied-key',
        ] as $field => $value) {
            $this->postJson(route('user.ai.chatbot.submit'), [
                'message' => 'Không được đổi runtime',
                $field => $value,
            ])->assertUnprocessable()->assertJsonValidationErrors($field);
        }

        MovieMateCinemaAssistant::assertNeverPrompted();
        $this->assertDatabaseCount('ai_conversations', 0);
        $this->assertDatabaseCount('ai_messages', 0);
    }

    public function test_nine_router_stream_keeps_the_public_sse_contract_provider_neutral(): void
    {
        $this->app->instance(AiTextStreamer::class, new class implements AiTextStreamer
        {
            public function enabledAndConfigured(): bool
            {
                return true;
            }

            public function source(): string
            {
                return 'nine_router';
            }

            public function deltas(string $message, AiConversationContext $context): iterable
            {
                yield 'Xin chào ';
                yield 'từ MovieMate';
            }
        });

        $content = $this->withSession(['ai.chat.history' => []])->postJson(
            route('user.ai.chatbot.stream'),
            ['message' => 'Xin chào'],
        )->assertOk()->streamedContent();

        preg_match_all('/^event: ([a-z_]+)$/m', $content, $matches);
        $this->assertSame(['status', 'text_delta', 'text_delta', 'completed'], $matches[1]);
        $this->assertStringContainsString('"source":"nine_router"', $content);
        $this->assertStringNotContainsString('response.output_text', $content);
        $this->assertDatabaseCount('ai_conversations', 0);
        $this->assertDatabaseCount('ai_messages', 0);
    }

    public function test_nine_router_failure_is_sanitized_and_never_leaks_its_key(): void
    {
        $secret = 'NINE-ROUTER-SECRET-SENTINEL';
        $this->enableNineRouter('failure-model', $secret);
        Http::preventStrayRequests();
        Log::spy();
        MovieMateCinemaAssistant::fake(
            fn (): never => throw new RuntimeException('upstream timeout '.$secret),
        )->preventStrayPrompts();

        $result = app(AiChatbotService::class)->answer('Thử nhà cung cấp lỗi');

        $this->assertFalse($result['assistant_completed']);
        $this->assertSame('unavailable', $result['source']);
        $this->assertSame('timeout', $result['failure_category']);
        $this->assertStringNotContainsString($secret, json_encode($result, JSON_THROW_ON_ERROR));
        $this->assertDatabaseCount('ai_messages', 0);
        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $event, array $context): bool => ! str_contains(
                json_encode([$event, $context], JSON_THROW_ON_ERROR),
                $secret,
            ),
        )->once();
    }

    private function enableNineRouter(string $model, string $key = 'test-only-nine-router-key'): void
    {
        config()->set('moviemate-ai.enabled', true);
        config()->set('moviemate-ai.provider', 'nine_router');
        config()->set('moviemate-ai.model', $model);
        config()->set('ai.providers.nine_router', [
            'driver' => 'nine_router',
            'key' => $key,
            'url' => 'http://127.0.0.1:20128/v1',
        ]);
        app(AiManager::class)->forgetInstance('nine_router');
    }

    /** @return array<string, mixed> */
    private function completedResponse(string $text): array
    {
        return [
            'id' => 'chatcmpl_nine_router_completed',
            'object' => 'chat.completion',
            'model' => 'tool-compatible-model',
            'choices' => [[
                'index' => 0,
                'message' => ['role' => 'assistant', 'content' => $text],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        ];
    }

    /** @return array<string, mixed> */
    private function toolCallResponse(string $name, string $arguments): array
    {
        return [
            'id' => 'chatcmpl_nine_router_tool',
            'object' => 'chat.completion',
            'model' => 'provider-normalized-model-without-route-prefix',
            'choices' => [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [[
                        'id' => 'call_nine_router_search',
                        'type' => 'function',
                        'function' => ['name' => $name, 'arguments' => $arguments],
                    ]],
                ],
                'finish_reason' => 'tool_calls',
            ]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        ];
    }

    /** @param list<array<string, mixed>> $events */
    private function ssePayload(array $events): string
    {
        return collect($events)
            ->map(fn (array $event): string => 'data: '.json_encode($event, JSON_THROW_ON_ERROR))
            ->implode("\n\n")."\n\ndata: [DONE]\n\n";
    }
}
