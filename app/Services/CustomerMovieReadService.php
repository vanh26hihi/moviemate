<?php

namespace App\Services;

use App\Models\Cinema;
use App\Models\Movie;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class CustomerMovieReadService
{
    public const MAX_RESULTS = 12;

    public function __construct(
        private readonly PublicShowtimeCatalog $catalog,
        private readonly CustomerShowtimeCatalogService $customerCatalog,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function search(array $filters = [], int $limit = self::MAX_RESULTS): Collection
    {
        $limit = max(1, min(self::MAX_RESULTS, $limit));
        $query = Movie::query()->with('genres')->whereIn('status', Movie::PUBLIC_STATUSES);

        if ($text = $this->plainText($filters['query'] ?? null, 100)) {
            $query->where(function (Builder $query) use ($text): void {
                $query->where('title', 'like', "%{$text}%")
                    ->orWhere('description', 'like', "%{$text}%");
            });
        }
        if ($genre = $this->plainText($filters['genre'] ?? null, 100)) {
            $query->whereHas('genres', fn (Builder $query) => $query->where('name', 'like', "%{$genre}%"));
        }
        if (in_array($filters['status'] ?? null, Movie::PUBLIC_STATUSES, true)) {
            $query->where('status', $filters['status']);
        }
        if ($rating = $this->plainText($filters['age_rating'] ?? null, 20)) {
            $query->where('age_rating', $rating);
        }

        match ($filters['runtime_band'] ?? null) {
            'short' => $query->where('duration', '<=', 90),
            'standard' => $query->whereBetween('duration', [91, 120]),
            'long' => $query->where('duration', '>', 120),
            default => null,
        };

        if (filled($filters['cinema_code'] ?? null) || filled($filters['date'] ?? null)) {
            $cinema = $this->activeCinema($filters['cinema_code'] ?? null);
            if (filled($filters['cinema_code'] ?? null) && ! $cinema) {
                return collect();
            }

            $showtimes = filled($filters['date'] ?? null)
                ? $this->customerCatalog->forDate(
                    $this->catalog->date((string) $filters['date'], $cinema),
                    $cinema,
                )
                : $this->customerCatalog->bookingWindow($cinema);

            $query->whereIn('id', $showtimes->pluck('movie.id')->map(fn ($id): int => (int) $id)->unique());
        }

        return $query->orderByRaw("case when status = 'now_showing' then 0 else 1 end")
            ->orderBy('title')->orderBy('id')->limit($limit)->get()
            ->map(fn (Movie $movie): array => $this->present($movie));
    }

    /** @return array<string, mixed>|null */
    public function details(?int $movieId = null, ?string $slug = null): ?array
    {
        $movie = Movie::query()->with('genres')->whereIn('status', Movie::PUBLIC_STATUSES)
            ->when($movieId !== null, fn (Builder $query) => $query->whereKey($movieId))
            ->when($movieId === null, fn (Builder $query) => $query->where('slug', trim((string) $slug)))
            ->first();

        if (! $movie) {
            return null;
        }

        $showtimes = $this->customerCatalog->bookingWindow(movie: $movie);
        $firstBookable = $showtimes->firstWhere('bookable', true);

        return [
            ...$this->present($movie),
            'description' => (string) $movie->description,
            'cover_url' => $movie->cover_url,
            'trailer_url' => $movie->trailer_url,
            'booking_available' => $firstBookable !== null,
            'booking_url' => $firstBookable['booking_url'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    public function present(Movie $movie): array
    {
        return [
            'id' => (int) $movie->id,
            'title' => $movie->title,
            'slug' => $movie->slug,
            'status' => $movie->status,
            'allows_customer_booking' => $movie->allowsCustomerBooking(),
            'genres' => $movie->genres->pluck('name')->values()->all(),
            'duration_minutes' => (int) $movie->duration,
            'age_rating' => $movie->age_rating,
            'country' => $movie->country,
            'release_date' => $movie->release_date?->toDateString(),
            'poster_url' => $movie->poster_url,
            'details_url' => route('user.movies.show', $movie->slug),
            'showtimes_url' => route('user.movies.show', $movie->slug).'#showtimes',
        ];
    }

    private function activeCinema(mixed $code): ?Cinema
    {
        $code = $this->plainText($code, 50);

        return $code === null ? null : Cinema::query()->active()->where('code', $code)->first();
    }

    private function plainText(mixed $value, int $max): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return mb_substr(str_replace(['%', '_'], '', trim($value)), 0, $max);
    }
}
