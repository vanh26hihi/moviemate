<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Movie;
use App\Models\Showtime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $today = now('Asia/Ho_Chi_Minh')->toDateString();
        $startDate = now('Asia/Ho_Chi_Minh')->subDays(6)->startOfDay();
        $endDate = now('Asia/Ho_Chi_Minh')->endOfDay();

        $paidBookings = Booking::query()->where('payment_status', 'paid');

        $stats = [
            'total_revenue' => (float) (clone $paidBookings)->sum('total_amount'),
            'tickets_sold' => BookingSeat::whereHas('booking', fn ($query) => $query->where('payment_status', 'paid'))->count(),
            'users' => User::count(),
            'now_showing_movies' => Movie::where('status', 'now_showing')->count(),
            'showtimes_today' => Showtime::whereDate('show_date', $today)->count(),
        ];

        $revenueByDay = $this->revenueByDay($startDate, $endDate);
        $topMovies = $this->topMovies($startDate, $endDate, 5);

        $recentBookings = Booking::with(['user', 'showtime.movie', 'bookingSeats.seat'])
            ->latest()
            ->limit(8)
            ->get();

        return view('admin.dashboard', compact('stats', 'revenueByDay', 'topMovies', 'recentBookings'));
    }

    private function revenueByDay(Carbon $startDate, Carbon $endDate): array
    {
        $rows = Booking::query()
            ->selectRaw('DATE(created_at) as booking_date')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as revenue')
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('booking_date')
            ->pluck('revenue', 'booking_date');

        $days = [];
        $maxRevenue = max((float) $rows->max(), 1);

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $key = $date->toDateString();
            $revenue = (float) ($rows[$key] ?? 0);

            $days[] = [
                'label' => $date->format('d/m'),
                'revenue' => $revenue,
                'height' => max(6, round(($revenue / $maxRevenue) * 100)),
            ];
        }

        return $days;
    }

    private function topMovies(Carbon $startDate, Carbon $endDate, int $limit)
    {
        return DB::table('movies')
            ->join('showtimes', 'showtimes.movie_id', '=', 'movies.id')
            ->join('bookings', function ($join) use ($startDate, $endDate) {
                $join->on('bookings.showtime_id', '=', 'showtimes.id')
                    ->where('bookings.payment_status', '=', 'paid')
                    ->whereBetween('bookings.created_at', [$startDate, $endDate]);
            })
            ->leftJoin('booking_seats', 'booking_seats.booking_id', '=', 'bookings.id')
            ->select('movies.id', 'movies.title', 'movies.slug', 'movies.poster')
            ->selectRaw('COUNT(booking_seats.id) as tickets_sold')
            ->selectRaw('COUNT(DISTINCT bookings.id) as bookings_count')
            ->selectRaw('COALESCE(SUM(booking_seats.price), 0) as revenue')
            ->groupBy('movies.id', 'movies.title', 'movies.slug', 'movies.poster')
            ->orderByDesc('tickets_sold')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();
    }
}
