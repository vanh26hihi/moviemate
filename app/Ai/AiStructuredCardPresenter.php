<?php

namespace App\Ai;

use App\Models\Movie;
use Illuminate\Support\Str;

final class AiStructuredCardPresenter
{
    public function __construct(private readonly MovieGenreLocalizer $genres) {}

    /** @return array<string, mixed>|null */
    public function movie(array $movie, bool $recommendation = false, ?string $reason = null): ?array
    {
        $id = $this->positiveInt($movie['id'] ?? $movie['movie_id'] ?? null);
        $slug = $this->plain($movie['slug'] ?? null, 191);
        $title = $this->plain($movie['title'] ?? null, 191);
        $status = $this->plain($movie['status'] ?? null, 40);
        if ($id === null || $slug === null || $title === null || ! in_array($status, Movie::PUBLIC_STATUSES, true)) {
            return null;
        }

        $detailsUrl = route('user.movies.show', $slug);
        $showtimesUrl = $detailsUrl.'#showtimes';
        $actions = [
            $this->action('movie_details', 'Chi tiết', $detailsUrl),
            $this->action('view_showtimes', 'Xem lịch chiếu', $showtimesUrl),
        ];

        $card = [
            'type' => 'movie',
            'context' => $recommendation ? 'recommendation' : 'discovery',
            'id' => $id,
            'title' => $title,
            'slug' => $slug,
            'poster_url' => $this->publicUrl($movie['poster_url'] ?? $movie['poster'] ?? null, 2048),
            'stored_status' => $status,
            'genres' => $this->genres->localizeList($this->plainList($movie['genres'] ?? [], 8, 100)),
            'duration_minutes' => $this->positiveInt($movie['duration_minutes'] ?? $movie['duration'] ?? null),
            'age_rating' => $this->plain($movie['age_rating'] ?? null, 20),
            'country' => $this->plain($movie['country'] ?? null, 100),
            'release_date' => $this->date($movie['release_date'] ?? null),
            'description' => $this->plain($movie['description'] ?? null, 500),
            'details_url' => $detailsUrl,
            'showtimes_url' => $showtimesUrl,
        ];

        if ($recommendation) {
            $card['reason'] = $this->plain($reason, 300);
            $showtime = collect(is_array($movie['showtimes'] ?? null) ? $movie['showtimes'] : [])
                ->first(fn ($item): bool => is_array($item)
                    && ($item['bookable'] ?? false) === true
                    && $this->positiveInt($item['id'] ?? null) !== null
                    && filled($item['booking_url'] ?? null));
            if ($status === Movie::STATUS_NOW_SHOWING
                && ($movie['bookable'] ?? false) === true
                && is_array($showtime)) {
                $showtimeId = $this->positiveInt($showtime['id']) ?? 0;
                $actions[] = $this->action(
                    'book_showtime',
                    'Đặt vé',
                    route('user.bookings.selectSeat', $showtimeId),
                    ['showtime_id' => $showtimeId],
                );
            }
        }

        $card['actions'] = $actions;

        return $this->withoutNulls($card);
    }

    /** @return array<string, mixed>|null */
    public function showtime(array $showtime, ?array $prices = null): ?array
    {
        $id = $this->positiveInt($showtime['id'] ?? null);
        $movie = is_array($showtime['movie'] ?? null) ? $showtime['movie'] : [];
        $cinema = is_array($showtime['cinema'] ?? null) ? $showtime['cinema'] : [];
        $movieId = $this->positiveInt($movie['id'] ?? null);
        $movieTitle = $this->plain($movie['title'] ?? null, 191);
        $movieSlug = $this->plain($movie['slug'] ?? null, 191);
        $date = $this->date($showtime['date'] ?? null);
        $time = $this->time($showtime['start_time'] ?? null);
        if ($id === null || $movieId === null || $movieTitle === null || $movieSlug === null || $date === null || $time === null) {
            return null;
        }

        $showtimesUrl = route('user.movies.show', $movieSlug).'#showtimes';
        $actions = [$this->action('view_showtimes', 'Xem lịch chiếu', $showtimesUrl)];
        $bookable = ($movie['status'] ?? null) === Movie::STATUS_NOW_SHOWING
            && ($showtime['bookable'] ?? false) === true
            && filled($showtime['booking_url'] ?? null);
        $bookingUrl = null;
        if ($bookable) {
            $bookingUrl = route('user.bookings.selectSeat', $id);
            $actions[] = $this->action('book_showtime', 'Đặt vé', $bookingUrl, ['showtime_id' => $id]);
        }

        $card = [
            'type' => 'showtime',
            'showtime_id' => $id,
            'movie_id' => $movieId,
            'movie_title' => $movieTitle,
            'movie_stored_status' => $this->plain($movie['status'] ?? null, 40),
            'starts_at' => $date.'T'.$time,
            'date' => $date,
            'time' => $time,
            'cinema' => $this->withoutNulls([
                'code' => $this->plain($cinema['code'] ?? null, 50),
                'name' => $this->plain($cinema['name'] ?? null, 191),
                'address' => $this->plain($cinema['address'] ?? null, 300),
                'city' => $this->plain($cinema['city'] ?? null, 120),
                'district' => $this->plain($cinema['district'] ?? null, 120),
            ]),
            'room_type' => $this->plain($showtime['room_type'] ?? null, 100),
            'presentation_format' => $this->presentationFormat($showtime['presentation_format'] ?? null),
            'starting_price_vnd' => $this->nonNegativeInt($showtime['starting_price_vnd'] ?? null),
            'currency' => 'VND',
            'booking_closes_at' => $this->plain($showtime['booking_closes_at'] ?? null, 40),
            'bookable' => $bookable,
            'showtimes_url' => $showtimesUrl,
            'booking_url' => $bookingUrl,
            'prices' => $this->prices($prices),
            'actions' => $actions,
        ];

        return $this->withoutNulls($card);
    }

    /** @return array<string, mixed>|null */
    public function cinema(array $cinema): ?array
    {
        $code = $this->plain($cinema['code'] ?? null, 50);
        $name = $this->plain($cinema['name'] ?? null, 191);
        if ($code === null || $name === null) {
            return null;
        }

        return $this->withoutNulls([
            'type' => 'cinema',
            'code' => $code,
            'name' => $name,
            'address' => $this->plain($cinema['address'] ?? null, 300),
            'city' => $this->plain($cinema['city'] ?? null, 120),
            'district' => $this->plain($cinema['district'] ?? null, 120),
            'phone' => $this->plain($cinema['phone'] ?? null, 30),
            'description' => $this->plain($cinema['description'] ?? null, 500),
            'image_url' => $this->publicUrl($cinema['image_url'] ?? null, 2048),
            'latitude' => $this->coordinate($cinema['latitude'] ?? null),
            'longitude' => $this->coordinate($cinema['longitude'] ?? null),
            'operating_hours' => $this->operatingHours($cinema['operating_hours'] ?? []),
            'details_url' => route('cinemas.show', $code),
            'actions' => [],
        ]);
    }

    /** @return array<string, mixed>|null */
    public function food(array $food): ?array
    {
        $id = $this->positiveInt($food['id'] ?? null);
        $name = $this->plain($food['name'] ?? null, 191);
        $price = $this->nonNegativeInt($food['price_vnd'] ?? null);
        if ($id === null || $name === null || $price === null) {
            return null;
        }

        return $this->withoutNulls([
            'type' => 'food',
            'id' => $id,
            'name' => $name,
            'description' => $this->plain($food['description'] ?? null, 500),
            'image_url' => $this->publicUrl($food['image_url'] ?? null, 2048),
            'price_vnd' => $price,
            'currency' => 'VND',
            'scope' => 'public_catalog',
            'branch_availability_confirmed' => false,
            'actions' => [],
        ]);
    }

    /** @return array<string, mixed> */
    private function action(string $type, string $label, string $url, array $parameters = []): array
    {
        return array_filter([
            'type' => $type,
            'label' => $label,
            'url' => $url,
            'parameters' => $parameters ?: null,
        ], fn ($value): bool => $value !== null);
    }

    /** @return list<array<string, mixed>>|null */
    private function prices(?array $payload): ?array
    {
        if (! is_array($payload['prices'] ?? null)) {
            return null;
        }

        $prices = collect($payload['prices'])->filter(fn ($price): bool => is_array($price))
            ->take(8)->map(function (array $price): ?array {
                $code = $this->plain($price['seat_type_code'] ?? null, 50);
                $name = $this->plain($price['seat_type_name'] ?? null, 100);
                $amount = $this->nonNegativeInt($price['amount_vnd'] ?? null);
                if ($code === null || $name === null || $amount === null) {
                    return null;
                }

                return [
                    'seat_type_code' => $code,
                    'seat_type_name' => $name,
                    'logical_unit_seat_count' => max(1, min(2, (int) ($price['logical_unit_seat_count'] ?? 1))),
                    'amount_vnd' => $amount,
                    'currency' => 'VND',
                ];
            })->filter()->values()->all();

        return $prices === [] ? null : $prices;
    }

    /** @return array{code: string, name: string}|null */
    private function presentationFormat(mixed $format): ?array
    {
        if (! is_array($format)) {
            return null;
        }
        $code = $this->plain($format['code'] ?? null, 50);
        $name = $this->plain($format['name'] ?? null, 100);

        return $code !== null && $name !== null ? ['code' => $code, 'name' => $name] : null;
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

    private function plain(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/isu', ' ', $value) ?? '';
        $value = Str::squish(strip_tags(preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value) ?? ''));

        return $value === '' ? null : Str::limit($value, $max, '');
    }

    /** @return list<string> */
    private function plainList(mixed $values, int $limit, int $max): array
    {
        if (! is_array($values)) {
            return [];
        }

        return collect($values)->map(fn ($value): ?string => $this->plain($value, $max))
            ->filter()->unique()->take($limit)->values()->all();
    }

    private function publicUrl(mixed $value, int $max): ?string
    {
        return is_string($value) && trim($value) !== '' ? Str::limit(trim($value), $max, '') : null;
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

    private function coordinate(mixed $value): ?string
    {
        return is_numeric($value) ? (string) $value : null;
    }

    /** @return array<string, mixed> */
    private function withoutNulls(array $values): array
    {
        return array_filter($values, fn ($value): bool => $value !== null);
    }
}
