<?php

namespace App\Ai;

final readonly class AiConversationContext
{
    /** @param list<array{role: 'user'|'assistant', content: string}> $messages */
    public function __construct(
        public array $messages,
        public int $characterCount,
    ) {}

    public static function empty(): self
    {
        return new self([], 0);
    }
}
