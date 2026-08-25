<?php

namespace App\Ai;

use JsonSerializable;

final readonly class AiAssistantResponse implements JsonSerializable
{
    public const VERSION = 1;

    /**
     * @param  list<array<string, mixed>>  $cards
     * @param  list<array<string, mixed>>  $suggestedActions
     */
    public function __construct(
        public string $text,
        public array $cards = [],
        public array $suggestedActions = [],
    ) {}

    /** @return array{version: int, text: string, cards: list<array<string, mixed>>, suggested_actions: list<array<string, mixed>>} */
    public function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'text' => $this->text,
            'cards' => $this->cards,
            'suggested_actions' => $this->suggestedActions,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
