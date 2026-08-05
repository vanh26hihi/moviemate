<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\Showtime;
use App\Services\CinemaContext;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct(private readonly CinemaContext $cinemaContext) {}

    public function index(Request $request)
    {
        $today = Carbon::today('Asia/Ho_Chi_Minh');
        $selectedDate = $this->normalizeSelectedDate($request->query('date'), $today);
        $cinema = $this->cinemaContext->current();
        $nowShowing = Movie::query()->where('status', 'now_showing')->orderByDesc('created_at')->get();
        $comingSoon = Movie::query()->where('status', 'coming_soon')->orderBy('release_date')->get();
        $scheduleDates = collect(range(0, 6))->map(function (int $offset) use ($today) {
            $date = $today->copy()->addDays($offset);

            return ['date' => $date->toDateString(), 'day' => $date->format('d'), 'label' => $offset === 0 ? 'Hôm nay' : $this->vietnameseWeekday($date)];
        });
        $scheduleShowtimes = Showtime::query()->with(['movie.genres', 'cinema', 'room'])
            ->where('cinema_id', $cinema->id)
            ->whereHas('movie', fn ($query) => $query->whereIn('status', ['now_showing', 'coming_soon']))
            ->whereHas('room', fn ($query) => $query->where('status', 'active'))
            ->where('status', 'active')
            ->whereBetween('show_date', [$today->toDateString(), $today->copy()->addDays(6)->toDateString()])
            ->orderBy('show_date')->orderBy('show_time')->get();
        $scheduleMoviesByDate = $scheduleShowtimes
            ->groupBy(fn (Showtime $showtime) => $showtime->show_date->toDateString())
            ->map(fn ($dateShowtimes) => $dateShowtimes->groupBy('movie_id')->map(fn ($items) => [
                'movie' => $items->first()->movie, 'showtimes' => $items->values(),
            ])->values());
        $showtimeDates = $scheduleShowtimes->pluck('show_date')
            ->map(fn ($date) => $date->toDateString())->unique()->values();
        $quickShowtimes = Showtime::query()->with(['movie.genres', 'cinema', 'room'])
            ->where('cinema_id', $cinema->id)
            ->whereHas('movie', fn ($query) => $query->whereIn('status', ['now_showing', 'coming_soon']))
            ->whereHas('room', fn ($query) => $query->where('status', 'active'))
            ->where('status', 'active')->whereDate('show_date', '>=', $today->toDateString())
            ->orderBy('show_date')->orderBy('show_time')->limit(10)->get();

        return view('user.home', compact(
            'nowShowing', 'comingSoon', 'quickShowtimes', 'cinema', 'scheduleDates',
            'selectedDate', 'scheduleMoviesByDate', 'showtimeDates'
        ));
    }

    private function normalizeSelectedDate(mixed $date, Carbon $fallback): string
    {
        if (! is_string($date) || $date === '') {
            return $fallback->toDateString();
        }
        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $date, $fallback->timezone);
        } catch (\Throwable) {
            return $fallback->toDateString();
        }

        if (! $parsed || $parsed->format('Y-m-d') !== $date
            || $parsed->lt($fallback) || $parsed->gt($fallback->copy()->addDays(6))) {
            return $fallback->toDateString();
        }

        return $parsed->toDateString();
    }

    private function vietnameseWeekday(Carbon $date): string
    {
        return match ((int) $date->dayOfWeekIso) {
            1 => 'Thứ 2', 2 => 'Thứ 3', 3 => 'Thứ 4', 4 => 'Thứ 5', 5 => 'Thứ 6', 6 => 'Thứ 7', default => 'Chủ nhật',
        };
    }
}
