<?php

namespace App\Ai;

use App\Models\Movie;
use Illuminate\Support\Str;

final class AiHistoricalStructuredPayload
{
    public const MAX_BYTES = 65_536;

    /** @var list<string> */
    private const CARD_TYPES = ['movie', 'showtime', 'cinema', 'food'];

    public function __construct(private readonly MovieGenreLocalizer $genres) {}

    /** @return array{version: int, cards: list<array<string, mixed>>}|null */
    public function forStorage(mixed $structuredResponse): ?array
    {
        return $this->sanitize($structuredResponse);
    }

    /** @return list<array<string, mixed>> */
    public function cardsForDisplay(mixed $storedPayload): array
    {
        return $this->sanitize($storedPayload)['cards'] ?? [];
    }

    /** @return array{version: int, cards: list<array<string, mixed>>}|null */
    private function sanitize(mixed $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        $cards = is_array($payload['cards'] ?? null) ? $payload['cards'] : [];
        $safe = [];
        foreach ($cards as $card) {
            if (! is_array($card) || ! in_array($card['type'] ?? null, self::CARD_TYPES, true)) {
                continue;
            }

            $presented = match ($card['type']) {
                'movie' => $this->movie($card),
                'showtime' => $this->showtime($card),
                'cinema' => $this->cinema($card),
                'food' => $this->food($card),
            };
            if ($presented === null) {
                continue;
            }

            $candidate = ['version' => AiAssistantResponse::VERSION, 'cards' => [...$safe, $presented]];
            if (strlen(json_encode($candidate, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) > self::MAX_BYTES) {
                break;
            }
            $safe[] = $presented;
        }

        return $safe === [] ? null : ['version' => AiAssistantResponse::VERSION, 'cards' => $safe];
    }

    /** @return array<string, mixed>|null */
    private function movie(array $card): ?array
    {
        $id = $this->positiveInt($card['id'] ?? null);
        $title = $this->text($card['title'] ?? null, 191);
        $status = $this->text($card['stored_status'] ?? null, 40);
        if ($id === null || $title === null || $status === null) {
            return null;
        }

        return $this->withoutNulls([
            'type' => 'movie',
            'context' => ($card['context'] ?? null) === 'recommendation' ? 'recommendation' : 'discovery',
            'id' => $id,
            'title' => $title,
            'stored_status' => $status,
            'poster_url' => Movie::imageUrl($this->text($card['poster_url'] ?? null, 2048)),
            'genres' => $this->genres->localizeList($this->textList($card['genres'] ?? [], 8, 100)),
            'duration_minutes' => $this->positiveInt($card['duration_minutes'] ?? null),
            'age_rating' => $this->text($card['age_rating'] ?? null, 20),
            'country' => $this->text($card['country'] ?? null, 100),
            'release_date' => $this->date($card['release_date'] ?? null),
            'description' => $this->text($card['description'] ?? null, 500),
            'reason' => $this->text($card['reason'] ?? null, 300),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function showtime(array $card): ?array
    {
        $id = $this->positiveInt($card['showtime_id'] ?? null);
        $movieId = $this->positiveInt($card['movie_id'] ?? null);
        $title = $this->text($card['movie_title'] ?? null, 191);
        $date = $this->date($card['date'] ?? null);
        $time = $this->time($card['time'] ?? null);
        if ($id === null || $movieId === null || $title === null || $date === null || $time === null) {
            return null;
        }

        return $this->withoutNulls([
            'type' => 'showtime',
            'showtime_id' => $id,
            'movie_id' => $movieId,
            'movie_title' => $title,
            'movie_stored_status' => $this->text($card['movie_stored_status'] ?? null, 40),
            'date' => $date,
            'time' => $time,
            'cinema' => $this->cinemaSummary($card['cinema'] ?? null),
            'room_type' => $this->text($card['room_type'] ?? null, 100),
            'presentation_format' => $this->format($card['presentation_format'] ?? null),
            'starting_price_vnd' => $this->nonNegativeInt($card['starting_price_vnd'] ?? null),
            'currency' => 'VND',
            'booking_closes_at' => $this->text($card['booking_closes_at'] ?? null, 40),
            'prices' => $this->prices($card['prices'] ?? null),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function cinema(array $card): ?array
    {
        $code = $this->text($card['code'] ?? null, 50);
        $name = $this->text($card['name'] ?? null, 191);
        if ($code === null || $name === null) {
            return null;
        }

        return $this->withoutNulls([
            'type' => 'cinema',
            'code' => $code,
            'name' => $name,
            'address' => $this->text($card['address'] ?? null, 300),
            'city' => $this->text($card['city'] ?? null, 120),
            'district' => $this->text($card['district'] ?? null, 120),
            'phone' => $this->text($card['phone'] ?? null, 30),
            'description' => $this->text($card['description'] ?? null, 500),
            'operating_hours' => $this->operatingHours($card['operating_hours'] ?? null),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function food(array $card): ?array
    {
        $id = $this->positiveInt($card['id'] ?? null);
        $name = $this->text($card['name'] ?? null, 191);
        $price = $this->nonNegativeInt($card['price_vnd'] ?? null);
        if ($id === null || $name === null || $price === null) {
            return null;
        }

        return $this->withoutNulls([
            'type' => 'food',
            'id' => $id,
            'name' => $name,
            'description' => $this->text($card['description'] ?? null, 500),
            'price_vnd' => $price,
            'currency' => 'VND',
            'scope' => 'public_catalog',
            'branch_availability_confirmed' => false,
        ]);
    }

    /** @return array<string, string>|null */
    private function cinemaSummary(mixed $cinema): ?array
    {
        if (! is_array($cinema)) {
            return null;
        }

        $safe = $this->withoutNulls([
            'code' => $this->text($cinema['code'] ?? null, 50),
            'name' => $this->text($cinema['name'] ?? null, 191),
            'address' => $this->text($cinema['address'] ?? null, 300),
            'city' => $this->text($cinema['city'] ?? null, 120),
            'district' => $this->text($cinema['district'] ?? null, 120),
        ]);

        return $safe === [] ? null : $safe;
    }

    /** @return array{code: string, name: string}|null */
    private function format(mixed $format): ?array
    {
        if (! is_array($format)) {
            return null;
        }
        $code = $this->text($format['code'] ?? null, 50);
        $name = $this->text($format['name'] ?? null, 100);

        return $code !== null && $name !== null ? ['code' => $code, 'name' => $name] : null;
    }

    /** @return list<array<string, mixed>> */
    private function prices(mixed $prices): array
    {
        if (! is_array($prices)) {
            return [];
        }

        return collect($prices)->filter(fn ($price): bool => is_array($price))->take(8)
            ->map(function (array $price): ?array {
                $name = $this->text($price['seat_type_name'] ?? null, 100);
                $amount = $this->nonNegativeInt($price['amount_vnd'] ?? null);

                return $name !== null && $amount !== null ? [
                    'seat_type_name' => $name,
                    'logical_unit_seat_count' => max(1, min(2, (int) ($price['logical_unit_seat_count'] ?? 1))),
                    'amount_vnd' => $amount,
                    'currency' => 'VND',
                ] : null;
            })->filter()->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function operatingHours(mixed $hours): array
    {
        if (! is_array($hours)) {
            return [];
        }

        return collect($hours)->filter(fn ($item): bool => is_array($item))->take(7)
            ->map(fn (array $item): array => [
                'day_of_week' => max(0, min(6, (int) ($item['day_of_week'] ?? 0))),
                'closed' => ($item['closed'] ?? false) === true,
                'opens_at' => $this->time($item['opens_at'] ?? null),
                'latest_show_start_at' => $this->time($item['latest_show_start_at'] ?? null),
            ])->values()->all();
    }

    private function text(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/isu', ' ', $value) ?? '';
        $value = Str::squish(strip_tags(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value) ?? ''));

        return $value === '' ? null : Str::limit($value, $max, '');
    }

    /** @return list<string> */
    private function textList(mixed $values, int $limit, int $max): array
    {
        return is_array($values) ? collect($values)->map(fn ($value): ?string => $this->text($value, $max))
            ->filter()->unique()->take($limit)->values()->all() : [];
    }

    private function positiveInt(mixed $value): ?int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT);

        return is_int($value) && $value > 0 ? $value : null;
    }

    private function nonNegativeInt(mixed $value): ?int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT);

        return is_int($value) && $value >= 0 ? $value : null;
    }

    private function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) ? $value : null;
    }

    private function time(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $value) ? $value : null;
    }

    /** @return array<string, mixed> */
    private function withoutNulls(array $values): array
    {
        return array_filter($values, fn ($value): bool => $value !== null);
    }
}
