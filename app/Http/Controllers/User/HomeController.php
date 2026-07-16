<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Showtime;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Show the home page with now showing and coming soon movies.
     */
    public function index(Request $request)
    {
        $timezone = 'Asia/Ho_Chi_Minh';
        $today = Carbon::today($timezone);
        $endDate = $today->copy()->addDays(6);
        $selectedDate = $this->normalizeSelectedDate($request->query('date'), $today);
        $cityOptions = $this->cityOptions();
        $brandTabs = ['Tất cả', 'MovieMate', 'CGV', 'Lotte', 'Galaxy', 'BHD', 'Beta', 'Cinestar'];
        $selectedCity = $this->normalizeSelectedCity($request->query('city'), array_keys($cityOptions));
        $selectedBrand = $this->normalizeSelectedBrand($request->query('brand'), $brandTabs);

        $nowShowing = Movie::where('status', 'now_showing')
            ->orderByDesc('created_at')
            ->get();

        $comingSoon = Movie::where('status', 'coming_soon')
            ->orderBy('release_date')
            ->get();

        $cinemas = Cinema::query()
            ->where('status', 'active')
            ->when($selectedCity, function ($query) use ($cityOptions, $selectedCity) {
                $aliases = $cityOptions[$selectedCity] ?? [$selectedCity];

                $query->where(function ($cityQuery) use ($aliases) {
                    foreach ($aliases as $alias) {
                        $cityQuery->orWhere('city', 'like', '%'.$alias.'%')
                            ->orWhere('address', 'like', '%'.$alias.'%');
                    }
                });
            })
            ->when($selectedBrand, function ($query) use ($selectedBrand) {
                $query->where('name', 'like', '%'.$selectedBrand.'%');
            })
            ->withCount([
                'showtimes as active_showtimes_count' => function ($query) use ($selectedDate) {
                    $query->where('status', 'active')
                        ->whereDate('show_date', $selectedDate);
                },
            ])
            ->orderBy('name')
            ->get();

        $requestedCinemaId = $request->integer('cinema_id');
        $selectedCinema = $cinemas->firstWhere('id', $requestedCinemaId) ?? $cinemas->first();

        $scheduleDates = collect(range(0, 6))->map(function (int $offset) use ($today) {
            $date = $today->copy()->addDays($offset);

            return [
                'date' => $date->toDateString(),
                'day' => $date->format('d'),
                'label' => $offset === 0 ? 'Hôm nay' : $this->vietnameseWeekday($date),
            ];
        });

        $scheduleShowtimes = collect();
        $scheduleMovies = collect();
        $showtimeDates = collect();

        if ($selectedCinema) {
            $scheduleShowtimes = Showtime::with(['movie.genres', 'cinema', 'room'])
                ->where('status', 'active')
                ->where('cinema_id', $selectedCinema->id)
                ->whereDate('show_date', $selectedDate)
                ->whereHas('movie')
                ->orderBy('show_time')
                ->get();

            $showtimeDates = Showtime::query()
                ->where('status', 'active')
                ->where('cinema_id', $selectedCinema->id)
                ->whereBetween('show_date', [$today->toDateString(), $endDate->toDateString()])
                ->orderBy('show_date')
                ->pluck('show_date')
                ->map(fn ($showDate) => Carbon::parse($showDate)->toDateString())
                ->unique()
                ->values();

            $scheduleMovies = $scheduleShowtimes
                ->groupBy('movie_id')
                ->map(function ($movieShowtimes) {
                    return [
                        'movie' => $movieShowtimes->first()->movie,
                        'showtimes' => $movieShowtimes->values(),
                    ];
                })
                ->values();
        }

        $quickShowtimes = Showtime::with(['movie.genres', 'cinema', 'room'])
            ->where('status', 'active')
            ->whereDate('show_date', '>=', $today->toDateString())
            ->orderBy('show_date')
            ->orderBy('show_time')
            ->limit(10)
            ->get();

        return view('user.home', compact(
            'nowShowing',
            'comingSoon',
            'quickShowtimes',
            'cinemas',
            'scheduleDates',
            'selectedCinema',
            'selectedDate',
            'scheduleMovies',
            'showtimeDates',
            'cityOptions',
            'brandTabs',
            'selectedCity',
            'selectedBrand'
        ));
    }

    private function cityOptions(): array
    {
        return [
            'Hà Nội' => ['Hà Nội', 'Ha Noi', 'Hanoi'],
            'TP. Hồ Chí Minh' => ['TP. Hồ Chí Minh', 'Hồ Chí Minh', 'Ho Chi Minh City', 'HCMC', 'Sài Gòn', 'Sai Gon'],
            'Đà Nẵng' => ['Đà Nẵng', 'Da Nang', 'Danang'],
        ];
    }

    private function normalizeSelectedCity(mixed $city, array $allowedCities): ?string
    {
        return is_string($city) && in_array($city, $allowedCities, true) ? $city : null;
    }

    private function normalizeSelectedBrand(mixed $brand, array $allowedBrands): ?string
    {
        if (! is_string($brand) || $brand === '' || $brand === 'Tất cả') {
            return null;
        }

        return in_array($brand, $allowedBrands, true) ? $brand : null;
    }

    private function normalizeSelectedDate(mixed $date, Carbon $fallback): string
    {
        if (! is_string($date) || $date === '') {
            return $fallback->toDateString();
        }

        try {
            $parsedDate = Carbon::createFromFormat('Y-m-d', $date, $fallback->timezone);
        } catch (\Throwable) {
            return $fallback->toDateString();
        }

        return $parsedDate && $parsedDate->format('Y-m-d') === $date
            ? $parsedDate->toDateString()
            : $fallback->toDateString();
    }

    private function vietnameseWeekday(Carbon $date): string
    {
        return match ((int) $date->dayOfWeekIso) {
            1 => 'Thứ 2',
            2 => 'Thứ 3',
            3 => 'Thứ 4',
            4 => 'Thứ 5',
            5 => 'Thứ 6',
            6 => 'Thứ 7',
            default => 'Chủ nhật',
        };
    }
}
