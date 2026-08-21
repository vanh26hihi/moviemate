<?php

namespace App\Services;

use App\Models\Cinema;
use App\Models\Movie;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class CustomerShowtimeReadService
{
    public const MAX_RESULTS = 12;

    public function __construct(
        private readonly PublicShowtimeCatalog $catalog,
        private readonly CustomerShowtimeCatalogService $customerCatalog,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function find(array $filters = [], int $limit = self::MAX_RESULTS): Collection
    {
        $limit = max(1, min(self::MAX_RESULTS, $limit));
        $cinema = $this->resolveCinema($filters['cinema_code'] ?? null);
        if (filled($filters['cinema_code'] ?? null) && ! $cinema) {
            return collect();
        }
        $movie = $this->resolveMovie($filters['movie_id'] ?? null, $filters['movie_slug'] ?? null);
        if ((filled($filters['movie_id'] ?? null) || filled($filters['movie_slug'] ?? null)) && ! $movie) {
            return collect();
        }

        if (filled($filters['date'] ?? null)) {
            $date = $this->catalog->date((string) $filters['date'], $cinema);
            $showtimes = $this->customerCatalog->forDate($date, $cinema, $movie);
        } elseif (filled($filters['from'] ?? null) || filled($filters['to'] ?? null)) {
            $from = $this->catalog->date((string) ($filters['from'] ?? $filters['to']), $cinema);
            $to = $this->catalog->date((string) ($filters['to'] ?? $filters['from']), $cinema);
            if ($from > $to) {
                throw ValidationException::withMessages(['from' => 'Ngày bắt đầu phải trước hoặc bằng ngày kết thúc.']);
            }
            $showtimes = $this->customerCatalog->between($from, $to, $cinema, $movie);
        } else {
            $showtimes = $this->customerCatalog->bookingWindow($cinema, $movie);
        }

        return $showtimes->take($limit)->map(fn (array $showtime): array => $this->present($showtime))->values();
    }

    /** @return array<string, mixed> */
    private function present(array $showtime): array
    {
        $bookable = ($showtime['bookable'] ?? false) === true;

        return [
            'id' => (int) $showtime['id'],
            'date' => $showtime['date'],
            'start_time' => $showtime['starts_at']->format('H:i'),
            'movie' => [
                'id' => (int) $showtime['movie']->id,
                'title' => $showtime['movie']->title,
                'slug' => $showtime['movie']->slug,
                'status' => $showtime['movie']->status,
            ],
            'cinema' => [
                'code' => $showtime['cinema']->code,
                'name' => $showtime['cinema']->name,
                'address' => $showtime['cinema']->address,
                'city' => $showtime['cinema']->city,
                'district' => $showtime['cinema']->district,
            ],
            'room_type' => $showtime['room_type'],
            'presentation_format' => $showtime['presentation_format'] ? [
                'code' => $showtime['presentation_format']['code'],
                'name' => $showtime['presentation_format']['name'],
            ] : null,
            'starting_price_vnd' => $showtime['starting_price'],
            'booking_closes_at' => $showtime['booking_closes_at']->toIso8601String(),
            'bookable' => $bookable,
            'booking_url' => $bookable ? $showtime['booking_url'] : null,
        ];
    }

    private function resolveCinema(mixed $code): ?Cinema
    {
        if (! is_string($code) || trim($code) === '') {
            return null;
        }

        return Cinema::query()->active()->where('code', trim($code))->first();
    }

    private function resolveMovie(mixed $id, mixed $slug): ?Movie
    {
        if (! filled($id) && ! filled($slug)) {
            return null;
        }

        return Movie::query()->whereIn('status', Movie::PUBLIC_STATUSES)
            ->when(filled($id), fn ($query) => $query->whereKey((int) $id))
            ->when(! filled($id), fn ($query) => $query->where('slug', trim((string) $slug)))
            ->first();
    }
}
