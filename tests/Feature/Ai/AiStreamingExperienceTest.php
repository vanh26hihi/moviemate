<?php

namespace Tests\Feature\Ai;

use App\Ai\AiConversationContext;
use App\Ai\AiStreamCompletionGate;
use App\Ai\Contracts\AiTextStreamer;
use App\Models\AiMessage;
use App\Models\FoodItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AiStreamingExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_stream_has_stable_order_and_persists_only_after_completion(): void
    {
        $this->fakeStreamer(['Xin ', 'chào MovieMate']);
        $user = User::factory()->create(['status' => 'active']);
        $conversation = $user->aiConversations()->create(['title' => 'Stream']);

        $response = $this->actingAs($user)->postJson(
            route('user.ai.conversations.messages.stream', $conversation),
            ['message' => 'Xin chào'],
        );
        $content = $response->streamedContent();

        $response->assertOk()->assertHeader('content-type', 'text/event-stream; charset=UTF-8');
        $this->assertEventOrder($content, ['status', 'conversation', 'text_delta', 'completed']);
        $this->assertStringContainsString('Xin ', $content);
        $this->assertStringContainsString('chào MovieMate', $content);
        $this->assertDatabaseHas('ai_messages', ['role' => AiMessage::ROLE_USER, 'content' => 'Xin chào']);
        $this->assertDatabaseHas('ai_messages', ['role' => AiMessage::ROLE_ASSISTANT, 'content' => 'Xin chào MovieMate']);
    }

    public function test_midstream_failure_emits_safe_error_and_retry_reuses_user_row(): void
    {
        $this->fakeStreamer(['Một phần'], new RuntimeException('provider secret failure'));
        $user = User::factory()->create(['status' => 'active']);
        $conversation = $user->aiConversations()->create(['title' => 'Retry']);
        $failed = $this->actingAs($user)->postJson(
            route('user.ai.conversations.messages.stream', $conversation),
            ['message' => 'Thử giúp tôi'],
        )->streamedContent();

        $this->assertStringContainsString('event: error', $failed);
        $this->assertStringNotContainsString('provider secret failure', $failed);
        $this->assertSame(1, $conversation->messages()->count());
        $userMessage = $conversation->messages()->sole();

        $this->fakeStreamer(['Đã hoàn tất']);
        $retried = $this->actingAs($user)->postJson(
            route('user.ai.conversations.messages.retry', [$conversation, $userMessage]),
            [],
        )->streamedContent();

        $this->assertStringContainsString('event: completed', $retried);
        $this->assertSame(2, $conversation->messages()->count());
        $this->assertSame(1, $conversation->messages()->where('role', AiMessage::ROLE_USER)->count());
    }

    public function test_completed_fallback_stream_emits_cards_and_guest_creates_no_database_rows(): void
    {
        config()->set('moviemate-ai.enabled', false);
        FoodItem::query()->create(['name' => 'Bắp rang', 'description' => 'Món công khai', 'price' => 55_000, 'active' => true]);

        $content = $this->withSession(['ai.chat.history' => []])->postJson(
            route('user.ai.chatbot.stream'),
            ['message' => 'MovieMate có đồ ăn gì?'],
        )->streamedContent();

        $this->assertEventOrder($content, ['status', 'text_delta', 'cards', 'completed']);
        $this->assertStringContainsString('Bắp rang', $content);
        $this->assertDatabaseCount('ai_conversations', 0);
        $this->assertDatabaseCount('ai_messages', 0);
    }

    public function test_stream_ownership_is_checked_before_any_message_is_written(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $other = User::factory()->create(['status' => 'active']);
        $conversation = $owner->aiConversations()->create(['title' => 'Riêng tư']);

        $this->actingAs($other)->postJson(
            route('user.ai.conversations.messages.stream', $conversation),
            ['message' => 'Đọc dữ liệu riêng'],
        )->assertNotFound();

        $this->assertDatabaseCount('ai_messages', 0);
    }

    public function test_client_abort_gate_leaves_only_the_already_stored_user_message(): void
    {
        $this->fakeStreamer(['Không được lưu']);
        $gate = $this->mock(AiStreamCompletionGate::class);
        $gate->shouldReceive('clientConnected')->once()->andReturnFalse();
        $user = User::factory()->create(['status' => 'active']);
        $conversation = $user->aiConversations()->create(['title' => 'Abort']);

        $content = $this->actingAs($user)->postJson(
            route('user.ai.conversations.messages.stream', $conversation),
            ['message' => 'Dừng giữa chừng'],
        )->streamedContent();

        $this->assertStringNotContainsString('event: completed', $content);
        $this->assertSame(1, $conversation->messages()->count());
        $this->assertSame(AiMessage::ROLE_USER, $conversation->messages()->sole()->role);
    }

    public function test_historical_payload_is_revalidated_and_strips_stale_or_malicious_actions(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $conversation = $user->aiConversations()->create(['title' => 'Stored payload']);
        $message = $conversation->messages()->create(['role' => AiMessage::ROLE_ASSISTANT, 'content' => 'An toàn']);
        $message->forceFill(['structured_payload' => [
            'version' => 1,
            'cards' => [[
                'type' => 'movie', 'id' => 42, 'title' => '<script>alert(1)</script> Phim sạch',
                'stored_status' => 'now_showing', 'details_url' => 'javascript:alert(1)',
                'actions' => [['type' => 'book_showtime', 'url' => 'https://evil.example']],
            ]],
        ]])->save();

        $card = $this->actingAs($user)->getJson(route('user.ai.conversations.messages.index', $conversation))
            ->assertOk()->json('data.0.historical_cards.0');

        $this->assertSame('Phim sạch', $card['title']);
        $this->assertArrayNotHasKey('actions', $card);
        $this->assertArrayNotHasKey('details_url', $card);
        $this->assertStringNotContainsString('evil.example', json_encode($card, JSON_THROW_ON_ERROR));
    }

    public function test_global_widget_exposes_accessible_streaming_shell_and_ai_modules_avoid_html_injection_sinks(): void
    {
        $this->get(route('user.ai.chatbot'))->assertOk()
            ->assertSee('data-ai-assistant', false)
            ->assertSee('role="dialog"', false)
            ->assertSee('role="log"', false)
            ->assertSee('for="ai-assistant-message"', false)
            ->assertSee('Nhập tin nhắn cho MovieMate AI')
            ->assertSee(route('user.ai.chatbot.stream'), false);

        $source = file_get_contents(resource_path('js/ai-chat.js')).file_get_contents(resource_path('js/ai/cards.js'));
        $this->assertStringNotContainsString('innerHTML', $source);
        $this->assertStringNotContainsString('insertAdjacentHTML', $source);
        $this->assertStringContainsString('textContent', $source);
    }

    private function fakeStreamer(array $deltas, ?\Throwable $failure = null): void
    {
        $this->app->instance(AiTextStreamer::class, new class($deltas, $failure) implements AiTextStreamer
        {
            public function __construct(private array $deltas, private ?\Throwable $failure) {}

            public function enabledAndConfigured(): bool
            {
                return true;
            }

            public function source(): string
            {
                return 'test';
            }

            public function deltas(string $message, AiConversationContext $context): iterable
            {
                foreach ($this->deltas as $delta) {
                    yield $delta;
                }
                if ($this->failure) {
                    throw $this->failure;
                }
            }
        });
    }

    private function assertEventOrder(string $content, array $events): void
    {
        $offset = -1;
        foreach ($events as $event) {
            $position = strpos($content, 'event: '.$event, $offset + 1);
            $this->assertNotFalse($position, "Missing SSE event {$event}.");
            $this->assertGreaterThan($offset, $position);
            $offset = $position;
        }
    }
}
