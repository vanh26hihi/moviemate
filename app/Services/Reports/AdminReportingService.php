<?php

namespace App\Services\Reports;

use App\Models\Booking;
use App\Models\Movie;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AdminReportingService
{
    /** @var array<string, Collection<int, object>> */
    private array $recognizedRows = [];

    /** @var array<string, Collection<int, object>> */
    private array $seatMetrics = [];

    public function __construct(private readonly AuthoritativePaymentQuery $payments) {}

    /** @return array<string, mixed> */
    public function report(ReportScope $scope): array
    {
        $financeRows = $this->rows($scope, 'finance');
        $operationalRows = $this->rows($scope, 'operations');
        $summary = $this->summary($scope, $financeRows);

        return [
            'generatedAt' => CarbonImmutable::now((string) config('app.timezone', 'Asia/Ho_Chi_Minh')),
            'scope' => $scope,
            'filters' => $scope->query(),
            'cinemas' => $scope->availableCinemas,
            'summary' => $summary,
            'revenueSeries' => $this->revenueSeries($scope, $financeRows),
            'topMovies' => $this->topMovies($scope, $financeRows),
            'peakTimes' => $this->peakTimes($operationalRows),
            'genres' => $this->genres($operationalRows),
            'salesChannels' => $this->salesChannels($financeRows),
            'paymentMethods' => $this->paymentMethods($financeRows),
            'ticketOperations' => $this->ticketOperations($scope),
            'counterCreators' => $this->counterActors($financeRows, 'created_by_staff_id'),
            'counterSettlers' => $this->counterActors($financeRows, 'settled_by_user_id'),
            'todayShowtimes' => $this->todayShowtimes($scope),
            'currentMovies' => $this->currentMovies($scope, $operationalRows),
            'attention' => $this->attention($scope),
            'hasPeriodData' => $summary['revenue'] > 0
                || $summary['showtimes'] > 0
                || $summary['logicalTickets'] > 0,
        ];
    }

    /** @return array<string, int> */
    public function summary(ReportScope $scope, ?Collection $financeRows = null): array
    {
        $financeRows ??= $this->rows($scope, 'finance');
        $showtimes = $this->scopeCinemas(DB::table('showtimes as s'), $scope, 's.cinema_id')
            ->where('s.show_date', '>=', $scope->from->toDateString())
            ->where('s.show_date', '<', $scope->to->addDay()->toDateString())
            ->where('s.status', '!=', 'cancelled')
            ->count();

        return [
            'revenue' => (int) $financeRows->sum('amount'),
            'logicalTickets' => (int) $financeRows->sum('logical_tickets'),
            'physicalSeats' => (int) $financeRows->sum('physical_seats'),
            'paidBookings' => $financeRows->count(),
            'showtimes' => (int) $showtimes,
        ];
    }

    /** @return Collection<int, object> */
    public function financeRows(ReportScope $scope): Collection
    {
        return $this->rows($scope, 'finance');
    }

    /** @return list<array{date: string, label: string, revenue: int, transactions: int, heightPercent: int}> */
    public function revenueSeries(ReportScope $scope, ?Collection $financeRows = null): array
    {
        $financeRows ??= $this->rows($scope, 'finance');
        $values = [];
        foreach ($financeRows as $row) {
            $date = CarbonImmutable::parse((string) $row->finance_paid_at, 'UTC')
                ->setTimezone($row->cinema_timezone ?: config('app.timezone'))
                ->toDateString();
            $values[$date] ??= ['revenue' => 0, 'transactions' => 0];
            $values[$date]['revenue'] += (int) $row->amount;
            $values[$date]['transactions']++;
        }

        $series = [];
        for ($day = $scope->from; $day->lte($scope->to); $day = $day->addDay()) {
            $date = $day->toDateString();
            $series[] = [
                'date' => $date,
                'label' => $day->format('d/m'),
                'revenue' => (int) ($values[$date]['revenue'] ?? 0),
                'transactions' => (int) ($values[$date]['transactions'] ?? 0),
                'heightPercent' => 0,
            ];
        }

        $maximum = max(0, ...array_column($series, 'revenue'));
        foreach ($series as &$day) {
            $day['heightPercent'] = $maximum > 0 && $day['revenue'] > 0
                ? max(4, (int) round($day['revenue'] / $maximum * 100))
                : 0;
        }
        unset($day);

        return $series;
    }

    /** @return list<array<string, int|string|null>> */
    public function topMovies(ReportScope $scope, ?Collection $financeRows = null): array
    {
        $financeRows ??= $this->rows($scope, 'finance');
        $metric = $scope->metric;

        return $financeRows->groupBy('movie_id')->map(function (Collection $rows): array {
            $first = $rows->first();

            return [
                'movie_id' => (int) $first->movie_id,
                'title' => (string) $first->movie_title,
                'status' => (string) $first->movie_status,
                'revenue' => (int) $rows->sum('amount'),
                'logical_tickets' => (int) $rows->sum('logical_tickets'),
                'physical_seats' => (int) $rows->sum('physical_seats'),
                'booking_count' => $rows->count(),
            ];
        })->sort(function (array $left, array $right) use ($metric): int {
            $column = match ($metric) {
                'logical_tickets' => 'logical_tickets',
                'physical_seats' => 'physical_seats',
                default => 'revenue',
            };

            return [$right[$column], $right['revenue'], -$right['movie_id']]
                <=> [$left[$column], $left['revenue'], -$left['movie_id']];
        })->take(10)->values()->all();
    }

    /** @return list<array<string, int|string>> */
    public function peakTimes(Collection $operationalRows): array
    {
        $buckets = collect([
            'morning' => ['label' => '06:00–11:59', 'logical_tickets' => 0, 'physical_seats' => 0, 'bookings' => 0],
            'afternoon' => ['label' => '12:00–16:59', 'logical_tickets' => 0, 'physical_seats' => 0, 'bookings' => 0],
            'evening' => ['label' => '17:00–20:59', 'logical_tickets' => 0, 'physical_seats' => 0, 'bookings' => 0],
            'late' => ['label' => '21:00–05:59', 'logical_tickets' => 0, 'physical_seats' => 0, 'bookings' => 0],
        ]);

        foreach ($operationalRows as $row) {
            $hour = (int) substr((string) $row->show_time, 0, 2);
            $key = match (true) {
                $hour >= 6 && $hour < 12 => 'morning',
                $hour >= 12 && $hour < 17 => 'afternoon',
                $hour >= 17 && $hour < 21 => 'evening',
                default => 'late',
            };
            $bucket = $buckets[$key];
            $bucket['logical_tickets'] += (int) $row->logical_tickets;
            $bucket['physical_seats'] += (int) $row->physical_seats;
            $bucket['bookings']++;
            $buckets[$key] = $bucket;
        }

        return $buckets->map(fn (array $bucket, string $key): array => ['key' => $key, ...$bucket])->values()->all();
    }

    /** @return list<array<string, int|string>> */
    public function genres(Collection $operationalRows): array
    {
        $movieIds = $operationalRows->pluck('movie_id')->map(fn ($id): int => (int) $id)->unique()->values();
        if ($movieIds->isEmpty()) {
            return [];
        }

        $genresByMovie = DB::table('movie_genre as mg')->join('genres as g', 'g.id', '=', 'mg.genre_id')
            ->whereIn('mg.movie_id', $movieIds)
            ->orderBy('g.name')
            ->get(['mg.movie_id', 'g.id as genre_id', 'g.name'])
            ->groupBy('movie_id');
        $result = [];
        foreach ($operationalRows as $row) {
            foreach ($genresByMovie->get($row->movie_id, collect()) as $genre) {
                $key = (int) $genre->genre_id;
                $result[$key] ??= [
                    'genre_id' => $key,
                    'name' => (string) $genre->name,
                    'logical_tickets' => 0,
                    'physical_seats' => 0,
                    'booking_count' => 0,
                    'showtimes' => [],
                ];
                $result[$key]['logical_tickets'] += (int) $row->logical_tickets;
                $result[$key]['physical_seats'] += (int) $row->physical_seats;
                $result[$key]['booking_count']++;
                $result[$key]['showtimes'][(int) $row->showtime_id] = true;
            }
        }

        return collect($result)->map(function (array $genre): array {
            $genre['showtime_count'] = count($genre['showtimes']);
            unset($genre['showtimes']);

            return $genre;
        })->sortByDesc('logical_tickets')->values()->all();
    }

    /** @return list<array<string, int|string>> */
    public function salesChannels(Collection $financeRows): array
    {
        $labels = [Booking::SALES_CHANNEL_ONLINE => 'Trực tuyến', Booking::SALES_CHANNEL_COUNTER => 'Tại quầy'];

        return collect($labels)->map(function (string $label, string $channel) use ($financeRows): array {
            $rows = $financeRows->where('sales_channel', $channel);

            return [
                'key' => $channel, 'label' => $label, 'bookings' => $rows->count(),
                'revenue' => (int) $rows->sum('amount'),
                'logical_tickets' => (int) $rows->sum('logical_tickets'),
                'physical_seats' => (int) $rows->sum('physical_seats'),
            ];
        })->values()->all();
    }

    /** @return list<array<string, int|string>> */
    public function paymentMethods(Collection $financeRows): array
    {
        $labels = ['vnpay' => 'VNPAY', 'zalopay' => 'ZaloPay', 'payos' => 'payOS', Payment::PROVIDER_COUNTER_CASH => 'Tiền mặt tại quầy'];

        return collect($labels)->map(function (string $label, string $provider) use ($financeRows): array {
            $rows = $financeRows->where('provider', $provider);

            return ['key' => $provider, 'label' => $label, 'transactions' => $rows->count(), 'revenue' => (int) $rows->sum('amount')];
        })->values()->all();
    }

    /** @return array<string, int> */
    public function ticketOperations(ReportScope $scope): array
    {
        $eligible = $this->recognizedQuery($scope, 'operations')->select('b.id as booking_id');
        $row = DB::query()->fromSub($eligible, 'eligible')
            ->leftJoin('booking_ticket_prints as tp', 'tp.booking_id', '=', 'eligible.booking_id')
            ->selectRaw('COUNT(*) as eligible')
            ->selectRaw('SUM(CASE WHEN tp.id IS NULL THEN 1 ELSE 0 END) as unprinted')
            ->selectRaw("SUM(CASE WHEN tp.status = 'printed' THEN 1 ELSE 0 END) as printed")
            ->selectRaw("SUM(CASE WHEN tp.status IN ('retry_allowed', 'retry_requires_authorization') THEN 1 ELSE 0 END) as print_failed")
            ->selectRaw("SUM(CASE WHEN tp.status IN ('printing', 'retry_authorized') THEN 1 ELSE 0 END) as print_waiting")
            ->first();
        $eligibleCount = (int) ($row->eligible ?? 0);

        return [
            'eligible' => $eligibleCount,
            'unprinted' => (int) ($row->unprinted ?? 0),
            'printed' => (int) ($row->printed ?? 0),
            'printFailed' => (int) ($row->print_failed ?? 0),
            'printWaiting' => (int) ($row->print_waiting ?? 0),
        ];
    }

    /** @return list<array<string, int|string>> */
    public function counterActors(Collection $financeRows, string $actorColumn): array
    {
        $rows = $financeRows->where('sales_channel', Booking::SALES_CHANNEL_COUNTER)
            ->filter(fn (object $row): bool => (int) ($row->{$actorColumn} ?? 0) > 0);
        $names = DB::table('users')->whereIn('id', $rows->pluck($actorColumn)->unique())
            ->pluck('name', 'id');

        return $rows->groupBy(fn (object $row): string => $row->{$actorColumn}.':'.$row->cinema_id)
            ->map(function (Collection $group) use ($actorColumn, $names): array {
                $first = $group->first();
                $actorId = (int) $first->{$actorColumn};

                return [
                    'actor_id' => $actorId,
                    'name' => (string) ($names[$actorId] ?? 'Tài khoản không còn khả dụng'),
                    'cinema' => (string) $first->cinema_name,
                    'bookings' => $group->count(),
                    'logical_tickets' => (int) $group->sum('logical_tickets'),
                    'physical_seats' => (int) $group->sum('physical_seats'),
                    'revenue' => (int) $group->sum('amount'),
                ];
            })->sortByDesc('bookings')->values()->all();
    }

    /** @return array{unresolved: int, review: int, total: int} */
    public function attention(ReportScope $scope): array
    {
        $query = DB::table('payments as p')->join('bookings as b', 'b.id', '=', 'p.booking_id')
            ->whereIn('p.status', [Payment::STATUS_UNRESOLVED, Payment::STATUS_REVIEW]);
        $this->scopeCinemas($query, $scope, 'b.cinema_id');
        $query->when($scope->salesChannel, fn (Builder $query, string $channel): Builder => $query->where('b.sales_channel', $channel))
            ->when($scope->provider, fn (Builder $query, string $provider): Builder => $query->where('p.provider', $provider));
        $counts = $query->selectRaw('p.status, COUNT(*) as aggregate')->groupBy('p.status')->pluck('aggregate', 'status');
        $unresolved = (int) ($counts[Payment::STATUS_UNRESOLVED] ?? 0);
        $review = (int) ($counts[Payment::STATUS_REVIEW] ?? 0);

        return ['unresolved' => $unresolved, 'review' => $review, 'total' => $unresolved + $review];
    }

    /** @return list<array<string, mixed>> */
    public function currentMovies(ReportScope $scope, ?Collection $operationalRows = null): array
    {
        $operationalRows ??= $this->rows($scope, 'operations');
        $query = DB::table('movies as m')->join('showtimes as s', 's.movie_id', '=', 'm.id')
            ->where('m.status', Movie::STATUS_NOW_SHOWING)
            ->where('s.status', 'active')
            ->where('s.show_date', '>=', $scope->from->toDateString())
            ->where('s.show_date', '<', $scope->to->addDay()->toDateString());
        $this->scopeCinemas($query, $scope, 's.cinema_id');
        $movies = $query->selectRaw('m.id, m.title, COUNT(DISTINCT s.id) as showtime_count')
            ->groupBy('m.id', 'm.title')->orderByDesc('showtime_count')->orderBy('m.title')->limit(10)->get();
        $genres = DB::table('movie_genre as mg')->join('genres as g', 'g.id', '=', 'mg.genre_id')
            ->whereIn('mg.movie_id', $movies->pluck('id'))->orderBy('g.name')->get(['mg.movie_id', 'g.name'])->groupBy('movie_id');
        $sold = $operationalRows->groupBy('movie_id');

        return $movies->map(function (object $movie) use ($genres, $sold): array {
            $rows = $sold->get($movie->id, collect());

            return [
                'movie_id' => (int) $movie->id,
                'title' => (string) $movie->title,
                'genres' => $genres->get($movie->id, collect())->pluck('name')->join(', '),
                'showtime_count' => (int) $movie->showtime_count,
                'logical_tickets' => (int) $rows->sum('logical_tickets'),
            ];
        })->all();
    }

    /** @return list<array<string, mixed>> */
    public function todayShowtimes(ReportScope $scope): array
    {
        $query = DB::table('showtimes as s')
            ->join('movies as m', 'm.id', '=', 's.movie_id')
            ->join('rooms as r', 'r.id', '=', 's.room_id')
            ->join('cinemas as c', 'c.id', '=', 's.cinema_id');
        $this->scopeCinemas($query, $scope, 's.cinema_id');
        $query->where(function (Builder $dates) use ($scope): void {
            foreach ($scope->cinemasByTimezone() as $timezone => $cinemas) {
                $today = CarbonImmutable::now($timezone)->toDateString();
                $dates->orWhere(fn (Builder $branch): Builder => $branch
                    ->whereIn('s.cinema_id', $cinemas->pluck('id'))
                    ->where('s.show_date', '>=', $today)
                    ->where('s.show_date', '<', CarbonImmutable::parse($today, $timezone)->addDay()->toDateString()));
            }
        });
        $showtimes = $query->select([
            's.id', 's.cinema_id', 's.show_date', 's.show_time', 's.status',
            'm.title as movie_title', 'm.duration', 'r.code as room_code', 'r.name as room_name',
            'r.cleaning_buffer_minutes', 'c.name as cinema_name', 'c.timezone', 'c.default_cleaning_buffer_minutes',
        ])->orderBy('s.show_date')->orderBy('s.show_time')->limit(20)->get();

        if ($showtimes->isEmpty()) {
            return [];
        }

        $soldQuery = $this->recognizedQuery($scope, 'none')
            ->whereIn('s.id', $showtimes->pluck('id'))
            ->select('b.id as booking_id');
        $sold = $this->seatMetricsForQuery($soldQuery)->groupBy('showtime_id');

        return $showtimes->map(function (object $showtime) use ($sold): array {
            $timezone = $showtime->timezone ?: config('app.timezone');
            $start = CarbonImmutable::createFromFormat(
                '!Y-m-d H:i:s',
                substr((string) $showtime->show_date, 0, 10).' '.substr((string) $showtime->show_time, 0, 8),
                $timezone,
            );
            $end = $start->addMinutes((int) $showtime->duration);
            $cleaning = $showtime->cleaning_buffer_minutes ?? $showtime->default_cleaning_buffer_minutes ?? 0;
            $metrics = $sold->get($showtime->id, collect());

            return [
                'id' => (int) $showtime->id,
                'cinema' => (string) $showtime->cinema_name,
                'movie' => (string) $showtime->movie_title,
                'room' => (string) ($showtime->room_code ?: $showtime->room_name),
                'start' => $start,
                'end' => $end,
                'cleaningUntil' => $end->addMinutes((int) $cleaning),
                'logicalTickets' => (int) $metrics->sum('logical_tickets'),
                'physicalSeats' => (int) $metrics->sum('physical_seats'),
                'status' => (string) $showtime->status,
            ];
        })->sortBy('start')->values()->all();
    }

    /** @return Collection<int, object> */
    private function rows(ReportScope $scope, string $mode): Collection
    {
        $key = $this->cacheKey($scope, $mode);
        if (isset($this->recognizedRows[$key])) {
            return $this->recognizedRows[$key];
        }

        $rows = $this->recognizedQuery($scope, $mode)->get();
        $metrics = $this->seatMetrics($scope, $mode)->keyBy('booking_id');
        foreach ($rows as $row) {
            $seat = $metrics->get($row->booking_id);
            $row->amount = (int) $row->amount;
            $row->logical_tickets = (int) ($seat->logical_tickets ?? 0);
            $row->physical_seats = (int) ($seat->physical_seats ?? 0);
            $row->ticket_revenue = (int) ($seat->ticket_revenue ?? 0);
        }

        return $this->recognizedRows[$key] = $rows;
    }

    private function recognizedQuery(ReportScope $scope, string $mode): Builder
    {
        $query = DB::table('bookings as b')
            ->joinSub($this->payments->authoritative(), 'ap', 'ap.booking_id', '=', 'b.id')
            ->join('showtimes as s', 's.id', '=', 'b.showtime_id')
            ->join('movies as m', 'm.id', '=', 's.movie_id')
            ->join('cinemas as c', 'c.id', '=', 'b.cinema_id')
            ->select([
                'b.id as booking_id', 'b.cinema_id', 'b.showtime_id', 'b.sales_channel',
                'b.created_by_staff_id', 'b.seat_subtotal', 'b.food_subtotal',
                'ap.payment_id', 'ap.provider', 'ap.amount', 'ap.finance_paid_at', 'ap.settled_by_user_id',
                's.movie_id', 's.show_date', 's.show_time', 'm.title as movie_title', 'm.status as movie_status',
                'c.name as cinema_name', 'c.timezone as cinema_timezone',
            ]);
        $this->scopeCinemas($query, $scope, 'b.cinema_id');
        $query->when($scope->salesChannel, fn (Builder $query, string $channel): Builder => $query->where('b.sales_channel', $channel))
            ->when($scope->provider, fn (Builder $query, string $provider): Builder => $query->where('ap.provider', $provider));

        if ($mode === 'finance') {
            $this->applyFinanceRange($query, $scope, 'ap.finance_paid_at', 'b.cinema_id');
        } elseif ($mode === 'operations') {
            $query->where('s.show_date', '>=', $scope->from->toDateString())
                ->where('s.show_date', '<', $scope->to->addDay()->toDateString());
        }

        return $query;
    }

    /** @return Collection<int, object> */
    private function seatMetrics(ReportScope $scope, string $mode): Collection
    {
        $key = $this->cacheKey($scope, $mode);
        if (isset($this->seatMetrics[$key])) {
            return $this->seatMetrics[$key];
        }

        return $this->seatMetrics[$key] = $this->seatMetricsForQuery(
            $this->recognizedQuery($scope, $mode)->select('b.id as booking_id'),
        );
    }

    /** @return Collection<int, object> */
    private function seatMetricsForQuery(Builder $recognized): Collection
    {
        $unit = DB::getDriverName() === 'sqlite'
            ? "COALESCE(bs.pricing_unit_key, 'seat:' || bs.seat_id)"
            : "COALESCE(bs.pricing_unit_key, CONCAT('seat:', bs.seat_id))";

        return DB::table('booking_seats as bs')->joinSub($recognized, 'recognized', 'recognized.booking_id', '=', 'bs.booking_id')
            ->selectRaw('bs.booking_id, MAX(bs.showtime_id) as showtime_id')
            ->selectRaw("COUNT(DISTINCT {$unit}) as logical_tickets")
            ->selectRaw('COUNT(bs.id) as physical_seats')
            ->selectRaw('COALESCE(SUM(bs.price), 0) as ticket_revenue')
            ->groupBy('bs.booking_id')->get();
    }

    private function applyFinanceRange(Builder $query, ReportScope $scope, string $paidColumn, string $cinemaColumn): void
    {
        $query->where(function (Builder $ranges) use ($scope, $paidColumn, $cinemaColumn): void {
            foreach ($scope->cinemasByTimezone() as $timezone => $cinemas) {
                $start = CarbonImmutable::createFromFormat('!Y-m-d', $scope->from->toDateString(), $timezone)->utc();
                $end = CarbonImmutable::createFromFormat('!Y-m-d', $scope->to->toDateString(), $timezone)->addDay()->utc();
                $ranges->orWhere(fn (Builder $branch): Builder => $branch
                    ->whereIn($cinemaColumn, $cinemas->pluck('id'))
                    ->where($paidColumn, '>=', $start->toDateTimeString())
                    ->where($paidColumn, '<', $end->toDateTimeString()));
            }
        });
    }

    private function scopeCinemas(Builder $query, ReportScope $scope, string $column): Builder
    {
        $ids = $scope->cinemaIds();

        return $ids === [] ? $query->whereRaw('1 = 0') : $query->whereIn($column, $ids);
    }

    private function cacheKey(ReportScope $scope, string $mode): string
    {
        return $mode.'|'.implode(',', $scope->cinemaIds()).'|'.$scope->from->toDateString().'|'.$scope->to->toDateString()
            .'|'.$scope->salesChannel.'|'.$scope->provider;
    }
}
