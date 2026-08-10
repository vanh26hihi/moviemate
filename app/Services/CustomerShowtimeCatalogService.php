<?php

namespace App\Services;

use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Showtime;
use Illuminate\Support\Collection;

/**
 * The single customer-facing presenter for schedule discovery. It deliberately
 * exposes a room type label, never a physical room identifier.
 */
final class CustomerShowtimeCatalogService
{
    public function __construct(
        private readonly PublicShowtimeCatalog $catalog,
        private readonly ShowtimeLifecycleService $lifecycle,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function forDate(string $date, ?Cinema $cinema = null, ?Movie $movie = null): Collection
    {
        return $this->present($this->catalog->forDate($date, $cinema, $movie));
    }

    /** @return Collection<int, array<string, mixed>> */
    public function between(string $from, string $to, ?Cinema $cinema = null, ?Movie $movie = null): Collection
    {
        return $this->present($this->catalog->between($from, $to, $cinema, $movie));
    }

    /** @param Collection<int, Showtime> $showtimes
     * @return Collection<int, array<string, mixed>>
     */
    public function present(Collection $showtimes): Collection
    {
        return $showtimes->map(function (Showtime $showtime): array {
            $lifecycle = $this->lifecycle->snapshot($showtime);

            return [
                'id' => (int) $showtime->id,
                'date' => $showtime->show_date->toDateString(),
                'cinema' => $showtime->cinema,
                'movie' => $showtime->movie,
                'poster' => $showtime->movie->poster_url,
                'genres' => $showtime->movie->genres,
                'duration' => $showtime->movie->duration,
                'age_rating' => $showtime->movie->age_rating,
                'room_type' => $showtime->room->room_type_label,
                'starts_at' => $lifecycle['starts_at'],
                'customer_visible_ends_at' => $lifecycle['ends_at'],
                'booking_closes_at' => $lifecycle['booking_closes_at'],
                'server_now' => $lifecycle['now'],
                'booking_url' => route('user.bookings.selectSeat', [
                    'showtime' => $showtime->id,
                    'cinema' => $showtime->cinema->code,
                ]),
                'bookable' => $this->lifecycle->isCustomerBookingOpen($showtime, $lifecycle['now']),
                'starting_price' => $showtime->getAttribute('starting_price') === null
                    ? null
                    : (int) $showtime->getAttribute('starting_price'),
            ];
        })->values();
    }
}
