<?php

namespace App\Services;

use App\Domain\Pricing\TicketPrice;
use App\Exceptions\PricingConfigurationException;
use App\Exceptions\ShowtimeScheduleException;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Showtime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class PublicShowtimeCatalog
{
    public const WINDOW_DAYS = 14;

    public const MOVIE_STATUSES = Movie::PUBLIC_STATUSES;

    public function __construct(
        private readonly TicketPricingService $pricing,
        private readonly ShowtimeScheduleService $schedule,
        private readonly ShowtimeLifecycleService $lifecycle,
    ) {}

    public function date(?string $requested, ?Cinema $cinema = null): string
    {
        $timezone = $cinema?->timezone ?: config('cinema.timezone', 'Asia/Ho_Chi_Minh');
        $today = CarbonImmutable::today($timezone);
        if ($requested === null || $requested === '') {
            return $today->toDateString();
        }
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $requested, $timezone);
        } catch (\Throwable) {
            $date = null;
        }
        if (! $date || $date->format('Y-m-d') !== $requested
            || $date->lt($today) || $date->gt($today->addDays(self::WINDOW_DAYS - 1))) {
            throw ValidationException::withMessages(['date' => 'Ngày xem lịch phải nằm trong 14 ngày tới.']);
        }

        return $date->toDateString();
    }

    /** @return Collection<int, array{date:string,label:string,day:string}> */
    public function dates(?Cinema $cinema = null): Collection
    {
        $timezone = $cinema?->timezone ?: config('cinema.timezone', 'Asia/Ho_Chi_Minh');
        $today = CarbonImmutable::today($timezone);

        return collect(range(0, self::WINDOW_DAYS - 1))->map(function (int $offset) use ($today): array {
            $date = $today->addDays($offset);

            return [
                'date' => $date->toDateString(),
                'day' => $date->format('d/m'),
                'label' => $offset === 0 ? 'Hôm nay' : $date->locale('vi')->translatedFormat('D'),
            ];
        });
    }

    /** @return Collection<int, Showtime> */
    public function forDate(string $date, ?Cinema $cinema = null, ?Movie $movie = null): Collection
    {
        return $this->sellable($this->structuralQuery($cinema, $movie)
            ->whereDate('show_date', $date)->orderBy('show_time')->orderBy('id')->get());
    }

    /** @return Collection<int, Showtime> */
    public function between(string $from, string $to, ?Cinema $cinema = null, ?Movie $movie = null): Collection
    {
        return $this->sellable($this->structuralQuery($cinema, $movie)
            ->whereBetween('show_date', [$from, $to])->orderBy('show_date')->orderBy('show_time')->orderBy('id')->get());
    }

    /** @param list<int> $cinemaIds
     * @return Collection<int, Showtime>
     */
    public function betweenForCinemas(array $cinemaIds, string $from, string $to): Collection
    {
        if ($cinemaIds === []) {
            return collect();
        }

        return $this->sellable($this->structuralQuery(null, null)->whereIn('cinema_id', $cinemaIds)
            ->whereBetween('show_date', [$from, $to])->orderBy('show_date')->orderBy('show_time')->orderBy('id')->get());
    }

    /** @return array<string, TicketPrice> */
    public function pricesFor(Showtime $showtime): array
    {
        return $this->pricing->calculateSeatTypes($showtime, allowLegacySnapshot: false);
    }

    public function isSellable(Showtime $showtime): bool
    {
        $showtime->loadMissing(['movie', 'cinema.operatingHours', 'room.cinema.operatingHours', 'roomLayout.cells']);
        if (! $this->operationallySellable($showtime)) {
            return false;
        }
        try {
            if (! $this->schedule->windowFor($showtime)->start->isFuture()) {
                return false;
            }
            $this->schedule->assertWithinOperatingHours($showtime->room, $this->schedule->windowFor($showtime));
            $this->pricesFor($showtime);
        } catch (PricingConfigurationException|ShowtimeScheduleException) {
            return false;
        }

        return true;
    }

    public function isCustomerSellable(Showtime $showtime): bool
    {
        $showtime->loadMissing(['movie', 'cinema.operatingHours', 'room.cinema.operatingHours', 'roomLayout.cells']);
        if (! $this->operationallySellable($showtime)) {
            return false;
        }

        try {
            if (! $this->lifecycle->isCustomerBookingOpen($showtime)) {
                return false;
            }
            $this->schedule->assertWithinOperatingHours($showtime->room, $this->schedule->windowFor($showtime));
            $this->pricesFor($showtime);
        } catch (PricingConfigurationException|ShowtimeScheduleException) {
            return false;
        }

        return true;
    }

    private function structuralQuery(?Cinema $cinema, ?Movie $movie): Builder
    {
        return Showtime::query()->with([
            'movie.genres', 'cinema.operatingHours', 'presentationFormat', 'room.roomType',
            'roomLayout.cells' => fn ($query) => $query->where('cell_type', 'seat'),
        ])->where('status', 'active')
            ->when($cinema, fn (Builder $query) => $query->where('cinema_id', $cinema->id))
            ->when($movie, fn (Builder $query) => $query->where('movie_id', $movie->id))
            ->whereHas('cinema', fn (Builder $query) => $query->active())
            ->whereHas('room', fn (Builder $query) => $query->where('status', 'active'))
            ->whereHas('movie', fn (Builder $query) => $query->whereIn('status', self::MOVIE_STATUSES))
            ->whereHas('roomLayout', fn (Builder $query) => $query->where('status', 'published')->whereHas('cells', fn (Builder $cells) => $cells->where('cell_type', 'seat')));
    }

    /** @param Collection<int, Showtime> $showtimes
     * @return Collection<int, Showtime>
     */
    private function sellable(Collection $showtimes): Collection
    {
        $showtimes = $showtimes->filter(fn (Showtime $showtime): bool => $this->operationallySellable($showtime)
            && $this->lifecycle->isCustomerBookingOpen($showtime))->values();
        $this->pricing->warmForShowtimes($showtimes);

        return $showtimes->filter(function (Showtime $showtime): bool {
            try {
                $this->schedule->assertWithinOperatingHours($showtime->room, $this->schedule->windowFor($showtime));
                $prices = $this->pricesFor($showtime);
            } catch (PricingConfigurationException|ShowtimeScheduleException) {
                return false;
            }
            $amounts = collect($prices)->pluck('finalAmount');
            $showtime->setAttribute('public_prices', $prices);
            $showtime->setAttribute('starting_price', $amounts->min());
            $showtime->setAttribute('maximum_price', $amounts->max());

            return true;
        })->values();
    }

    private function operationallySellable(Showtime $showtime): bool
    {
        if ($showtime->status !== 'active'
            || ! in_array($showtime->movie?->status, self::MOVIE_STATUSES, true)
            || $showtime->cinema?->status !== 'active' || $showtime->cinema?->archived_at !== null
            || $showtime->room?->status !== 'active'
            || (int) $showtime->room?->cinema_id !== (int) $showtime->cinema_id
            || ! $showtime->roomLayout || $showtime->roomLayout->status !== 'published'
            || (int) $showtime->roomLayout->room_id !== (int) $showtime->room_id
            || $showtime->roomLayout->cells->isEmpty()) {
            return false;
        }

        return true;
    }
}
