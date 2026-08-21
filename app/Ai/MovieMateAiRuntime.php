<?php

namespace App\Ai;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Responses\StreamableAgentResponse;

final class MovieMateAiRuntime
{
    /** @var list<string> */
    public const SUPPORTED_PROVIDERS = ['openai', 'gemini'];

    public function enabledAndConfigured(): bool
    {
        return (bool) config('moviemate-ai.enabled', false)
            && in_array($this->provider(), self::SUPPORTED_PROVIDERS, true)
            && trim((string) config('ai.providers.'.$this->provider().'.key')) !== '';
    }

    public function provider(): string
    {
        $provider = strtolower((string) config('moviemate-ai.provider', 'openai'));

        return in_array($provider, self::SUPPORTED_PROVIDERS, true) ? $provider : 'openai';
    }

    public function model(): ?string
    {
        $model = trim((string) config('moviemate-ai.model', ''));

        return $model === '' ? null : $model;
    }

    public function timeout(): int
    {
        return max(1, min(20, (int) config('moviemate-ai.timeout', 20)));
    }

    public function prompt(Agent $agent, string $prompt, ?AiConversationContext $context = null): string
    {
        return trim($agent->prompt(
            $this->withContext($prompt, $context ?? AiConversationContext::empty()),
            provider: $this->provider(),
            model: $this->model(),
            timeout: $this->timeout(),
        )->text);
    }

    public function stream(Agent $agent, string $prompt, ?AiConversationContext $context = null): StreamableAgentResponse
    {
        return $agent->stream(
            $this->withContext($prompt, $context ?? AiConversationContext::empty()),
            provider: $this->provider(),
            model: $this->model(),
            timeout: $this->timeout(),
        );
    }

    private function withContext(string $currentMessage, AiConversationContext $context): string
    {
        if ($context->messages === []) {
            return $currentMessage;
        }

        return implode("\n", [
            'UNTRUSTED_CONVERSATION_HISTORY_JSON_START',
            json_encode($context->messages, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'UNTRUSTED_CONVERSATION_HISTORY_JSON_END',
            'CURRENT_USER_MESSAGE_JSON_START',
            json_encode(['content' => $currentMessage], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'CURRENT_USER_MESSAGE_JSON_END',
        ]);
    }
}
