<?php

namespace App\Services;

use App\Exceptions\PricingConfigurationException;
use App\Exceptions\ShowtimeScheduleException;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Showtime;
use App\Models\ShowtimeTicketPrice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PublicShowtimeCatalog
{
    public const WINDOW_DAYS = 14;

    public const MOVIE_STATUSES = Movie::PUBLIC_STATUSES;

    public function __construct(
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

    public function withCustomerBookingAvailability(Builder $movies, ?Cinema $cinema = null): Builder
    {
        $timezone = $cinema?->timezone ?: config('cinema.timezone', 'Asia/Ho_Chi_Minh');
        $today = CarbonImmutable::today($timezone);
        $bookingThreshold = CarbonImmutable::now($timezone)->subMinutes(ShowtimeLifecycleService::BOOKING_CUTOFF_MINUTES);
        $driver = DB::connection()->getDriverName();
        $dayOfWeekExpression = $driver === 'sqlite'
            ? "((CAST(strftime('%w', showtimes.show_date) AS INTEGER) + 6) % 7) + 1"
            : 'WEEKDAY(showtimes.show_date) + 1';

        return $movies->withExists(['showtimes as customer_booking_available' => fn (Builder $showtimes) => $showtimes
            ->where('showtimes.status', 'active')
            ->when($cinema, fn (Builder $query) => $query->where('showtimes.cinema_id', $cinema->id))
            ->whereBetween('showtimes.show_date', [
                $today->toDateString(),
                $today->addDays(self::WINDOW_DAYS - 1)->toDateString(),
            ])
            ->where(function (Builder $query) use ($bookingThreshold): void {
                $query->whereDate('showtimes.show_date', '>', $bookingThreshold->toDateString())
                    ->orWhere(function (Builder $sameDay) use ($bookingThreshold): void {
                        $sameDay->whereDate('showtimes.show_date', $bookingThreshold->toDateString())
                            ->whereTime('showtimes.show_time', '>', $bookingThreshold->format('H:i:s'));
                    });
            })
            ->whereHas('cinema', fn (Builder $query) => $query->active())
            ->whereHas('room', fn (Builder $query) => $query->where('status', 'active')
                ->whereColumn('rooms.cinema_id', 'showtimes.cinema_id'))
            ->whereHas('roomLayout', fn (Builder $query) => $query->where('status', 'published')
                ->whereColumn('room_layouts.room_id', 'showtimes.room_id')
                ->whereHas('cells', fn (Builder $cells) => $cells->where('cell_type', 'seat')->whereHas('seat')))
            ->whereHas('ticketPrices')
            ->whereRaw('(SELECT COUNT(DISTINCT stp.seat_type_id) FROM showtime_ticket_prices stp WHERE stp.showtime_id = showtimes.id) = (SELECT COUNT(DISTINCT seats.seat_type_id) FROM room_layout_cells rlc JOIN seats ON seats.id = rlc.seat_id WHERE rlc.room_layout_id = showtimes.room_layout_id AND rlc.cell_type = ?)', ['seat'])
            ->whereRaw('(SELECT COUNT(DISTINCT stp.price_book_version_id) FROM showtime_ticket_prices stp WHERE stp.showtime_id = showtimes.id) = 1')
            ->where(function (Builder $query) use ($dayOfWeekExpression): void {
                $query->whereDoesntHave('cinema.operatingHours', fn (Builder $hours) => $hours
                    ->whereRaw("cinema_operating_hours.day_of_week = {$dayOfWeekExpression}"))
                    ->orWhereHas('cinema.operatingHours', fn (Builder $hours) => $hours
                        ->whereRaw("cinema_operating_hours.day_of_week = {$dayOfWeekExpression}")
                        ->where('cinema_operating_hours.is_closed', false)
                        ->whereColumn('cinema_operating_hours.opens_at', '<=', 'showtimes.show_time')
                        ->whereColumn('cinema_operating_hours.latest_show_start_at', '>=', 'showtimes.show_time'));
            })]);
    }

    /** @return array<string, ShowtimeTicketPrice> */
    public function pricesFor(Showtime $showtime): array
    {
        $showtime->loadMissing(['ticketPrices.seatType', 'roomLayout.cells.seat']);
        $structuralSeatTypeIds = $showtime->roomLayout->cells
            ->where('cell_type', 'seat')
            ->map(fn ($cell): int => (int) $cell->seat?->seat_type_id)
            ->filter()
            ->unique()->sort()->values();
        $snapshotSeatTypeIds = $showtime->ticketPrices
            ->pluck('seat_type_id')->map(fn ($id): int => (int) $id)->unique()->sort()->values();
        if ($structuralSeatTypeIds->isEmpty() || $structuralSeatTypeIds->all() !== $snapshotSeatTypeIds->all()
            || $showtime->ticketPrices->pluck('price_book_version_id')->unique()->count() !== 1) {
            throw new PricingConfigurationException('Showtime immutable logical SeatType prices are incomplete.');
        }

        return $showtime->ticketPrices
            ->mapWithKeys(fn ($snapshot): array => [(string) $snapshot->seatType->code => $snapshot])
            ->all();
    }

    public function isSellable(Showtime $showtime): bool
    {
        $showtime->loadMissing(['movie', 'cinema.operatingHours', 'room.cinema.operatingHours', 'roomLayout.cells.seat', 'ticketPrices.seatType']);
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
        $showtime->loadMissing(['movie', 'cinema.operatingHours', 'room.cinema.operatingHours', 'roomLayout.cells.seat', 'ticketPrices.seatType']);
        if (! $showtime->movie?->allowsCustomerBooking()
            || ! $this->operationallySellable($showtime)) {
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
            'roomLayout.cells' => fn ($query) => $query->where('cell_type', 'seat')->with('seat'),
            'ticketPrices.seatType',
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
