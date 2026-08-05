<?php

namespace App\Services;

use App\Domain\Money\VndAmount;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Movie;
use App\Models\Payment;
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
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $chartDays = collect(range(6, 0))
            ->map(fn (int $daysAgo): CarbonImmutable => $today->subDays($daysAgo));
        $revenueChart = $this->revenueChart($chartDays->all());

        return [
            'metrics' => [
                'totalRevenue' => $this->vnd($this->recognizedBookings()->sum('bookings.total_amount')),
                'ticketsSold' => $this->ticketsSold(),
                'users' => User::query()->count(),
                'nowShowingMovies' => Movie::query()->where('status', 'now_showing')->count(),
            ],
            'revenueChart' => $revenueChart,
            'hasRevenueChartData' => collect($revenueChart)->contains(
                fn (array $day): bool => $day['revenue'] > 0,
            ),
            'topMovies' => $this->topMovies(),
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
            ->whereHas('booking', function (Builder $query): void {
                $query->where('payment_status', 'paid')
                    ->whereIn('booking_status', self::REVENUE_BOOKING_STATUSES)
                    ->whereHas('payments', function (Builder $payments): void {
                        $payments->where('status', Payment::STATUS_SUCCESS)
                            ->whereNotNull('verified_at')
                            ->whereNotNull('paid_at');
                    });
            })
            ->count();
    }

    /**
     * @param  array<int, CarbonImmutable>  $days
     * @return array<int, array{label: string, date: string, revenue: int, heightPercent: int}>
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
            ];
        })->all();
    }

    private function topMovies(): EloquentCollection
    {
        return Movie::query()
            ->select(['movies.id', 'movies.title'])
            ->selectRaw('COUNT(booking_seats.id) as tickets_sold')
            ->join('showtimes', 'showtimes.movie_id', '=', 'movies.id')
            ->join('bookings', 'bookings.showtime_id', '=', 'showtimes.id')
            ->joinSub($this->firstSuccessfulPayments(), 'first_successful_payments', function ($join): void {
                $join->on('first_successful_payments.booking_id', '=', 'bookings.id');
            })
            ->join('booking_seats', 'booking_seats.booking_id', '=', 'bookings.id')
            ->where('bookings.payment_status', 'paid')
            ->whereIn('bookings.booking_status', self::REVENUE_BOOKING_STATUSES)
            ->groupBy('movies.id', 'movies.title')
            ->orderByDesc('tickets_sold')
            ->orderBy('movies.title')
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
