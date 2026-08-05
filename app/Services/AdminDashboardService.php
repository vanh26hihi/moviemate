<?php

namespace App\Services;

use App\Domain\Money\VndAmount;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Movie;
use App\Models\Payment;
use App\Models\Showtime;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class AdminDashboardService
{
    private const REVENUE_BOOKING_STATUSES = ['paid', 'used'];

    /** @return array<string, mixed> */
    public function overview(): array
    {
        $timezone = (string) config('app.timezone', 'UTC');
        $generatedAt = CarbonImmutable::now($timezone);
        $today = $generatedAt->startOfDay();
        $chartDays = collect(range(6, 0))
            ->map(fn (int $daysAgo): CarbonImmutable => $today->subDays($daysAgo));
        $revenueChart = $this->revenueChart($chartDays->all());
        $topMovies = $this->topMovies(
            $today->subDays(6)->utc(),
            $today->addDay()->utc(),
        );

        return [
            'generatedAt' => $generatedAt,
            'metrics' => [
                'totalRevenue' => $this->vnd($this->recognizedBookings()->sum('bookings.total_amount')),
                'ticketsSold' => $this->ticketsSold(),
                'users' => User::query()->count(),
                'nowShowingMovies' => Movie::query()->where('status', 'now_showing')->count(),
                'showtimesToday' => Showtime::query()
                    ->whereDate('show_date', $today->toDateString())
                    ->where('status', 'active')
                    ->whereHas('cinema', fn (Builder $query): Builder => $query
                        ->active()
                        ->primary()
                        ->where('canonical_key', CinemaContext::CANONICAL_KEY))
                    ->whereHas('room', fn (Builder $query): Builder => $query->operational())
                    ->count(),
            ],
            'operations' => [
                'pendingBookings' => Booking::query()
                    ->where('booking_status', 'pending_payment')
                    ->where('payment_status', 'unpaid')
                    ->where(function (Builder $query) use ($generatedAt): void {
                        $query->whereNull('expires_at')
                            ->orWhere('expires_at', '>', $generatedAt->utc());
                    })
                    ->count(),
            ],
            'revenueChart' => $revenueChart,
            'hasRevenueChartData' => collect($revenueChart)->contains(
                fn (array $day): bool => $day['revenue'] > 0,
            ),
            'topMovies' => $topMovies,
            'recentBookings' => $this->recentBookings(),
        ];
    }

    private function recognizedBookings(): Builder
    {
        return Booking::query()
            ->joinSub($this->firstSuccessfulPayments(), 'first_successful_payments', function ($join): void {
                $join->on('first_successful_payments.booking_id', '=', 'bookings.id');
            })
            ->where('bookings.payment_status', 'paid')
            ->whereIn('bookings.booking_status', self::REVENUE_BOOKING_STATUSES);
    }

    private function firstSuccessfulPayments(): Builder
    {
        return Payment::query()
            ->selectRaw('booking_id, MIN(paid_at) as recognized_at')
            ->where('status', Payment::STATUS_SUCCESS)
            ->whereNotNull('verified_at')
            ->whereNotNull('paid_at')
            ->groupBy('booking_id');
    }

    private function ticketsSold(): int
    {
        return BookingSeat::query()
            ->joinSub(
                $this->recognizedBookings()->select('bookings.id'),
                'recognized_bookings',
                function ($join): void {
                    $join->on('recognized_bookings.id', '=', 'booking_seats.booking_id');
                },
            )
            ->count('booking_seats.id');
    }

    /**
     * @param  array<int, CarbonImmutable>  $days
     * @return array<int, array{label: string, date: string, revenue: int, heightPercent: int, isToday: bool}>
     */
    private function revenueChart(array $days): array
    {
        $selects = [];
        $bindings = [];

        foreach ($days as $index => $day) {
            $start = $day->utc();
            $end = $day->addDay()->utc();
            $selects[] = "SUM(CASE WHEN first_successful_payments.recognized_at >= ? AND first_successful_payments.recognized_at < ? THEN bookings.total_amount ELSE 0 END) AS revenue_{$index}";
            $bindings[] = $start->toDateTimeString();
            $bindings[] = $end->toDateTimeString();
        }

        $aggregate = $this->recognizedBookings()
            ->selectRaw(implode(', ', $selects), $bindings)
            ->first();
        $revenues = collect($days)->map(
            fn (CarbonImmutable $day, int $index): int => $this->vnd($aggregate?->getAttribute("revenue_{$index}")),
        );
        $maximum = max(0, (int) $revenues->max());

        return collect($days)->map(function (CarbonImmutable $day, int $index) use ($revenues, $maximum): array {
            $revenue = $revenues[$index];

            return [
                'label' => $this->weekdayLabel($day),
                'date' => $day->format('d/m'),
                'revenue' => $revenue,
                'heightPercent' => $maximum > 0 && $revenue > 0
                    ? max(4, (int) round(($revenue / $maximum) * 100))
                    : 0,
                'isToday' => $day->isToday(),
            ];
        })->all();
    }

    private function topMovies(CarbonImmutable $periodStart, CarbonImmutable $periodEnd): EloquentCollection
    {
        $recognizedBookings = $this->recognizedBookings()
            ->where('first_successful_payments.recognized_at', '>=', $periodStart->toDateTimeString())
            ->where('first_successful_payments.recognized_at', '<', $periodEnd->toDateTimeString())
            ->select([
                'bookings.id',
                'bookings.showtime_id',
                'bookings.total_amount',
            ]);
        $bookingTicketCounts = BookingSeat::query()
            ->selectRaw('booking_id, COUNT(id) as tickets_sold')
            ->groupBy('booking_id');

        return Movie::query()
            ->select(['movies.id', 'movies.title', 'movies.poster'])
            ->selectRaw('SUM(paid_booking_tickets.tickets_sold) as tickets_sold')
            ->selectRaw('SUM(recognized_bookings.total_amount) as revenue')
            ->selectRaw('COUNT(recognized_bookings.id) as booking_count')
            ->join('showtimes', 'showtimes.movie_id', '=', 'movies.id')
            ->joinSub($recognizedBookings, 'recognized_bookings', function ($join): void {
                $join->on('recognized_bookings.showtime_id', '=', 'showtimes.id');
            })
            ->joinSub($bookingTicketCounts, 'paid_booking_tickets', function ($join): void {
                $join->on('paid_booking_tickets.booking_id', '=', 'recognized_bookings.id');
            })
            ->groupBy('movies.id', 'movies.title', 'movies.poster')
            ->orderByDesc('tickets_sold')
            ->orderByDesc('revenue')
            ->orderBy('movies.id')
            ->limit(5)
            ->get();
    }

    private function recentBookings(): EloquentCollection
    {
        return Booking::query()
            ->select([
                'id',
                'user_id',
                'showtime_id',
                'booking_code',
                'total_amount',
                'payment_status',
                'booking_status',
                'created_at',
            ])
            ->with([
                'user:id,name',
                'showtime:id,movie_id,cinema_id,room_id,show_date,show_time',
                'showtime.movie:id,title',
                'showtime.cinema:id,name',
                'showtime.room:id,name',
            ])
            ->latest('id')
            ->limit(6)
            ->get();
    }

    private function weekdayLabel(CarbonImmutable $day): string
    {
        return match ($day->dayOfWeek) {
            CarbonImmutable::SUNDAY => 'CN',
            CarbonImmutable::MONDAY => 'T2',
            CarbonImmutable::TUESDAY => 'T3',
            CarbonImmutable::WEDNESDAY => 'T4',
            CarbonImmutable::THURSDAY => 'T5',
            CarbonImmutable::FRIDAY => 'T6',
            default => 'T7',
        };
    }

    private function vnd(mixed $amount): int
    {
        if ($amount === null) {
            return 0;
        }

        if (is_float($amount) && is_finite($amount) && floor($amount) === $amount) {
            $amount = number_format($amount, 0, '.', '');
        }

        return VndAmount::fromDatabase($amount)->value();
    }
}
