<?php

namespace App\Http\Controllers\User;

use App\Ai\AiConversationContext;
use App\Ai\AiConversationContextBuilder;
use App\Ai\AiStreamCompletionGate;
use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\AiChatStreamService;
use App\Services\AiConversationService;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedEvent;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class AiChatStreamController extends Controller
{
    public function guest(
        Request $request,
        AiConversationContextBuilder $contexts,
        AiChatStreamService $chat,
        AiStreamCompletionGate $completion,
    ): StreamedResponse {
        $message = $this->validatedMessage($request);
        $history = $this->guestHistory($request);
        $context = $contexts->forGuest($history);
        $history[] = ['message' => $message, 'response' => null, 'created_at' => now()->toIso8601String()];
        $history = array_values(array_slice($history, -20));
        $request->session()->put('ai.chat.history', $history);

        return $this->response(function () use ($request, $chat, $completion, $context, $message): \Generator {
            $result = yield from $this->events($chat, $completion, $context, $message, 'guest');
            if ($result !== null) {
                $history = $this->guestHistory($request);
                $last = array_key_last($history);
                if ($last !== null && $history[$last]['message'] === $message && $history[$last]['response'] === null) {
                    $history[$last]['response'] = $result['answer'];
                    $request->session()->put(['ai.chat.history' => $history, 'ai.chat.meta' => $result]);
                    $request->session()->save();
                }
            }
        });
    }

    public function retryGuest(
        Request $request,
        AiConversationContextBuilder $contexts,
        AiChatStreamService $chat,
        AiStreamCompletionGate $completion,
    ): StreamedResponse {
        $message = $this->validatedMessage($request);
        $history = $this->guestHistory($request);
        $last = array_key_last($history);
        abort_unless($last !== null && $history[$last]['message'] === $message && $history[$last]['response'] === null, 422);
        $context = $contexts->forGuest(array_slice($history, 0, $last));

        return $this->response(function () use ($request, $chat, $completion, $context, $message): \Generator {
            $result = yield from $this->events($chat, $completion, $context, $message, 'guest');
            if ($result !== null) {
                $history = $this->guestHistory($request);
                $last = array_key_last($history);
                if ($last !== null && $history[$last]['message'] === $message && $history[$last]['response'] === null) {
                    $history[$last]['response'] = $result['answer'];
                    $request->session()->put(['ai.chat.history' => $history, 'ai.chat.meta' => $result]);
                    $request->session()->save();
                }
            }
        });
    }

    public function authenticated(
        Request $request,
        int $conversation,
        AiConversationService $conversations,
        AiConversationContextBuilder $contexts,
        AiChatStreamService $chat,
        AiStreamCompletionGate $completion,
    ): StreamedResponse {
        $message = $this->validatedMessage($request);
        $owned = $conversations->findOwned($request->user(), $conversation);
        Gate::authorize('continue', $owned);
        $context = $contexts->forConversation($request->user(), $owned);
        $userMessage = $conversations->appendUserMessage($request->user(), $owned, $message);

        return $this->authenticatedResponse($request, $owned, $userMessage, $context, $chat, $completion, $conversations);
    }

    public function retryAuthenticated(
        Request $request,
        int $conversation,
        int $message,
        AiConversationService $conversations,
        AiConversationContextBuilder $contexts,
        AiChatStreamService $chat,
        AiStreamCompletionGate $completion,
    ): StreamedResponse {
        $this->validateOverrides($request);
        $owned = $conversations->findOwned($request->user(), $conversation);
        Gate::authorize('continue', $owned);
        $userMessage = $conversations->retryableUserMessage($request->user(), $owned, $message);
        $context = $contexts->forConversationBefore($request->user(), $owned, $userMessage->id);

        return $this->authenticatedResponse($request, $owned, $userMessage, $context, $chat, $completion, $conversations);
    }

    private function authenticatedResponse(
        Request $request,
        AiConversation $conversation,
        AiMessage $userMessage,
        AiConversationContext $context,
        AiChatStreamService $chat,
        AiStreamCompletionGate $completion,
        AiConversationService $conversations,
    ): StreamedResponse {
        return $this->response(function () use ($request, $conversation, $userMessage, $context, $chat, $completion, $conversations): \Generator {
            yield $this->event('status', ['message' => 'MovieMate đang suy nghĩ…']);
            yield $this->event('conversation', [
                'conversation_id' => $conversation->id,
                'user_message_id' => $userMessage->id,
                'title' => $conversation->fresh()->title,
                'retry_url' => route('user.ai.conversations.messages.retry', [$conversation, $userMessage]),
            ]);
            $result = yield from $this->events(
                $chat,
                $completion,
                $context,
                $userMessage->content,
                'authenticated',
                emitStatus: false,
                emitCards: false,
                emitCompleted: false,
            );
            if ($result === null || ! $completion->clientConnected()) {
                return;
            }
            $assistant = $conversations->appendAssistantMessage(
                $request->user(),
                $conversation,
                $result['answer'],
                $result['structured_response'] ?? null,
            );
            $cards = $result['structured_response']['cards'] ?? [];
            if ($cards !== []) {
                yield $this->event('cards', [
                    'cards' => $cards,
                    'text' => $result['structured_response']['text'] ?? $result['answer'],
                ]);
            }
            yield $this->event('completed', [
                'conversation_id' => $conversation->id,
                'assistant_message_id' => $assistant->id,
                'title' => $conversation->fresh()->title,
                'source' => $result['source'],
            ]);
        });
    }

    /** @return \Generator<int, StreamedEvent, mixed, array<string, mixed>|null> */
    private function events(
        AiChatStreamService $chat,
        AiStreamCompletionGate $completion,
        AiConversationContext $context,
        string $message,
        string $audience,
        bool $emitStatus = true,
        bool $emitCards = true,
        bool $emitCompleted = true,
    ): \Generator {
        try {
            if ($emitStatus) {
                yield $this->event('status', ['message' => 'MovieMate đang suy nghĩ…']);
            }
            $stream = $chat->stream($message, $context, $audience);
            foreach ($stream as $delta) {
                if (! $completion->clientConnected()) {
                    return null;
                }
                yield $this->event('text_delta', ['delta' => $delta]);
            }
            $result = $stream->getReturn();
            if (! ($result['assistant_completed'] ?? false) || ! $completion->clientConnected()) {
                throw new \RuntimeException('AI stream did not complete.');
            }
            $cards = $result['structured_response']['cards'] ?? [];
            if ($emitCards && $cards !== []) {
                yield $this->event('cards', [
                    'cards' => $cards,
                    'text' => $result['structured_response']['text'] ?? $result['answer'],
                ]);
            }
            if ($emitCompleted) {
                yield $this->event('completed', ['source' => $result['source']]);
            }

            return $result;
        } catch (Throwable $exception) {
            Log::warning('AI streaming response failed.', ['exception' => $exception::class]);
            yield $this->event('error', [
                'message' => 'MovieMate AI tạm thời không thể trả lời. Bạn có thể thử lại.',
            ]);

            return null;
        }
    }

    private function response(callable $callback): StreamedResponse
    {
        return response()->eventStream($callback, [
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
        ], null);
    }

    private function event(string $type, array $data): StreamedEvent
    {
        return new StreamedEvent($type, ['type' => $type, 'data' => $data]);
    }

    private function validatedMessage(Request $request): string
    {
        $validated = $request->validate([
            'message' => ['bail', 'required', 'string', 'max:'.AiConversationService::MESSAGE_MAX_LENGTH, 'not_regex:/^\s*$/u'],
            ...$this->overrideRules(),
        ]);

        return trim($validated['message']);
    }

    private function validateOverrides(Request $request): void
    {
        $request->validate(['message' => ['prohibited'], ...$this->overrideRules()]);
    }

    /** @return array<string, list<string>> */
    private function overrideRules(): array
    {
        return collect([
            'conversation_id', 'user_id', 'role', 'assistant', 'system', 'history', 'messages',
            'system_prompt', 'developer_prompt', 'context', 'assistant_history', 'provider', 'model',
            'base_url', 'api_key', 'temperature', 'max_tokens', 'max_steps', 'steps', 'timeout', 'tool_registry',
            'structured_payload', 'cards', 'bookable', 'booking_url', 'actions',
        ])->mapWithKeys(fn (string $field): array => [$field => ['prohibited']])->all();
    }

    /** @return list<array{message: string, response: ?string, created_at?: string}> */
    private function guestHistory(Request $request): array
    {
        return collect($request->session()->get('ai.chat.history', []))
            ->filter(fn ($turn): bool => is_array($turn) && is_string($turn['message'] ?? null))
            ->map(fn (array $turn): array => [
                'message' => trim($turn['message']),
                'response' => is_string($turn['response'] ?? null) ? $turn['response'] : null,
                'created_at' => is_string($turn['created_at'] ?? null) ? $turn['created_at'] : now()->toIso8601String(),
            ])->values()->all();
    }
}
