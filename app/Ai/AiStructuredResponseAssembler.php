<?php

namespace App\Ai;

final class AiStructuredResponseAssembler
{
    public const MOVIE_LIMIT = 5;

    public const RECOMMENDATION_LIMIT = 5;

    public const SHOWTIME_LIMIT = 6;

    public const CINEMA_LIMIT = 5;

    public const FOOD_LIMIT = 6;

    public function __construct(
        private readonly AiStructuredCardPresenter $presenter,
        private readonly AiCardFirstTextPresenter $cardFirstText,
    ) {}

    public function assemble(string $text, AiStructuredResultCollector $collector): AiAssistantResponse
    {
        $entries = $collector->entries();
        $prices = [];
        foreach ($entries as $entry) {
            if ($entry['tool'] !== 'get_showtime_prices') {
                continue;
            }
            $payload = $entry['payload']['showtime_prices'] ?? null;
            if (is_array($payload) && is_int($payload['showtime_id'] ?? null)) {
                $prices[$payload['showtime_id']] = $payload;
            }
        }

        $cards = [];
        $seen = [];
        $counts = ['movie' => 0, 'recommendation' => 0, 'showtime' => 0, 'cinema' => 0, 'food' => 0];
        foreach ($entries as $entry) {
            [$items, $kind] = $this->items($entry['tool'], $entry['payload']);
            foreach ($items as $item) {
                if (! is_array($item) || $counts[$kind] >= $this->limit($kind)) {
                    continue;
                }
                $card = match ($kind) {
                    'movie' => $this->presenter->movie($item),
                    'recommendation' => $this->presenter->movie($item, true),
                    'showtime' => $this->presenter->showtime($item, $prices[(int) ($item['id'] ?? 0)] ?? null),
                    'cinema' => $this->presenter->cinema($item),
                    'food' => $this->presenter->food($item),
                };
                if ($card === null) {
                    continue;
                }
                $key = $kind.':'.($card['id'] ?? $card['showtime_id'] ?? $card['code'] ?? '');
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $counts[$kind]++;
                $cards[] = $card;
            }
        }

        $cards = $this->presentationCards($cards);

        return new AiAssistantResponse($this->cardFirstText->present($text, $cards), $cards);
    }

    /** @param list<array<string, mixed>> $recommendations */
    public function assembleRecommendations(string $text, array $recommendations): AiAssistantResponse
    {
        $cards = collect($recommendations)->filter(fn ($item): bool => is_array($item))
            ->take(self::RECOMMENDATION_LIMIT)
            ->map(fn (array $item) => $this->presenter->movie($item, true, is_string($item['reason'] ?? null) ? $item['reason'] : null))
            ->filter()->unique('id')->values()->all();

        return new AiAssistantResponse($this->cardFirstText->present($text, $cards), $cards);
    }

    /** @return array{0: list<mixed>, 1: string} */
    private function items(string $tool, array $payload): array
    {
        return match ($tool) {
            'search_movies' => [$this->list($payload['movies'] ?? null), 'movie'],
            'get_movie_details' => [is_array($payload['movie'] ?? null) ? [$payload['movie']] : [], 'movie'],
            'find_showtimes' => [$this->list($payload['showtimes'] ?? null), 'showtime'],
            'list_cinemas' => [$this->list($payload['cinemas'] ?? null), 'cinema'],
            'list_food_items' => [$this->list($payload['items'] ?? null), 'food'],
            'recommend_movies' => [$this->list($payload['candidates'] ?? null), 'recommendation'],
            default => [[], 'movie'],
        };
    }

    /** @return list<mixed> */
    private function list(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    private function limit(string $kind): int
    {
        return match ($kind) {
            'movie' => self::MOVIE_LIMIT,
            'recommendation' => self::RECOMMENDATION_LIMIT,
            'showtime' => self::SHOWTIME_LIMIT,
            'cinema' => self::CINEMA_LIMIT,
            'food' => self::FOOD_LIMIT,
        };
    }

    /** @param list<array<string, mixed>> $cards
     * @return list<array<string, mixed>>
     */
    private function presentationCards(array $cards): array
    {
        $types = collect($cards)->pluck('type')->filter()->unique();
        if ($types->contains('showtime') && $types->every(fn (string $type): bool => in_array($type, ['movie', 'showtime'], true))) {
            return collect($cards)->reject(fn (array $card): bool => ($card['type'] ?? null) === 'movie')
                ->values()->all();
        }

        return $cards;
    }
}
