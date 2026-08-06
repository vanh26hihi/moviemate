<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Services\CinemaContext;
use App\Services\PublicShowtimeCatalog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class HomeController extends Controller
{
    public function __construct(
        private readonly CinemaContext $cinemaContext,
        private readonly PublicShowtimeCatalog $catalog,
    ) {}

    public function index(Request $request)
    {
        $cinema = $this->cinemaContext->preference();
        $today = Carbon::today($cinema?->timezone ?: 'Asia/Ho_Chi_Minh');
        try {
            $selectedDate = $this->catalog->date($request->query('date'), $cinema);
        } catch (ValidationException) {
            $selectedDate = $today->toDateString();
        }
        $nowShowing = Movie::query()->where('status', 'now_showing')->orderByDesc('created_at')->get();
        $comingSoon = Movie::query()->where('status', 'coming_soon')->orderBy('release_date')->get();
        $scheduleDates = $this->catalog->dates($cinema)->take(7)->values();
        $scheduleShowtimes = $this->catalog->between(
            $today->toDateString(), $today->copy()->addDays(6)->toDateString(), $cinema,
        );
        $scheduleMoviesByDate = $scheduleShowtimes
            ->groupBy(fn ($showtime) => $showtime->show_date->toDateString())
            ->map(fn ($dateShowtimes) => $dateShowtimes->groupBy('movie_id')->map(fn ($items) => [
                'movie' => $items->first()->movie, 'showtimes' => $items->values(),
            ])->values());
        $showtimeDates = $scheduleShowtimes->pluck('show_date')
            ->map(fn ($date) => $date->toDateString())->unique()->values();
        $quickShowtimes = $scheduleShowtimes->take(10);

        return view('user.home', compact(
            'nowShowing', 'comingSoon', 'quickShowtimes', 'cinema', 'scheduleDates',
            'selectedDate', 'scheduleMoviesByDate', 'showtimeDates'
        ));
    }
}
