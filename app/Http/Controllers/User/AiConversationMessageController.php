<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\AiConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiConversationMessageController extends Controller
{
    public function index(Request $request, int $conversation, AiConversationService $conversations): JsonResponse
    {
        $owned = $conversations->findOwned($request->user(), $conversation);
        Gate::authorize('view', $owned);
        $messages = $conversations->paginateMessages($owned);
        $messages->through(fn ($message): array => [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $message->content,
            'created_at' => $message->created_at?->toIso8601String(),
        ]);

        return response()->json($messages);
    }

    public function __invoke(Request $request, int $conversation, AiConversationService $conversations): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['bail', 'required', 'string', 'max:'.AiConversationService::MESSAGE_MAX_LENGTH, 'not_regex:/^\s*$/u'],
            'user_id' => ['prohibited'],
            'role' => ['prohibited'],
            'assistant' => ['prohibited'],
            'system' => ['prohibited'],
            'history' => ['prohibited'],
            'messages' => ['prohibited'],
            'system_prompt' => ['prohibited'],
            'developer_prompt' => ['prohibited'],
            'context' => ['prohibited'],
            'assistant_history' => ['prohibited'],
            'provider' => ['prohibited'],
            'model' => ['prohibited'],
            'temperature' => ['prohibited'],
            'max_tokens' => ['prohibited'],
            'max_steps' => ['prohibited'],
            'steps' => ['prohibited'],
            'timeout' => ['prohibited'],
            'tool_registry' => ['prohibited'],
        ]);

        $owned = $conversations->findOwned($request->user(), $conversation);
        Gate::authorize('continue', $owned);

        try {
            $result = $conversations->continueOwned($request->user(), $owned, $validated['message']);
        } catch (Throwable $exception) {
            Log::warning('AI conversation response failed after storing the user message.', [
                'exception' => $exception::class,
                'conversation_id' => $owned->id,
            ]);

            return response()->json([
                'message' => 'MovieMate AI tạm thời không thể trả lời. Tin nhắn của bạn đã được lưu để thử lại sau.',
            ], 503);
        }

        return response()->json([
            'data' => [
                'conversation_id' => $owned->id,
                'user_message_id' => $result['user_message']->id,
                'assistant_message_id' => $result['assistant_message']?->id,
                'answer' => $result['result']['answer'],
                'source' => $result['result']['source'],
                'assistant_completed' => $result['assistant_message'] !== null,
            ],
        ], $result['assistant_message'] === null ? 503 : 201);
    }
}
