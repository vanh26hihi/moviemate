<?php

namespace App\Services;

use App\Models\Cinema;
use App\Models\Showtime;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ShowtimeCalendarService
{
    public function data(Request $request): array
    {
        $today = Carbon::today('Asia/Ho_Chi_Minh');
        $now = now('Asia/Ho_Chi_Minh');
        $endDate = $today->copy()->addDays(6);
        $selectedDate = $this->normalizeSelectedDate(
            $request->query('date'),
            $this->defaultSelectedDate($today, $endDate, $now)
        );
        $cityOptions = $this->cityOptions();
        $brandTabs = ['Tất cả', 'MovieMate', 'CGV', 'Lotte', 'Galaxy', 'BHD', 'Beta', 'Cinestar'];
        $selectedCity = $this->normalizeSelectedCity($request->query('city'), array_keys($cityOptions));
        $selectedBrand = $this->normalizeSelectedBrand($request->query('brand'), $brandTabs);
        $userLat = $this->normalizeCoordinate($request->query('lat'), -90, 90);
        $userLng = $this->normalizeCoordinate($request->query('lng'), -180, 180);
        $isNearby = $request->boolean('nearby') && ! is_null($userLat) && ! is_null($userLng);

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
                'showtimes as active_showtimes_count' => function ($query) use ($selectedDate, $today, $now) {
                    $query->where('status', 'active')
                        ->whereDate('show_date', $selectedDate)
                        ->when($selectedDate === $today->toDateString(), function ($query) use ($now) {
                            $query->whereTime('show_time', '>=', $now->copy()->subMinutes(30)->format('H:i:s'));
                        });
                },
            ])
            ->orderBy('name')
            ->get();

        $cinemas = $isNearby
            ? $this->sortByDistance($cinemas, $userLat, $userLng)
            : $cinemas->map(function (Cinema $cinema) {
                $cinema->distance = null;

                return $cinema;
            })->values();

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
                ->when($selectedDate === $today->toDateString(), function ($query) use ($now) {
                    $query->whereTime('show_time', '>=', $now->copy()->subMinutes(30)->format('H:i:s'));
                })
                ->whereHas('movie')
                ->orderBy('show_time')
                ->get();

            $showtimeDates = Showtime::query()
                ->where('status', 'active')
                ->where('cinema_id', $selectedCinema->id)
                ->whereBetween('show_date', [$today->toDateString(), $endDate->toDateString()])
                ->where(function ($query) use ($today, $now) {
                    $query->whereDate('show_date', '>', $today->toDateString())
                        ->orWhere(function ($query) use ($today, $now) {
                            $query->whereDate('show_date', $today->toDateString())
                                ->whereTime('show_time', '>=', $now->copy()->subMinutes(30)->format('H:i:s'));
                        });
                })
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

        return compact(
            'cinemas',
            'scheduleDates',
            'selectedCinema',
            'selectedDate',
            'scheduleMovies',
            'showtimeDates',
            'cityOptions',
            'brandTabs',
            'selectedCity',
            'selectedBrand',
            'isNearby',
            'userLat',
            'userLng'
        );
    }

    private function sortByDistance($cinemas, ?float $userLat, ?float $userLng)
    {
        return $cinemas
            ->map(function (Cinema $cinema) use ($userLat, $userLng) {
                $cinema->distance = $this->cinemaHasCoordinates($cinema)
                    ? $this->calculateDistance($userLat, $userLng, (float) $cinema->latitude, (float) $cinema->longitude)
                    : null;

                return $cinema;
            })
            ->sortBy(fn (Cinema $cinema) => is_null($cinema->distance) ? PHP_FLOAT_MAX : $cinema->distance)
            ->values();
    }

    private function normalizeCoordinate(mixed $value, float $min, float $max): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $coordinate = (float) $value;

        return $coordinate >= $min && $coordinate <= $max ? $coordinate : null;
    }

    private function cinemaHasCoordinates(Cinema $cinema): bool
    {
        return ! is_null($cinema->latitude)
            && ! is_null($cinema->longitude)
            && is_numeric($cinema->latitude)
            && is_numeric($cinema->longitude);
    }

    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    private function cityOptions(): array
    {
        return [
            'Hà Nội' => ['Ha Noi', 'Hanoi', 'Hà Nội', 'Hà Nội'],
            'TP. Hồ Chí Minh' => ['TP. Hồ Chí Minh', 'Hồ Chí Minh', 'TP. Ho Chi Minh', 'Ho Chi Minh', 'Ho Chi Minh City', 'HCMC', 'Sai Gon', 'Sài Gòn'],
            'Đà Nẵng' => ['Đà Nẵng', 'Da Nang', 'Danang'],
        ];
    }

    private function normalizeSelectedCity(mixed $city, array $allowedCities): ?string
    {
        return is_string($city) && in_array($city, $allowedCities, true) ? $city : null;
    }

    private function normalizeSelectedBrand(mixed $brand, array $allowedBrands): ?string
    {
        if (! is_string($brand) || $brand === '' || in_array($brand, ['Tất cả', 'Tat ca'], true)) {
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
            $parsedDate = Carbon::createFromFormat('Y-m-d', $date, $fallback->getTimezone());
        } catch (\Throwable) {
            return $fallback->toDateString();
        }

        return $parsedDate && $parsedDate->format('Y-m-d') === $date
            ? $parsedDate->toDateString()
            : $fallback->toDateString();
    }

    private function defaultSelectedDate(Carbon $today, Carbon $endDate, Carbon $now): Carbon
    {
        $firstAvailableDate = Showtime::query()
            ->where('status', 'active')
            ->whereBetween('show_date', [$today->toDateString(), $endDate->toDateString()])
            ->where(function ($query) use ($today, $now) {
                $query->whereDate('show_date', '>', $today->toDateString())
                    ->orWhere(function ($query) use ($today, $now) {
                        $query->whereDate('show_date', $today->toDateString())
                            ->whereTime('show_time', '>=', $now->copy()->subMinutes(30)->format('H:i:s'));
                    });
            })
            ->orderBy('show_date')
            ->value('show_date');

        return $firstAvailableDate
            ? Carbon::parse($firstAvailableDate, $today->getTimezone())
            : $today;
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
