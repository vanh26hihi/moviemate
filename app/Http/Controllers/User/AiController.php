<?php

namespace App\Http\Controllers\User;

use App\Ai\AiConversationContextBuilder;
use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Cinema;
use App\Models\Genre;
use App\Services\AiChatbotService;
use App\Services\AiConversationService;
use App\Services\AiMovieRecommendationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class AiController extends Controller
{
    public function recommend(Request $request): View
    {
        return $this->recommendationView(
            preferences: $request->session()->get('ai.recommend.preferences', []),
            result: $request->session()->get('ai.recommend.result'),
        );
    }

    public function recommendStore(Request $request, AiMovieRecommendationService $service): View
    {
        $preferences = $request->validate([
            'mood' => ['required', 'string', 'in:happy,sad,stress,chill,excited,romantic'],
            'genres' => ['nullable', 'array', 'max:5'],
            'genres.*' => ['string', 'max:100'],
            'companion' => ['required', 'string', 'in:alone,couple,friends,family'],
            'preferred_time' => ['required', 'string', 'in:tonight,tomorrow,weekend,after_21,morning,afternoon'],
            'location' => ['nullable', 'string', 'max:191'],
            ...$this->prohibitedAiOverrides(),
        ]);

        $preferences['genres'] = array_values(array_unique($preferences['genres'] ?? []));
        $result = $service->recommend($preferences);

        $request->session()->put([
            'ai.recommend.preferences' => $preferences,
            'ai.recommend.result' => $result,
        ]);

        return $this->recommendationView($preferences, $result);
    }

    public function chatbot(Request $request, AiConversationService $conversations): View
    {
        $currentConversation = null;
        $conversationList = collect();

        if ($request->user()) {
            $conversationList = $conversations->recentForUser($request->user());
            if ($request->has('conversation')) {
                $currentConversation = $conversations->findOwned($request->user(), $request->integer('conversation'));
                Gate::authorize('view', $currentConversation);
            } else {
                $currentConversation = $conversationList->first();
            }

            $history = $currentConversation
                ? $this->persistedChatHistory($conversations, $currentConversation)
                : collect();
        } else {
            $history = $this->chatHistory($request);
        }

        return view('user.ai.chatbot', [
            'chatHistory' => $history,
            'currentChat' => $history->last(),
            'chatMeta' => $request->session()->get('ai.chat.meta'),
            'currentConversation' => $currentConversation,
            'conversationList' => $conversationList,
        ]);
    }

    public function chatbotStore(
        Request $request,
        AiChatbotService $service,
        AiConversationService $conversations,
        AiConversationContextBuilder $contextBuilder,
    ): RedirectResponse {
        $validated = $request->validate([
            'message' => ['bail', 'required', 'string', 'max:'.AiConversationService::MESSAGE_MAX_LENGTH, 'not_regex:/^\s*$/u'],
            'conversation_id' => ['nullable', 'integer', 'min:1'],
            'user_id' => ['prohibited'],
            'role' => ['prohibited'],
            'assistant' => ['prohibited'],
            'system' => ['prohibited'],
            ...$this->prohibitedAiOverrides(),
        ]);

        if ($request->user()) {
            $conversation = isset($validated['conversation_id'])
                ? $conversations->findOwned($request->user(), (int) $validated['conversation_id'])
                : $conversations->createForUser($request->user());
            Gate::authorize('continue', $conversation);

            try {
                $conversationResult = $conversations->continueOwned(
                    $request->user(),
                    $conversation,
                    $validated['message'],
                );
                $request->session()->put('ai.chat.meta', $conversationResult['result']);
            } catch (Throwable $exception) {
                Log::warning('Authenticated AI conversation failed after storing the user message.', [
                    'exception' => $exception::class,
                    'conversation_id' => $conversation->id,
                ]);
                $request->session()->put('ai.chat.meta', [
                    'source' => 'unavailable',
                    'message' => 'MovieMate AI tạm thời không thể trả lời. Tin nhắn của bạn đã được lưu để thử lại sau.',
                ]);
            }

            return to_route('user.ai.chatbot', ['conversation' => $conversation->id]);
        }

        $history = $this->chatHistory($request);
        $context = $contextBuilder->forGuest($request->session()->get('ai.chat.history', []));
        $result = $service->answer($validated['message'], $context, 'guest');
        $history->push([
            'message' => $validated['message'],
            'response' => ($result['assistant_completed'] ?? false) ? $result['answer'] : null,
            'created_at' => now()->toIso8601String(),
        ]);

        $request->session()->put([
            'ai.chat.history' => $history->take(-20)->map(fn ($turn): array => (array) $turn)->values()->all(),
            'ai.chat.meta' => $result,
        ]);

        return to_route('user.ai.chatbot');
    }

    /** @return array<string, list<string>> */
    private function prohibitedAiOverrides(): array
    {
        return collect([
            'history', 'messages', 'system_prompt', 'developer_prompt', 'context', 'assistant_history',
            'provider', 'model', 'temperature', 'max_tokens', 'max_steps', 'steps', 'timeout', 'tool_registry',
        ])->mapWithKeys(fn (string $field): array => [$field => ['prohibited']])->all();
    }

    private function recommendationView(array $preferences, ?array $result): View
    {
        return view('user.ai.recommend', [
            'preferences' => $preferences,
            'recommendations' => $result['recommendations'] ?? null,
            'recommendationMeta' => $result,
            'genres' => Genre::query()->orderBy('name')->get(),
            'cinemas' => Cinema::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    private function chatHistory(Request $request): Collection
    {
        return collect($request->session()->get('ai.chat.history', []))
            ->filter(fn ($chat): bool => is_array($chat))
            ->map(function (array $chat): object {
                $chat['created_at'] = isset($chat['created_at'])
                    ? now()->parse($chat['created_at'])
                    : now();

                return (object) $chat;
            })
            ->values();
    }

    private function persistedChatHistory(
        AiConversationService $conversations,
        AiConversation $conversation,
    ): Collection {
        $history = collect();
        $current = null;

        foreach ($conversations->recentOrderedMessages($conversation) as $message) {
            if ($message->role === AiMessage::ROLE_USER) {
                $current = (object) [
                    'message' => $message->content,
                    'response' => null,
                    'created_at' => $message->created_at,
                ];
                $history->push($current);
            } elseif ($message->role === AiMessage::ROLE_ASSISTANT && $current !== null) {
                $current->response = $message->content;
            }
        }

        return $history;
    }
}
