<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class RecommendationReadService
{
    public const MAX_CANDIDATES = 24;

    public function __construct(private readonly CustomerShowtimeCatalogService $showtimes) {}

    /** @return Collection<int, array<string, mixed>> */
    public function candidates(array $preferences = [], int $limit = self::MAX_CANDIDATES): Collection
    {
        $limit = max(1, min(self::MAX_CANDIDATES, $limit));
        $showtimes = $this->showtimes->bookingWindow()
            ->filter(fn (array $showtime): bool => ($showtime['bookable'] ?? false) === true);

        if ($location = $this->text($preferences['location'] ?? null)) {
            $showtimes = $showtimes->filter(function (array $showtime) use ($location): bool {
                $haystack = Str::lower(implode(' ', array_filter([
                    $showtime['cinema']->code,
                    $showtime['cinema']->name,
                    $showtime['cinema']->city,
                    $showtime['cinema']->district,
                    $showtime['cinema']->address,
                ])));

                return Str::contains($haystack, Str::lower($location));
            });
        }

        if ($preferredTime = $this->text($preferences['preferred_time'] ?? null)) {
            $matching = $showtimes->filter(fn (array $showtime): bool => $this->matchesTime($showtime, $preferredTime));
            if ($matching->isNotEmpty()) {
                $showtimes = $matching;
            }
        }

        return $showtimes->groupBy(fn (array $showtime): int => (int) $showtime['movie']->id)
            ->take($limit)
            ->map(function (Collection $movieShowtimes): array {
                $first = $movieShowtimes->first();
                $movie = $first['movie'];
                $safeShowtimes = $movieShowtimes->take(8)->map(fn (array $showtime): array => [
                    'id' => (int) $showtime['id'],
                    'date' => $showtime['date'],
                    'time' => $showtime['starts_at']->format('H:i'),
                    'cinema_code' => $showtime['cinema']->code,
                    'cinema' => $showtime['cinema']->name,
                    'city' => $showtime['cinema']->city,
                    'room_type' => $showtime['room_type'],
                    'starting_price_vnd' => $showtime['starting_price'],
                    'bookable' => true,
                    'booking_url' => $showtime['booking_url'],
                ])->values();

                return [
                    'movie_id' => (int) $movie->id,
                    'title' => $movie->title,
                    'slug' => $movie->slug,
                    'status' => $movie->status,
                    'description' => Str::limit((string) $movie->description, 500, ''),
                    'poster' => $movie->poster_url,
                    'duration' => (int) $movie->duration,
                    'age_rating' => $movie->age_rating,
                    'country' => $movie->country,
                    'genres' => $movie->genres->pluck('name')->values()->all(),
                    'bookable' => $safeShowtimes->contains('bookable', true),
                    'booking_url' => $safeShowtimes->firstWhere('bookable', true)['booking_url'] ?? null,
                    'details_url' => route('user.movies.show', $movie->slug),
                    'showtimes_url' => route('user.movies.show', $movie->slug).'#showtimes',
                    'showtimes' => $safeShowtimes->all(),
                ];
            })->values();
    }

    private function matchesTime(array $showtime, string $preferredTime): bool
    {
        $startsAt = CarbonImmutable::instance($showtime['starts_at']);

        return match (Str::lower($preferredTime)) {
            'tonight' => $startsAt->isToday() && $startsAt->hour >= 18,
            'tomorrow' => $startsAt->isTomorrow(),
            'weekend' => $startsAt->isWeekend(),
            'after_21' => $startsAt->hour >= 21,
            'morning' => $startsAt->hour < 12,
            'afternoon' => $startsAt->hour >= 12 && $startsAt->hour < 18,
            default => true,
        };
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, 191) : null;
    }
}
