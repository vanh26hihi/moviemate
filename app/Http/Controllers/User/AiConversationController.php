<?php

namespace App\Http\Controllers\User;

use App\Ai\AiHistoricalMessagePresenter;
use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\AiConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AiConversationController extends Controller
{
    public function index(Request $request, AiConversationService $conversations): JsonResponse
    {
        Gate::authorize('viewAny', AiConversation::class);
        $paginator = $conversations->listForUser($request->user());
        $paginator->through(fn (AiConversation $conversation): array => $this->conversationData($conversation));

        return response()->json($paginator);
    }

    public function store(Request $request, AiConversationService $conversations): JsonResponse|RedirectResponse
    {
        $conversation = $conversations->createForUser($request->user());

        if (! $request->expectsJson()) {
            return to_route('user.ai.chatbot', ['conversation' => $conversation->id]);
        }

        return response()->json(['data' => $this->conversationData($conversation)], 201);
    }

    public function show(Request $request, int $conversation, AiConversationService $conversations, AiHistoricalMessagePresenter $messagesPresenter): JsonResponse
    {
        $owned = $conversations->findOwned($request->user(), $conversation);
        Gate::authorize('view', $owned);
        $messages = $conversations->paginateMessages($owned);

        return response()->json([
            'data' => [
                ...$this->conversationData($owned),
                'messages' => $messages->getCollection()
                    ->map(fn (AiMessage $message): array => $messagesPresenter->present($message))
                    ->all(),
                'messages_pagination' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total(),
                ],
            ],
        ]);
    }

    public function update(Request $request, int $conversation, AiConversationService $conversations): JsonResponse|RedirectResponse
    {
        $owned = $conversations->findOwned($request->user(), $conversation);
        Gate::authorize('update', $owned);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:'.AiConversationService::TITLE_MAX_LENGTH],
            'user_id' => ['prohibited'],
            'role' => ['prohibited'],
        ]);
        $normalized = $conversations->normalizeTitle($validated['title']);
        $validator = validator(['title' => $normalized], ['title' => ['required']]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $updated = $conversations->renameOwned($request->user(), $owned, $normalized);

        if (! $request->expectsJson()) {
            return to_route('user.ai.chatbot', ['conversation' => $updated->id]);
        }

        return response()->json(['data' => $this->conversationData($updated)]);
    }

    public function destroy(Request $request, int $conversation, AiConversationService $conversations): JsonResponse|RedirectResponse
    {
        $owned = $conversations->findOwned($request->user(), $conversation);
        Gate::authorize('delete', $owned);
        $conversations->deleteOwned($request->user(), $owned);

        if (! $request->expectsJson()) {
            return to_route('user.ai.chatbot');
        }

        return response()->json(status: 204);
    }

    private function conversationData(AiConversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'created_at' => $conversation->created_at?->toIso8601String(),
            'updated_at' => $conversation->updated_at?->toIso8601String(),
        ];
    }
}
