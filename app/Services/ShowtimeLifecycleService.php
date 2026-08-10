<?php

namespace App\Services;

use App\Domain\Showtimes\ShowtimeWindow;
use App\Models\Cinema;
use App\Models\Showtime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class ShowtimeLifecycleService
{
    public const UPCOMING = 'upcoming';

    public const PLAYING = 'playing';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    public const BOOKING_CUTOFF_MINUTES = 15;

    /** @var array<string, string> */
    public const LABELS = [
        self::UPCOMING => 'Sắp chiếu',
        self::PLAYING => 'Đang chiếu',
        self::COMPLETED => 'Đã chiếu xong',
        self::CANCELLED => 'Đã hủy',
    ];

    public function __construct(private readonly ShowtimeScheduleService $schedule) {}

    /**
     * @return array{
     *   state:string,
     *   label:string,
     *   now:CarbonImmutable,
     *   starts_at:CarbonImmutable,
     *   ends_at:CarbonImmutable,
     *   cleaning_starts_at:CarbonImmutable,
     *   room_ready_at:CarbonImmutable,
     *   booking_closes_at:CarbonImmutable,
     *   window:ShowtimeWindow
     * }
     */
    public function snapshot(Showtime $showtime, ?CarbonInterface $now = null): array
    {
        if ($showtime->relationLoaded('room')
            && $showtime->relationLoaded('cinema')
            && $showtime->room
            && $showtime->cinema
            && (int) $showtime->room->cinema_id === (int) $showtime->cinema_id
            && ! $showtime->room->relationLoaded('cinema')) {
            $showtime->room->setRelation('cinema', $showtime->cinema);
        }

        $window = $this->schedule->windowFor($showtime);
        $current = $this->currentTime($window->start, $now);
        $state = match (true) {
            $showtime->status === self::CANCELLED => self::CANCELLED,
            $current->lt($window->start) => self::UPCOMING,
            $current->lt($window->movieEnd) => self::PLAYING,
            default => self::COMPLETED,
        };

        return [
            'state' => $state,
            'label' => self::LABELS[$state],
            'now' => $current,
            'starts_at' => $window->start,
            'ends_at' => $window->movieEnd,
            'cleaning_starts_at' => $window->movieEnd,
            'room_ready_at' => $window->operationalEnd,
            'booking_closes_at' => $window->start->addMinutes(self::BOOKING_CUTOFF_MINUTES),
            'window' => $window,
        ];
    }

    public function state(Showtime $showtime, ?CarbonInterface $now = null): string
    {
        return $this->snapshot($showtime, $now)['state'];
    }

    public function isCustomerBookingOpen(Showtime $showtime, ?CarbonInterface $now = null): bool
    {
        if ($showtime->status !== 'active') {
            return false;
        }

        $snapshot = $this->snapshot($showtime, $now);

        return $snapshot['now']->lt($snapshot['booking_closes_at'])
            && $snapshot['now']->lt($snapshot['ends_at']);
    }

    public function applyFilter(Builder $query, string $state): Builder
    {
        if ($state === self::CANCELLED) {
            return $query->where('showtimes.status', self::CANCELLED);
        }

        if (! in_array($state, [self::UPCOMING, self::PLAYING, self::COMPLETED], true)) {
            return $query;
        }

        $query->where('showtimes.status', '!=', self::CANCELLED)
            ->join('movies as lifecycle_movies', 'lifecycle_movies.id', '=', 'showtimes.movie_id')
            ->select('showtimes.*');

        $driver = DB::connection()->getDriverName();
        $startExpression = $driver === 'sqlite'
            ? "datetime(date(showtimes.show_date) || ' ' || showtimes.show_time)"
            : 'timestamp(showtimes.show_date, showtimes.show_time)';
        $endExpression = $driver === 'sqlite'
            ? "datetime(date(showtimes.show_date) || ' ' || showtimes.show_time, '+' || lifecycle_movies.duration || ' minutes')"
            : 'timestampadd(minute, lifecycle_movies.duration, timestamp(showtimes.show_date, showtimes.show_time))';

        $cinemaGroups = Cinema::query()->get(['id', 'timezone'])
            ->groupBy(fn (Cinema $cinema): string => $this->validTimezone($cinema->timezone));

        return $query->where(function (Builder $outer) use ($cinemaGroups, $state, $startExpression, $endExpression): void {
            foreach ($cinemaGroups as $timezone => $cinemas) {
                $now = CarbonImmutable::now($timezone)->format('Y-m-d H:i:s');
                $outer->orWhere(function (Builder $scoped) use ($cinemas, $state, $startExpression, $endExpression, $now): void {
                    $scoped->whereIn('showtimes.cinema_id', $cinemas->pluck('id'));

                    match ($state) {
                        self::UPCOMING => $scoped->whereRaw("{$startExpression} > ?", [$now]),
                        self::PLAYING => $scoped
                            ->whereRaw("{$startExpression} <= ?", [$now])
                            ->whereRaw("{$endExpression} > ?", [$now]),
                        self::COMPLETED => $scoped->whereRaw("{$endExpression} <= ?", [$now]),
                    };
                });
            }

            if ($cinemaGroups->isEmpty()) {
                $outer->whereRaw('1 = 0');
            }
        });
    }

    private function currentTime(CarbonImmutable $startsAt, ?CarbonInterface $now): CarbonImmutable
    {
        return $now === null
            ? CarbonImmutable::now($startsAt->getTimezone())
            : CarbonImmutable::instance($now)->setTimezone($startsAt->getTimezone());
    }

    private function validTimezone(?string $timezone): string
    {
        return is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)
            ? $timezone
            : (string) config('cinema.timezone', 'Asia/Ho_Chi_Minh');
    }
}
