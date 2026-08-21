<?php

namespace Tests\Feature\Ai;

use App\Ai\Agents\MovieMateCinemaAssistant;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class AiConversationPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_distinct_server_owned_conversations(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $other = User::factory()->create(['status' => 'active']);
        $existing = $this->conversation($owner, ['title' => 'Cuộc trò chuyện cũ']);

        $response = $this->actingAs($owner)->postJson(route('user.ai.conversations.store'), [
            'user_id' => $other->id,
            'role' => AiMessage::ROLE_ASSISTANT,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Cuộc trò chuyện mới');
        $created = AiConversation::query()->findOrFail($response->json('data.id'));
        $this->assertTrue($created->user->is($owner));
        $this->assertNotSame($existing->id, $created->id);
        $this->assertDatabaseHas('ai_conversations', ['id' => $existing->id]);
        $this->assertDatabaseMissing('ai_conversations', ['user_id' => $other->id]);

        $second = $this->actingAs($owner)->postJson(route('user.ai.conversations.store'));
        $second->assertCreated();
        $this->assertDatabaseCount('ai_conversations', 3);
    }

    public function test_list_is_owner_only_recent_first_and_paginated(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $other = User::factory()->create(['status' => 'active']);
        $oldest = null;
        $newest = null;

        foreach (range(1, 23) as $index) {
            $conversation = $this->conversation($owner, [
                'title' => 'Owner '.$index,
                'last_message_at' => now()->subMinutes(24 - $index),
            ]);
            $oldest ??= $conversation;
            $newest = $conversation;
        }
        $foreign = $this->conversation($other, [
            'title' => 'Private other-user chat',
            'last_message_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($owner)->getJson(route('user.ai.conversations.index'));

        $response->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('total', 23)
            ->assertJsonPath('per_page', 20)
            ->assertJsonPath('data.0.id', $newest->id);
        $listedIds = collect($response->json('data'))->pluck('id');
        $this->assertFalse($listedIds->contains($foreign->id));
        $this->assertFalse($listedIds->contains($oldest->id));
    }

    public function test_open_and_message_history_are_ordered_without_calling_provider(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $conversation = $this->conversation($owner);
        $first = $conversation->messages()->create([
            'role' => AiMessage::ROLE_USER,
            'content' => 'Câu đầu',
            'created_at' => now()->addHour(),
        ]);
        $second = $conversation->messages()->create([
            'role' => AiMessage::ROLE_ASSISTANT,
            'content' => 'Trả lời sau',
            'created_at' => now()->subHour(),
        ]);
        MovieMateCinemaAssistant::fake()->preventStrayPrompts();

        $this->actingAs($owner)
            ->getJson(route('user.ai.conversations.show', $conversation->id))
            ->assertOk()
            ->assertJsonPath('data.messages.0.id', $first->id)
            ->assertJsonPath('data.messages.1.id', $second->id);

        $this->actingAs($owner)
            ->getJson(route('user.ai.conversations.messages.index', $conversation->id))
            ->assertOk()
            ->assertJsonPath('data.0.content', 'Câu đầu')
            ->assertJsonPath('data.1.content', 'Trả lời sau');

        MovieMateCinemaAssistant::assertNeverPrompted();
    }

    public function test_message_history_is_bounded_paginated_and_chronological(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $conversation = $this->conversation($owner);
        $messages = collect();

        foreach (range(1, 55) as $index) {
            $messages->push($conversation->messages()->create([
                'role' => $index % 2 === 0 ? AiMessage::ROLE_ASSISTANT : AiMessage::ROLE_USER,
                'content' => 'Tin nhắn '.$index,
            ]));
        }

        $this->actingAs($owner)
            ->getJson(route('user.ai.conversations.show', $conversation->id))
            ->assertOk()
            ->assertJsonCount(50, 'data.messages')
            ->assertJsonPath('data.messages.0.id', $messages[0]->id)
            ->assertJsonPath('data.messages.49.id', $messages[49]->id)
            ->assertJsonPath('data.messages_pagination.total', 55)
            ->assertJsonPath('data.messages_pagination.per_page', 50);

        $this->actingAs($owner)
            ->getJson(route('user.ai.conversations.messages.index', [
                'conversation' => $conversation->id,
                'page' => 2,
            ]))
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.id', $messages[50]->id)
            ->assertJsonPath('data.4.id', $messages[54]->id);
    }

    public function test_owner_can_continue_and_only_current_turn_is_sent_to_provider(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $other = User::factory()->create(['status' => 'active']);
        $conversation = $this->conversation($owner);
        $conversation->messages()->create([
            'role' => AiMessage::ROLE_USER,
            'content' => 'Nội dung lịch sử không được gửi',
        ]);
        $this->fakeAssistant(['Câu trả lời đã hoàn tất.']);

        $response = $this->actingAs($owner)->postJson(
            route('user.ai.conversations.messages.store', $conversation->id),
            ['message' => 'Tối nay có phim gì?'],
        );

        $response->assertCreated()
            ->assertJsonPath('data.assistant_completed', true)
            ->assertJsonPath('data.answer', 'Câu trả lời đã hoàn tất.');
        $this->assertDatabaseHas('ai_messages', [
            'ai_conversation_id' => $conversation->id,
            'role' => AiMessage::ROLE_USER,
            'content' => 'Tối nay có phim gì?',
        ]);
        $this->assertDatabaseHas('ai_messages', [
            'ai_conversation_id' => $conversation->id,
            'role' => AiMessage::ROLE_ASSISTANT,
            'content' => 'Câu trả lời đã hoàn tất.',
        ]);
        $this->assertNotNull($conversation->refresh()->last_message_at);
        MovieMateCinemaAssistant::assertPrompted('Tối nay có phim gì?');

        $before = $conversation->messages()->count();
        $this->actingAs($other)->postJson(
            route('user.ai.conversations.messages.store', $conversation->id),
            ['message' => 'Tin nhắn xâm nhập'],
        )->assertNotFound();
        $this->assertSame($before, $conversation->messages()->count());
    }

    public function test_existing_chatbot_route_persists_authenticated_chat_and_rejects_foreign_id(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $other = User::factory()->create(['status' => 'active']);
        $foreign = $this->conversation($other);
        $this->fakeAssistant(['Trả lời qua chatbot hiện hữu.']);

        $response = $this->actingAs($owner)->post(route('user.ai.chatbot.submit'), [
            'message' => 'Phim gia đình tối nay',
        ]);

        $created = $owner->aiConversations()->sole();
        $response->assertRedirect(route('user.ai.chatbot', ['conversation' => $created->id]));
        $this->assertSame('Phim gia đình tối nay', $created->title);
        $this->assertDatabaseHas('ai_messages', [
            'ai_conversation_id' => $created->id,
            'role' => AiMessage::ROLE_ASSISTANT,
            'content' => 'Trả lời qua chatbot hiện hữu.',
        ]);

        $this->actingAs($owner)->postJson(route('user.ai.chatbot.submit'), [
            'conversation_id' => $foreign->id,
            'message' => 'Không được nối vào hội thoại người khác',
        ])->assertNotFound();
        $this->assertDatabaseCount('ai_messages', 2);
    }

    public function test_first_meaningful_message_derives_a_safe_bounded_local_title(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $conversation = $this->conversation($owner);
        $this->fakeAssistant(['OK']);

        $message = "  <b>Tối nay</b>   có phim hành động nào?\n".str_repeat('x', 150);
        $this->actingAs($owner)->postJson(
            route('user.ai.conversations.messages.store', $conversation->id),
            ['message' => $message],
        )->assertCreated();

        $title = $conversation->refresh()->title;
        $this->assertStringStartsWith('Tối nay có phim hành động nào?', $title);
        $this->assertLessThanOrEqual(120, mb_strlen($title));
        $this->assertStringNotContainsString('<b>', $title);
    }

    public function test_rename_is_owner_only_validated_and_safe_to_render(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $other = User::factory()->create(['status' => 'active']);
        $conversation = $this->conversation($owner);

        $this->actingAs($owner)->patchJson(
            route('user.ai.conversations.update', $conversation->id),
            ['title' => '  <script>alert("x")</script>   Phim tối nay  '],
        )->assertOk()->assertJsonPath('data.title', 'alert("x") Phim tối nay');

        $this->actingAs($owner)
            ->get(route('user.ai.chatbot', ['conversation' => $conversation->id]))
            ->assertOk()
            ->assertDontSee('<script>alert("x")</script>', false)
            ->assertSee('alert(&quot;x&quot;) Phim tối nay', false);

        $this->actingAs($owner)->patchJson(
            route('user.ai.conversations.update', $conversation->id),
            ['title' => 'Không được đổi', 'user_id' => $other->id],
        )->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->assertSame('alert("x") Phim tối nay', $conversation->refresh()->title);

        $this->actingAs($other)->patchJson(
            route('user.ai.conversations.update', $conversation->id),
            ['title' => 'Xâm nhập'],
        )->assertNotFound();
        $this->actingAs($owner)->patchJson(
            route('user.ai.conversations.update', $conversation->id),
            ['title' => '   '],
        )->assertUnprocessable();
        $this->actingAs($owner)->patchJson(
            route('user.ai.conversations.update', $conversation->id),
            ['title' => str_repeat('a', 121)],
        )->assertUnprocessable();
    }

    public function test_delete_is_owner_only_and_cascades_messages(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $other = User::factory()->create(['status' => 'active']);
        $conversation = $this->conversation($owner);
        $message = $conversation->messages()->create([
            'role' => AiMessage::ROLE_USER,
            'content' => 'Sẽ bị xóa cùng cuộc trò chuyện',
        ]);

        $this->actingAs($other)->deleteJson(
            route('user.ai.conversations.destroy', $conversation->id),
        )->assertNotFound();
        $this->assertDatabaseHas('ai_conversations', ['id' => $conversation->id]);

        $this->actingAs($owner)->deleteJson(
            route('user.ai.conversations.destroy', $conversation->id),
        )->assertNoContent();
        $this->assertDatabaseMissing('ai_conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('ai_messages', ['id' => $message->id]);
    }

    public function test_http_idor_matrix_hides_every_foreign_conversation_operation(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $other = User::factory()->create(['status' => 'active']);
        $conversation = $this->conversation($owner, ['title' => 'Bí mật của khách A']);
        $conversation->messages()->create([
            'role' => AiMessage::ROLE_USER,
            'content' => 'Nội dung bí mật',
        ]);

        $this->actingAs($other)->getJson(route('user.ai.conversations.index'))
            ->assertOk()->assertJsonMissing(['title' => 'Bí mật của khách A']);
        $this->actingAs($other)->getJson(route('user.ai.conversations.show', $conversation->id))
            ->assertNotFound();
        $this->actingAs($other)->getJson(route('user.ai.conversations.messages.index', $conversation->id))
            ->assertNotFound();
        $this->actingAs($other)->postJson(
            route('user.ai.conversations.messages.store', $conversation->id),
            ['message' => 'Xâm nhập'],
        )->assertNotFound();
        $this->actingAs($other)->patchJson(
            route('user.ai.conversations.update', $conversation->id),
            ['title' => 'Xâm nhập'],
        )->assertNotFound();
        $this->actingAs($other)->deleteJson(route('user.ai.conversations.destroy', $conversation->id))
            ->assertNotFound();

        $this->assertDatabaseHas('ai_conversations', [
            'id' => $conversation->id,
            'user_id' => $owner->id,
            'title' => 'Bí mật của khách A',
        ]);
        $this->assertDatabaseCount('ai_messages', 1);
    }

    public function test_guest_chat_is_bounded_and_never_creates_permanent_rows(): void
    {
        $seedHistory = collect(range(1, 20))->map(fn (int $index): array => [
            'message' => 'Câu hỏi tạm thời '.$index,
            'response' => 'Trả lời tạm thời '.$index,
            'created_at' => now()->toIso8601String(),
        ])->all();

        $response = $this->withSession(['ai.chat.history' => $seedHistory])
            ->post(route('user.ai.chatbot.submit'), ['message' => 'Câu hỏi tạm thời 21']);

        $response->assertRedirect(route('user.ai.chatbot'));
        $response->assertSessionHas('ai.chat.history', fn (array $history): bool => count($history) === 20
            && $history[0]['message'] === 'Câu hỏi tạm thời 2'
            && $history[19]['message'] === 'Câu hỏi tạm thời 21');
        $this->assertDatabaseCount('ai_conversations', 0);
        $this->assertDatabaseCount('ai_messages', 0);

        $owner = User::factory()->create(['status' => 'active']);
        $conversation = $this->conversation($owner);
        $this->getJson(route('user.ai.conversations.index'))->assertUnauthorized();
        $this->postJson(route('user.ai.conversations.store'))->assertUnauthorized();
        $this->getJson(route('user.ai.conversations.show', $conversation->id))->assertUnauthorized();
        $this->getJson(route('user.ai.conversations.messages.index', $conversation->id))->assertUnauthorized();
        $this->postJson(
            route('user.ai.conversations.messages.store', $conversation->id),
            ['message' => 'Guest không được tiếp tục'],
        )->assertUnauthorized();
        $this->patchJson(
            route('user.ai.conversations.update', $conversation->id),
            ['title' => 'Guest không được đổi tên'],
        )->assertUnauthorized();
        $this->deleteJson(route('user.ai.conversations.destroy', $conversation->id))->assertUnauthorized();
    }

    public function test_role_ownership_and_oversized_message_injection_is_rejected(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $other = User::factory()->create(['status' => 'active']);
        $conversation = $this->conversation($owner);

        foreach (['system', 'assistant', 'tool'] as $role) {
            $this->actingAs($owner)->postJson(
                route('user.ai.conversations.messages.store', $conversation->id),
                ['message' => 'Không được lưu', 'role' => $role],
            )->assertUnprocessable()->assertJsonValidationErrors('role');
        }
        $this->actingAs($owner)->postJson(
            route('user.ai.conversations.messages.store', $conversation->id),
            ['message' => 'Không được lưu', 'user_id' => $other->id],
        )->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->actingAs($owner)->postJson(
            route('user.ai.conversations.messages.store', $conversation->id),
            ['message' => str_repeat('x', 1001)],
        )->assertUnprocessable()->assertJsonValidationErrors('message');
        $this->actingAs($owner)->postJson(
            route('user.ai.conversations.messages.store', $conversation->id),
            ['message' => '   '],
        )->assertUnprocessable()->assertJsonValidationErrors('message');

        $this->assertDatabaseCount('ai_messages', 0);
        $this->assertSame($owner->id, $conversation->refresh()->user_id);
    }

    public function test_provider_failure_keeps_only_user_message_and_stores_no_error_secret(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $conversation = $this->conversation($owner);
        $this->enableAssistant();
        MovieMateCinemaAssistant::fake(function (): never {
            throw new RuntimeException('provider-secret-stack-detail');
        })->preventStrayPrompts();

        $this->actingAs($owner)->postJson(
            route('user.ai.conversations.messages.store', $conversation->id),
            ['message' => 'Tin nhắn vẫn được lưu'],
        )->assertServiceUnavailable()
            ->assertJsonPath('data.assistant_completed', false);

        $messages = $conversation->messages()->orderBy('id')->get();
        $this->assertCount(1, $messages);
        $this->assertSame(AiMessage::ROLE_USER, $messages->first()->role);
        $this->assertSame('Tin nhắn vẫn được lưu', $messages->first()->content);
        $stored = $messages->pluck('content')->implode(' ');
        $this->assertStringNotContainsString('provider-secret', $stored);
        $this->assertStringNotContainsString('RuntimeException', $stored);
    }

    private function conversation(User $user, array $attributes = []): AiConversation
    {
        return $user->aiConversations()->create([
            'title' => 'Cuộc trò chuyện mới',
            ...$attributes,
        ]);
    }

    private function fakeAssistant(array $responses): void
    {
        $this->enableAssistant();
        MovieMateCinemaAssistant::fake($responses)->preventStrayPrompts();
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
