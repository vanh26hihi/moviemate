<?php
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

    $requestedCinemaId = $request->integer('cinema_id');
    $selectedCinema = $cinemas->firstWhere('id', $requestedCinemaId) ?? $cinemas->first();

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

$cinemas = $isNearby
    ? $this->sortByDistance($cinemas, $userLat, $userLng)
    : $cinemas->map(function (Cinema $cinema) {
        $cinema->distance = null;

        return $cinema;
    })->values();

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