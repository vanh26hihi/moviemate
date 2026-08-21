<?php

namespace App\Ai;

use App\Ai\Agents\MovieMateCinemaAssistant;
use App\Ai\Contracts\AiTextStreamer;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\TextDelta;
use RuntimeException;

final class LaravelAiTextStreamer implements AiTextStreamer
{
    public function __construct(
        private readonly MovieMateCinemaAssistant $assistant,
        private readonly MovieMateAiRuntime $runtime,
    ) {}

    public function enabledAndConfigured(): bool
    {
        return $this->runtime->enabledAndConfigured();
    }

    public function deltas(string $message, AiConversationContext $context): iterable
    {
        $completed = false;
        foreach ($this->runtime->stream($this->assistant, $message, $context) as $event) {
            if ($event instanceof TextDelta) {
                yield $event->delta;
            } elseif ($event instanceof StreamEnd) {
                $completed = true;
            }
        }

        if (! $completed) {
            throw new RuntimeException('AI stream ended without completion.');
        }
    }

    public function source(): string
    {
        return $this->runtime->provider();
    }
}
