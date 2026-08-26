<?php

namespace App\Ai\Contracts;

use App\Ai\AiConversationContext;

interface AiTextStreamer
{
    public function enabledAndConfigured(): bool;

    /** @return iterable<string> */
    public function deltas(string $message, AiConversationContext $context): iterable;

    public function source(): string;
}
