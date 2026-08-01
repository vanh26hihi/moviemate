<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Cinema;
use App\Models\Movie;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function revenue(Request $request)
    {
        [$startDate, $endDate] = $this->dateRange($request);

        $paidBookings = Booking::query()
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalRevenue = (float) (clone $paidBookings)->sum('total_amount');
        $paidBookingIds = (clone $paidBookings)->pluck('id');
        $ticketsSold = BookingSeat::whereIn('booking_id', $paidBookingIds)->count();
        $averageTicketPrice = $ticketsSold > 0 ? $totalRevenue / $ticketsSold : 0;
        $cancelledBookings = Booking::where('booking_status', 'cancelled')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
        $allBookings = Booking::whereBetween('created_at', [$startDate, $endDate])->count();
        $cancelRate = $allBookings > 0 ? ($cancelledBookings / $allBookings) * 100 : 0;

        $summary = compact('totalRevenue', 'ticketsSold', 'averageTicketPrice', 'cancelledBookings', 'cancelRate');

        $revenueByDay = $this->revenueByDay($startDate, $endDate);
        $revenueByCinema = $this->revenueByCinema($startDate, $endDate);
        $paymentMethods = $this->paymentMethods($startDate, $endDate, $totalRevenue);

        return view('admin.analytics.revenue', compact(
            'startDate',
            'endDate',
            'summary',
            'revenueByDay',
            'revenueByCinema',
            'paymentMethods'
        ));
    }

    public function topMovies(Request $request)
    {
        [$startDate, $endDate] = $this->dateRange($request);
        $cinemaId = $request->integer('cinema_id') ?: null;

        $topMovies = $this->topMoviesQuery($startDate, $endDate, $cinemaId)->paginate(20)->withQueryString();
        $cinemas = Cinema::orderBy('name')->get();

        $totalTickets = $topMovies->getCollection()->sum('tickets_sold');
        $totalRevenue = $topMovies->getCollection()->sum('revenue');

        return view('admin.analytics.top-movies', compact(
            'startDate',
            'endDate',
            'cinemaId',
            'cinemas',
            'topMovies',
            'totalTickets',
            'totalRevenue'
        ));
    }

    private function dateRange(Request $request): array
    {
        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->start_date)->startOfDay()
            : now('Asia/Ho_Chi_Minh')->startOfMonth();

        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->end_date)->endOfDay()
            : now('Asia/Ho_Chi_Minh')->endOfDay();

        if ($startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
        }

        return [$startDate, $endDate];
    }

    private function revenueByDay(Carbon $startDate, Carbon $endDate): array
    {
        $rows = Booking::query()
            ->selectRaw('DATE(created_at) as booking_date')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as revenue')
            ->selectRaw('COUNT(*) as bookings_count')
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('booking_date')
            ->get()
            ->keyBy('booking_date');

        $days = [];
        $maxRevenue = max((float) $rows->max('revenue'), 1);

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $key = $date->toDateString();
            $row = $rows->get($key);
            $revenue = (float) ($row->revenue ?? 0);

            $days[] = [
                'label' => $date->format('d/m'),
                'date' => $key,
                'revenue' => $revenue,
                'bookings_count' => (int) ($row->bookings_count ?? 0),
                'height' => max(4, round(($revenue / $maxRevenue) * 100)),
            ];
        }

        return $days;
    }

    private function revenueByCinema(Carbon $startDate, Carbon $endDate)
    {
        return DB::table('cinemas')
            ->join('showtimes', 'showtimes.cinema_id', '=', 'cinemas.id')
            ->join('bookings', function ($join) use ($startDate, $endDate) {
                $join->on('bookings.showtime_id', '=', 'showtimes.id')
                    ->where('bookings.payment_status', '=', 'paid')
                    ->whereBetween('bookings.created_at', [$startDate, $endDate]);
            })
            ->select('cinemas.id', 'cinemas.name')
            ->selectRaw('COALESCE(SUM(bookings.total_amount), 0) as revenue')
            ->selectRaw('COUNT(DISTINCT bookings.id) as bookings_count')
            ->groupBy('cinemas.id', 'cinemas.name')
            ->orderByDesc('revenue')
            ->get();
    }

    private function paymentMethods(Carbon $startDate, Carbon $endDate, float $totalRevenue)
    {
        return DB::table('payments')
            ->join('bookings', 'bookings.id', '=', 'payments.booking_id')
            ->where('bookings.payment_status', 'paid')
            ->whereBetween('bookings.created_at', [$startDate, $endDate])
            ->select('payments.payment_method')
            ->selectRaw('COUNT(*) as transactions_count')
            ->selectRaw('COALESCE(SUM(bookings.total_amount), 0) as revenue')
            ->groupBy('payments.payment_method')
            ->orderByDesc('revenue')
            ->get()
            ->map(function ($method) use ($totalRevenue) {
                $method->percent = $totalRevenue > 0 ? ((float) $method->revenue / $totalRevenue) * 100 : 0;
                return $method;
            });
    }

    private function topMoviesQuery(Carbon $startDate, Carbon $endDate, ?int $cinemaId = null)
    {
        return DB::table('movies')
            ->join('showtimes', 'showtimes.movie_id', '=', 'movies.id')
            ->join('bookings', function ($join) use ($startDate, $endDate) {
                $join->on('bookings.showtime_id', '=', 'showtimes.id')
                    ->where('bookings.payment_status', '=', 'paid')
                    ->whereBetween('bookings.created_at', [$startDate, $endDate]);
            })
            ->leftJoin('booking_seats', 'booking_seats.booking_id', '=', 'bookings.id')
            ->when($cinemaId, fn ($query) => $query->where('showtimes.cinema_id', $cinemaId))
            ->select('movies.id', 'movies.title', 'movies.slug', 'movies.poster', 'movies.status')
            ->selectRaw('COUNT(booking_seats.id) as tickets_sold')
            ->selectRaw('COUNT(DISTINCT bookings.id) as bookings_count')
            ->selectRaw('COALESCE(SUM(booking_seats.price), 0) as revenue')
            ->groupBy('movies.id', 'movies.title', 'movies.slug', 'movies.poster', 'movies.status')
            ->orderByDesc('tickets_sold')
            ->orderByDesc('revenue');
    }
}
