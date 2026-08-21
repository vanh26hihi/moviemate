<?php

namespace App\Services;

use App\Ai\AiConversationContextBuilder;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AiConversationService
{
    public const DEFAULT_TITLE = 'Cuộc trò chuyện mới';

    public const TITLE_MAX_LENGTH = 120;

    public const MESSAGE_MAX_LENGTH = 1000;

    public const PER_PAGE = 20;

    public const MESSAGES_PER_PAGE = 50;

    public const UI_MESSAGE_LIMIT = 100;

    public function __construct(
        private readonly AiChatbotService $chatbot,
        private readonly AiConversationContextBuilder $contextBuilder,
    ) {}

    public function createForUser(User $user): AiConversation
    {
        return $user->aiConversations()->create([
            'title' => self::DEFAULT_TITLE,
        ]);
    }

    public function listForUser(User $user): LengthAwarePaginator
    {
        return $user->aiConversations()
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);
    }

    public function recentForUser(User $user, int $limit = 8): Collection
    {
        return $user->aiConversations()
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(max(1, min(20, $limit)))
            ->get();
    }

    public function findOwned(User $user, int $conversationId): AiConversation
    {
        return $user->aiConversations()->whereKey($conversationId)->firstOrFail();
    }

    public function renameOwned(User $user, AiConversation $conversation, string $title): AiConversation
    {
        $this->ensureOwned($user, $conversation);
        $conversation->update(['title' => $this->normalizeTitle($title)]);

        return $conversation->refresh();
    }

    public function deleteOwned(User $user, AiConversation $conversation): void
    {
        $this->ensureOwned($user, $conversation);
        $conversation->delete();
    }

    /**
     * @return array{user_message: AiMessage, assistant_message: ?AiMessage, result: array}
     */
    public function continueOwned(User $user, AiConversation $conversation, string $content): array
    {
        $this->ensureOwned($user, $conversation);
        $context = $this->contextBuilder->forConversation($user, $conversation);
        $userMessage = $this->appendUserMessage($user, $conversation, $content);

        $result = $this->chatbot->answer($content, $context, 'authenticated');
        $answer = trim((string) ($result['answer'] ?? ''));
        $assistantCompleted = ($result['assistant_completed'] ?? true) && $answer !== '';
        $result['assistant_completed'] = $assistantCompleted;
        $assistantMessage = $assistantCompleted
            ? $this->appendAssistantMessage($user, $conversation, $answer)
            : null;

        return [
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage,
            'result' => $result,
        ];
    }

    public function appendUserMessage(User $user, AiConversation $conversation, string $content): AiMessage
    {
        $this->ensureOwned($user, $conversation);

        return DB::transaction(function () use ($conversation, $content): AiMessage {
            $activityAt = now();
            $message = $conversation->messages()->create([
                'role' => AiMessage::ROLE_USER,
                'content' => trim($content),
            ]);

            $changes = ['last_message_at' => $activityAt];
            if ($conversation->title === self::DEFAULT_TITLE
                && ! $conversation->messages()->where('role', AiMessage::ROLE_USER)->where('id', '!=', $message->id)->exists()) {
                $changes['title'] = $this->titleFromMessage($content);
            }
            $conversation->update($changes);

            return $message;
        });
    }

    public function appendAssistantMessage(User $user, AiConversation $conversation, string $content): AiMessage
    {
        $this->ensureOwned($user, $conversation);

        return DB::transaction(function () use ($conversation, $content): AiMessage {
            $message = $conversation->messages()->create([
                'role' => AiMessage::ROLE_ASSISTANT,
                'content' => $content,
            ]);
            $conversation->update(['last_message_at' => now()]);

            return $message;
        });
    }

    public function recentOrderedMessages(AiConversation $conversation): Collection
    {
        return $conversation->messages()
            ->orderByDesc('id')
            ->limit(self::UI_MESSAGE_LIMIT)
            ->get()
            ->sortBy('id')
            ->values();
    }

    public function paginateMessages(AiConversation $conversation): LengthAwarePaginator
    {
        return $conversation->messages()
            ->orderBy('id')
            ->paginate(self::MESSAGES_PER_PAGE);
    }

    public function normalizeTitle(string $title): string
    {
        $withoutTags = strip_tags($title);
        $withoutControls = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $withoutTags) ?? '';

        return Str::squish($withoutControls);
    }

    private function titleFromMessage(string $message): string
    {
        $title = $this->normalizeTitle($message);

        return $title === ''
            ? self::DEFAULT_TITLE
            : Str::limit($title, self::TITLE_MAX_LENGTH, '');
    }

    private function ensureOwned(User $user, AiConversation $conversation): void
    {
        if ($conversation->user_id !== $user->id) {
            throw new NotFoundHttpException;
        }
    }
}
