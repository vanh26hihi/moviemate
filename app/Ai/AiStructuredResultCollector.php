<?php

namespace App\Ai;

final class AiStructuredResultCollector
{
    /** @var list<string> */
    public const COLLECTED_TOOLS = [
        'search_movies',
        'get_movie_details',
        'find_showtimes',
        'list_cinemas',
        'list_food_items',
        'get_showtime_prices',
        'recommend_movies',
    ];

    /** @var list<array{tool: string, payload: array<string, mixed>}> */
    private array $entries = [];

    public function reset(): void
    {
        $this->entries = [];
    }

    public function record(string $tool, mixed $result): void
    {
        if (! in_array($tool, self::COLLECTED_TOOLS, true)) {
            return;
        }

        $payload = is_string($result) ? json_decode($result, true) : $result;
        if (! is_array($payload)) {
            return;
        }

        $this->entries[] = ['tool' => $tool, 'payload' => $payload];
    }

    /** @return list<array{tool: string, payload: array<string, mixed>}> */
    public function entries(): array
    {
        return $this->entries;
    }
}
