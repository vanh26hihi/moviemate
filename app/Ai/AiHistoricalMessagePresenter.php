<?php

namespace App\Ai;

use App\Models\AiMessage;

final class AiHistoricalMessagePresenter
{
    public function __construct(private readonly AiHistoricalStructuredPayload $payloads) {}

    /** @return array<string, mixed> */
    public function present(AiMessage $message): array
    {
        return [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $message->content,
            'historical_cards' => $message->role === AiMessage::ROLE_ASSISTANT
                ? $this->payloads->cardsForDisplay($message->structured_payload)
                : [],
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
