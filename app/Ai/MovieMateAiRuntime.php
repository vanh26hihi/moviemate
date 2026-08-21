<?php

namespace App\Ai;

use Laravel\Ai\Contracts\Agent;

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

    public function prompt(Agent $agent, string $prompt): string
    {
        return trim($agent->prompt(
            $prompt,
            provider: $this->provider(),
            model: $this->model(),
            timeout: $this->timeout(),
        )->text);
    }
}
