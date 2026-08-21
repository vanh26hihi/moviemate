<?php

namespace App\Ai;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AiConversationContextBuilder
{
    public function forConversation(User $user, AiConversation $conversation): AiConversationContext
    {
        return $this->forConversationBefore($user, $conversation, null);
    }

    public function forConversationBefore(User $user, AiConversation $conversation, ?int $beforeMessageId): AiConversationContext
    {
        if ($conversation->user_id !== $user->id) {
            throw new NotFoundHttpException;
        }

        $query = $conversation->messages()
            ->select(['id', 'role', 'content'])
            ->when($beforeMessageId !== null, fn ($query) => $query->where('id', '<', $beforeMessageId));

        $messages = $query->orderByDesc('id')
            ->limit($this->messageLimit())
            ->get()
            ->reverse()
            ->values()
            ->map(fn (AiMessage $message): array => [
                'role' => $message->role,
                'content' => $message->content,
            ]);

        return $this->bound($messages);
    }

    public function forGuest(array $history): AiConversationContext
    {
        $messages = collect($history)->filter(fn ($turn): bool => is_array($turn))
            ->flatMap(function (array $turn): array {
                $messages = [];
                if (isset($turn['message']) && is_string($turn['message'])) {
                    $messages[] = ['role' => AiMessage::ROLE_USER, 'content' => $turn['message']];
                }
                if (isset($turn['response']) && is_string($turn['response'])) {
                    $messages[] = ['role' => AiMessage::ROLE_ASSISTANT, 'content' => $turn['response']];
                }

                return $messages;
            })->take(-$this->messageLimit())->values();

        return $this->bound($messages);
    }

    /** @param Collection<int, array{role: mixed, content: mixed}> $messages */
    private function bound(Collection $messages): AiConversationContext
    {
        $allowedRoles = [AiMessage::ROLE_USER, AiMessage::ROLE_ASSISTANT];
        $normalized = $messages->filter(fn (array $message): bool => in_array($message['role'], $allowedRoles, true)
                && is_string($message['content'])
                && trim($message['content']) !== '')
            ->map(fn (array $message): array => [
                'role' => $message['role'],
                'content' => trim($message['content']),
            ])->values();

        $budget = $this->characterLimit();
        while ($normalized->isNotEmpty() && $normalized->sum(fn (array $message): int => mb_strlen($message['content'])) > $budget) {
            $normalized->shift();
        }

        $messages = $normalized->values()->all();

        return new AiConversationContext(
            $messages,
            array_sum(array_map(fn (array $message): int => mb_strlen($message['content']), $messages)),
        );
    }

    private function messageLimit(): int
    {
        return max(1, min(16, (int) config('moviemate-ai.context_messages', 12)));
    }

    private function characterLimit(): int
    {
        return max(1000, min(12_000, (int) config('moviemate-ai.context_characters', 6000)));
    }
}
