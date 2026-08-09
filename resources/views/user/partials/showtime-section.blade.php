@php
    
    $cityOptions = collect($cityOptions ?? [
        'Hà Nội' => [],
        'TP. Hồ Chí Minh' => [],
        'Đà Nẵng' => [],
    ]);
    $brandTabs = collect($brandTabs ?? ['Tất cả', 'MovieMate', 'CGV', 'Lotte', 'Galaxy', 'BHD', 'Beta', 'Cinestar']);
    $cinemaList = collect($cinemas ?? []);
    $dateList = collect($scheduleDates ?? []);
    $movieRows = collect($scheduleMovies ?? []);
    $availableDates = collect($showtimeDates ?? []);
    $selectedCinema = $selectedCinema ?? $cinemaList->first();
    $selectedDate = $selectedDate ?? now('Asia/Ho_Chi_Minh')->toDateString();
    $selectedCity = $selectedCity ?? null;
    $selectedBrand = $selectedBrand ?? null;
    $isNearby = (bool) ($isNearby ?? false);
    $userLat = $userLat ?? null;
    $userLng = $userLng ?? null;
    $cityLabel = $selectedCity ?: 'Tất cả thành phố';
    $brandLabel = $selectedBrand ?: 'Tất cả';
    $nearbyParams = $isNearby ? ['nearby' => 1, 'lat' => $userLat, 'lng' => $userLng] : [];
    $showtimeAjaxRoute = $showtimeAjaxRoute ?? 'ajax.showtimes';
    $showtimeBaseRoute = $showtimeBaseRoute ?? 'home';
    $nearestCinemaId = $isNearby ? optional($cinemaList->first(fn ($cinema) => ! is_null($cinema->distance ?? null)))->id : null;
    $directionUrl = null;

    if ($selectedCinema && ! is_null($selectedCinema->latitude) && ! is_null($selectedCinema->longitude)) {
        $directionUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . $selectedCinema->latitude . ',' . $selectedCinema->longitude;
    } elseif ($selectedCinema && filled($selectedCinema->address)) {
        $directionQuery = trim($selectedCinema->address . ', ' . ($selectedCinema->city ?? ''));
        $directionUrl = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($directionQuery);
    }

    $safeDate = function ($value, string $format = 'd/m') {
        if (blank($value)) {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($value)->format($format);
        } catch (\Throwable) {
            return '';
        }
    };

    $safeTime = function ($value) {
        if (blank($value)) {
            return '--:--';
        }

        try {
            return \Carbon\Carbon::parse($value)->format('H:i');
        } catch (\Throwable) {
            $time = (string) $value;

            return preg_match('/^\d{2}:\d{2}/', $time) ? substr($time, 0, 5) : '--:--';
        }
    };

    $showtimeRange = function ($showtime, $movie) use ($selectedDate, $safeTime) {
        $start = $safeTime($showtime->show_time ?? null);
        $duration = (int) ($movie?->duration ?? 0);

        if ($start === '--:--' || $duration <= 0) {
            return $start;
        }

        try {
            $date = $showtime->show_date ?? $selectedDate;
            $dateText = \Carbon\Carbon::parse($date)->toDateString();
            $startAt = \Carbon\Carbon::parse($dateText.' '.$start, 'Asia/Ho_Chi_Minh');

            return $start.' ~ '.$startAt->copy()->addMinutes($duration)->format('H:i');
        } catch (\Throwable) {
            return $start;
        }
    };

    $isPastShowtime = function ($showtime) use ($selectedDate) {
        if (blank($showtime?->show_time)) {
            return false;
        }

        try {
            $date = $showtime->show_date ?? $selectedDate;
            $dateText = \Carbon\Carbon::parse($date)->toDateString();
            $startAt = \Carbon\Carbon::parse($dateText.' '.$showtime->show_time, 'Asia/Ho_Chi_Minh');

            return $startAt->lessThanOrEqualTo(now('Asia/Ho_Chi_Minh'));
        } catch (\Throwable) {
            return false;
        }
    };

    $bookingUrl = function ($showtime) {
        if ($showtime && \Illuminate\Support\Facades\Route::has('user.bookings.selectSeat')) {
            return route('user.bookings.selectSeat', $showtime);
        }

        return url('/booking/select-seat');
    };

    $homeShowtimeUrl = function (array $params = []) use ($nearbyParams, $showtimeBaseRoute) {
        return route($showtimeBaseRoute, array_filter(array_merge($nearbyParams, $params), fn ($value) => filled($value))) . '#home-showtime-calendar';
    };

    $cinemaBadge = function ($name) {
        $words = preg_split('/\s+/', trim((string) $name));
        $letters = collect($words)->filter()->take(2)->map(fn ($word) => mb_substr($word, 0, 1))->join('');

        return mb_strtoupper($letters ?: 'MM');
    };

    $cinemas = collect($cinemas ?? []);
    $entries = collect($scheduleShowtimes ?? []);
    $entriesByDate = $entries->groupBy('date');
    $selectedDate = $selectedDate ?? now(config('cinema.timezone', 'Asia/Ho_Chi_Minh'))->toDateString();
@endphp
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
        <span class="text-sm font-bold app-muted">Vị trí</span>
        <details class="relative group w-full sm:w-auto">
            <summary class="list-none cursor-pointer inline-flex items-center justify-between gap-3 w-full sm:w-auto px-4 py-2.5 rounded-2xl app-secondary border app-border app-text font-bold text-sm hover:border-brand-start transition-colors">
                <span class="inline-flex items-center gap-2">
                    <i class="ph-fill ph-map-pin text-brand-start"></i>
                    {{ $cityLabel }}
                </span>
                <i class="ph ph-caret-down app-muted transition-transform group-open:rotate-180"></i>
            </summary>
            <div class="absolute left-0 top-full mt-2 z-40 w-full sm:w-72 max-w-[calc(100vw-2rem)] rounded-2xl border app-border cinema-card p-2 shadow-2xl">
                <a data-showtime-filter href="{{ $homeShowtimeUrl(['brand' => $selectedBrand, 'date' => $selectedDate]) }}" class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-bold transition-colors {{ ! $selectedCity ? 'bg-brand-start/10 text-brand-start' : 'app-text hover:bg-brand-start/10 hover:text-brand-start' }}">
                    Tất cả thành phố
                    @if(! $selectedCity)<i class="ph-bold ph-check"></i>@endif
                </a>
                @foreach($cityOptions->keys() as $city)
                    <a data-showtime-filter href="{{ $homeShowtimeUrl(['city' => $city, 'brand' => $selectedBrand, 'date' => $selectedDate]) }}" class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-bold transition-colors {{ $selectedCity === $city ? 'bg-brand-start/10 text-brand-start' : 'app-text hover:bg-brand-start/10 hover:text-brand-start' }}">
                        {{ $city }}
                        @if($selectedCity === $city)<i class="ph-bold ph-check"></i>@endif
                    </a>
                @endforeach
            </div>
        </details>
        <button type="button" id="nearbyCinemaBtn" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl border font-extrabold text-sm transition-colors {{ $isNearby ? 'bg-gradient-to-r from-brand-start to-brand-end border-transparent text-white shadow-lg shadow-brand-start/20' : 'bg-brand-start/10 border-brand-start/25 text-brand-start hover:bg-brand-start hover:text-white' }}">
            <i class="ph-fill ph-navigation-arrow"></i>
            <span data-nearby-label>Gần bạn</span>
        </button>
    </div>

    <div class="text-xs app-muted">
        {{ $cinemaList->count() }} rạp phù hợp · {{ $safeDate($selectedDate, 'd/m/Y') }}
    </div>
</div>
<section id="home-showtime-calendar" data-showtime-calendar data-selected-date="{{ $selectedDate }}" class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="cinema-card overflow-hidden rounded-[24px] border app-border shadow-2xl shadow-black/10">
        <header class="border-b app-border p-5 lg:p-6">
            <p class="text-sm font-extrabold uppercase tracking-[0.22em] text-brand-start">Lịch chiếu MovieMate</p>
            <h2 class="mt-2 text-2xl font-extrabold app-text sm:text-3xl">Chọn chi nhánh, ngày và suất chiếu phù hợp</h2>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <form method="GET" action="{{ route('home') }}"><label class="sr-only" for="homeCinemaSelect">Chi nhánh</label><select id="homeCinemaSelect" name="cinema" class="cinema-input" onchange="this.form.submit()"><option value="">Tất cả chi nhánh</option>@foreach($cinemas as $branch)<option value="{{ $branch->code }}" data-latitude="{{ $branch->latitude }}" data-longitude="{{ $branch->longitude }}" @selected($cinema?->is($branch))>{{ $branch->name }}</option>@endforeach</select></form>
                <button id="nearbyCinemaBtn" type="button" class="btn-secondary" data-nearby-target="homeCinemaSelect"><i class="ph-fill ph-navigation-arrow" aria-hidden="true"></i> Gần bạn</button>
                <p id="nearbyCinemaStatus" class="text-sm app-muted" role="status" aria-live="polite">Vị trí chỉ được yêu cầu sau khi bạn bấm nút.</p>
            </div>
        </header>
        <div class="p-5 lg:p-6">
            <div data-showtime-date-strip class="flex gap-2 overflow-x-auto pb-4">
                @foreach($scheduleDates as $date)
                    @php($active = $selectedDate === $date['date'])
                    <button type="button" id="showtime-date-{{ $date['date'] }}" data-showtime-date="{{ $date['date'] }}" aria-controls="{{ $entriesByDate->has($date['date']) ? 'showtime-panel-'.$date['date'] : 'showtime-empty-panel' }}" aria-pressed="{{ $active ? 'true' : 'false' }}" class="shrink-0 min-w-20 rounded-2xl border px-4 py-3 text-center {{ $active ? 'border-transparent bg-gradient-to-br from-brand-start to-brand-end text-white' : 'app-border app-secondary app-text' }}">
                        <span class="block text-lg font-black">{{ $date['day'] }}</span><span class="block text-xs font-bold">{{ $date['label'] }}</span>
                    </button>
                @endforeach
            </div>
            <div class="min-w-0">
    <div class="flex gap-2 overflow-x-auto hide-scrollbar pb-1">
        @foreach($brandTabs as $tab)
            @php
                $isAllBrandTab = in_array($tab, ['Tất cả', 'Tat ca'], true);
                $brandValue = $isAllBrandTab ? null : $tab;
                $isActiveBrand = ($isAllBrandTab && ! $selectedBrand) || $selectedBrand === $tab;
            @endphp
            <a data-showtime-filter href="{{ $homeShowtimeUrl(['city' => $selectedCity, 'brand' => $brandValue, 'date' => $selectedDate]) }}" class="shrink-0 px-4 py-2.5 rounded-full border text-sm font-bold transition-all {{ $isActiveBrand ? 'bg-gradient-to-r from-brand-start to-brand-end text-white border-transparent shadow-lg shadow-brand-start/20' : 'app-secondary app-border app-muted hover:text-brand-start hover:border-brand-start' }}">
                {{ $tab }}
            </a>
        @endforeach
    </div>
    <div class="relative z-10 grid grid-cols-1 lg:grid-cols-[32%_68%] min-w-0 overflow-hidden rounded-b-[24px]">
    <aside class="border-b lg:border-b-0 lg:border-r app-border">
        <div class="lg:max-h-[520px] overflow-x-auto lg:overflow-x-hidden lg:overflow-y-auto p-3 sm:p-4 overscroll-x-contain">
            <div class="flex lg:block gap-3 lg:space-y-3">
                @forelse($cinemaList as $cinema)
                    @php
                        $isActiveCinema = $selectedCinema && (int) $cinema->id === (int) $selectedCinema->id;
                        $showtimeCount = (int) ($cinema->active_showtimes_count ?? 0);
                        $distance = $cinema->distance ?? null;
                        $isNearestCinema = $nearestCinemaId && (int) $cinema->id === (int) $nearestCinemaId;
                    @endphp

                    <a data-showtime-filter href="{{ $homeShowtimeUrl(['city' => $selectedCity, 'brand' => $selectedBrand, 'cinema_id' => $cinema->id, 'date' => $selectedDate]) }}" class="block min-w-[17rem] max-w-[82vw] lg:max-w-none lg:min-w-0 w-full text-left rounded-3xl border p-4 transition-all duration-200 {{ $isActiveCinema ? 'border-brand-start/60 bg-gradient-to-r from-brand-start/15 to-brand-end/10 shadow-lg shadow-brand-start/10' : 'app-border app-secondary hover:border-brand-start/45 hover:bg-brand-start/5 hover:-translate-y-0.5' }}">
                        <div class="flex items-start gap-3 min-w-0">
                            <span class="shrink-0 w-12 h-12 rounded-2xl flex items-center justify-center font-black text-sm {{ $isActiveCinema ? 'bg-gradient-to-br from-brand-start to-brand-end text-white' : 'bg-brand-start/10 text-brand-start' }}">
                                {{ $cinemaBadge($cinema->name ?? 'MovieMate') }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block app-text font-extrabold line-clamp-1">{{ $cinema->name ?? 'Rạp MovieMate' }}</span>
                                <span class="block app-muted text-sm leading-relaxed line-clamp-2 mt-1">{{ $cinema->address ?? 'Địa chỉ đang cập nhật' }}</span>
                                <span class="mt-3 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex px-3 py-1 rounded-full text-xs font-extrabold {{ $isActiveCinema ? 'bg-brand-start text-white' : 'bg-brand-start/10 text-brand-start' }}">
                                        {{ $showtimeCount }} suất
                                    </span>
                                    @if($isNearby && ! is_null($distance))
                                        <span class="inline-flex px-3 py-1 rounded-full text-xs font-extrabold app-secondary border app-border app-text">
                                            {{ number_format($distance, 1) }} km
                                        </span>
                                    @endif
                                    @if($isNearestCinema)
                                        <span class="inline-flex px-3 py-1 rounded-full text-xs font-extrabold bg-emerald-500/10 text-emerald-500 border border-emerald-500/20">
                                            Gần nhất
                                        </span>
                                    @endif
                                </span>
                            </span>
                            <i class="ph ph-caret-right app-muted mt-3"></i>
                        </div>
                    </a>
                @empty
                    <div class="min-w-[17rem] max-w-[82vw] lg:max-w-none lg:min-w-0 rounded-3xl border app-border app-secondary p-6 text-center">
                        <div class="w-12 h-12 mx-auto rounded-2xl bg-brand-start/10 text-brand-start flex items-center justify-center mb-3">
                            <i class="ph-fill ph-film-strip text-2xl"></i>
                        </div>
                        <p class="app-text font-extrabold">Không tìm thấy rạp</p>
                        <p class="app-muted text-sm mt-1">Hãy thử đổi thành phố hoặc cụm rạp.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </aside>
</div>
            <div class="mt-5">
                @foreach($scheduleDates as $date)
                    @php($dateEntries = collect($entriesByDate->get($date['date'], [])))
                    @if($dateEntries->isNotEmpty())
                        <div data-showtime-panel="{{ $date['date'] }}" id="showtime-panel-{{ $date['date'] }}" class="space-y-7" @if($selectedDate !== $date['date']) hidden @endif>
                            @foreach($dateEntries->groupBy(fn ($item) => $item['cinema']->id) as $branchEntries)
                                @php($branch = $branchEntries->first()['cinema'])
                                <section class="space-y-4"><div><h3 class="text-xl font-extrabold app-text">{{ $branch->name }}</h3><p class="text-sm app-muted">{{ $branch->address }}</p></div>
                                @foreach($branchEntries->groupBy(fn ($item) => $item['movie']->id) as $movieEntries)<x-customer.showtimes.movie-card :entries="$movieEntries" />@endforeach</section>
                            @endforeach
                        </div>
                    @endif
                @endforeach
                <div data-showtime-empty-panel data-showtime-empty id="showtime-empty-panel" class="rounded-3xl border app-border app-secondary p-8 text-center" @if($entriesByDate->has($selectedDate)) hidden @endif><i class="ph-fill ph-calendar-x text-4xl text-brand-start" aria-hidden="true"></i><h3 class="mt-3 font-bold app-text">Chưa có suất chiếu trong ngày</h3></div>
            </div>
        </div>
    </div>
</section>
