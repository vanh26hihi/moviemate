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
    $cityLabel = $selectedCity ?: 'Tất cả thành phố';
    $brandLabel = $selectedBrand ?: 'Tất cả';

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
        return $showtime
            ? url('/booking/select-seat?showtime_id=' . $showtime->id)
            : url('/booking/select-seat');
    };

    $homeShowtimeUrl = function (array $params = []) {
        return route('home', array_filter($params, fn ($value) => filled($value))) . '#home-showtime-calendar';
    };

    $cinemaBadge = function ($name) {
        $words = preg_split('/\s+/', trim((string) $name));
        $letters = collect($words)->filter()->take(2)->map(fn ($word) => mb_substr($word, 0, 1))->join('');

        return mb_strtoupper($letters ?: 'MM');
    };
@endphp

<section id="home-showtime-calendar" class="showtime-section max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-7">
        <div>
            <p class="text-brand-start text-sm font-extrabold uppercase tracking-[0.22em] mb-2">MovieMate Cinema</p>
            <h2 class="text-3xl sm:text-4xl font-extrabold app-text">Lịch chiếu phim</h2>
            <p class="mt-3 app-muted max-w-2xl">Chọn rạp, ngày chiếu và suất chiếu phù hợp với bạn.</p>
        </div>
    </div>

    <div class="cinema-card rounded-[24px] border app-border shadow-2xl shadow-black/10 overflow-visible">
        <div class="relative z-30 p-4 sm:p-5 lg:p-6 border-b app-border">
            <div class="flex flex-col gap-4">
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
                                <a href="{{ $homeShowtimeUrl(['brand' => $selectedBrand, 'date' => $selectedDate]) }}" class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-bold transition-colors {{ ! $selectedCity ? 'bg-brand-start/10 text-brand-start' : 'app-text hover:bg-brand-start/10 hover:text-brand-start' }}">
                                    Tất cả thành phố
                                    @if(! $selectedCity)<i class="ph-bold ph-check"></i>@endif
                                </a>
                                @foreach($cityOptions->keys() as $city)
                                    <a href="{{ $homeShowtimeUrl(['city' => $city, 'brand' => $selectedBrand, 'date' => $selectedDate]) }}" class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-bold transition-colors {{ $selectedCity === $city ? 'bg-brand-start/10 text-brand-start' : 'app-text hover:bg-brand-start/10 hover:text-brand-start' }}">
                                        {{ $city }}
                                        @if($selectedCity === $city)<i class="ph-bold ph-check"></i>@endif
                                    </a>
                                @endforeach
                            </div>
                        </details>
                        <button type="button" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl bg-brand-start/10 border border-brand-start/25 text-brand-start font-extrabold text-sm hover:bg-brand-start hover:text-white transition-colors">
                            <i class="ph-fill ph-navigation-arrow"></i>
                            Gần bạn
                        </button>
                    </div>

                    <div class="text-xs app-muted">
                        {{ $cinemaList->count() }} rạp phù hợp · {{ $safeDate($selectedDate, 'd/m/Y') }}
                    </div>
                </div>

                <div class="min-w-0">
                    <div class="flex gap-2 overflow-x-auto hide-scrollbar pb-1">
                        @foreach($brandTabs as $tab)
                            @php
                                $brandValue = $tab === 'Tất cả' ? null : $tab;
                                $isActiveBrand = ($tab === 'Tất cả' && ! $selectedBrand) || $selectedBrand === $tab;
                            @endphp
                            <a href="{{ $homeShowtimeUrl(['city' => $selectedCity, 'brand' => $brandValue, 'date' => $selectedDate]) }}" class="shrink-0 px-4 py-2.5 rounded-full border text-sm font-bold transition-all {{ $isActiveBrand ? 'bg-gradient-to-r from-brand-start to-brand-end text-white border-transparent shadow-lg shadow-brand-start/20' : 'app-secondary app-border app-muted hover:text-brand-start hover:border-brand-start' }}">
                                {{ $tab }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="relative z-10 grid grid-cols-1 lg:grid-cols-[32%_68%] min-w-0 overflow-hidden rounded-b-[24px]">
            <aside class="border-b lg:border-b-0 lg:border-r app-border">
                <div class="lg:max-h-[520px] overflow-x-auto lg:overflow-x-hidden lg:overflow-y-auto p-3 sm:p-4 overscroll-x-contain">
                    <div class="flex lg:block gap-3 lg:space-y-3">
                        @forelse($cinemaList as $cinema)
                            @php
                                $isActiveCinema = $selectedCinema && (int) $cinema->id === (int) $selectedCinema->id;
                                $showtimeCount = (int) ($cinema->active_showtimes_count ?? 0);
                            @endphp

                            <a href="{{ $homeShowtimeUrl(['city' => $selectedCity, 'brand' => $selectedBrand, 'cinema_id' => $cinema->id, 'date' => $selectedDate]) }}" class="block min-w-[17rem] max-w-[82vw] lg:max-w-none lg:min-w-0 w-full text-left rounded-3xl border p-4 transition-all duration-200 {{ $isActiveCinema ? 'border-brand-start/60 bg-gradient-to-r from-brand-start/15 to-brand-end/10 shadow-lg shadow-brand-start/10' : 'app-border app-secondary hover:border-brand-start/45 hover:bg-brand-start/5 hover:-translate-y-0.5' }}">
                                <div class="flex items-start gap-3 min-w-0">
                                    <span class="shrink-0 w-12 h-12 rounded-2xl flex items-center justify-center font-black text-sm {{ $isActiveCinema ? 'bg-gradient-to-br from-brand-start to-brand-end text-white' : 'bg-brand-start/10 text-brand-start' }}">
                                        {{ $cinemaBadge($cinema->name ?? 'MovieMate') }}
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block app-text font-extrabold line-clamp-1">{{ $cinema->name ?? 'Rạp MovieMate' }}</span>
                                        <span class="block app-muted text-sm leading-relaxed line-clamp-2 mt-1">{{ $cinema->address ?? 'Địa chỉ đang cập nhật' }}</span>
                                        <span class="inline-flex mt-3 px-3 py-1 rounded-full text-xs font-extrabold {{ $isActiveCinema ? 'bg-brand-start text-white' : 'bg-brand-start/10 text-brand-start' }}">
                                            {{ $showtimeCount }} suất
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

            <div class="min-w-0 p-4 sm:p-5 lg:p-6">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-5">
                    <div>
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <span class="px-3 py-1 rounded-full bg-brand-start/10 text-brand-start text-xs font-extrabold">{{ $brandLabel }}</span>
                            <span class="px-3 py-1 rounded-full app-secondary border app-border app-muted text-xs font-bold">{{ $cityLabel }}</span>
                        </div>
                        <h3 class="text-xl sm:text-2xl font-extrabold app-text">
                            Lịch chiếu phim {{ $selectedCinema?->name ?? 'MovieMate' }}
                        </h3>
                        <p class="app-muted text-sm mt-1">{{ $selectedCinema?->address ?? 'Địa chỉ đang cập nhật' }}</p>
                    </div>
                    <a href="{{ $selectedCinema?->address ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($selectedCinema->address) : '#' }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 text-brand-start hover:text-brand-end font-extrabold text-sm">
                        <i class="ph-fill ph-map-trifold"></i>
                        Bản đồ
                    </a>
                </div>

                <div class="flex gap-2 overflow-x-auto hide-scrollbar pb-3 mb-5 border-b app-border">
                    @forelse($dateList as $date)
                        @php
                            $dateValue = $date['date'] ?? null;
                            $isActiveDate = $dateValue === $selectedDate;
                            $hasShowtimes = $dateValue && $availableDates->contains($dateValue);
                        @endphp

                        <a href="{{ $homeShowtimeUrl(['city' => $selectedCity, 'brand' => $selectedBrand, 'cinema_id' => $selectedCinema?->id, 'date' => $dateValue]) }}" class="shrink-0 min-w-24 px-4 py-3 rounded-2xl border text-center transition-all {{ $isActiveDate ? 'border-transparent bg-gradient-to-br from-brand-start to-brand-end text-white shadow-lg shadow-brand-start/20' : 'app-border app-secondary app-text hover:border-brand-start hover:text-brand-start' }}">
                            <span class="block text-2xl font-black leading-none">{{ $date['day'] ?? $safeDate($dateValue, 'd') }}</span>
                            <span class="block text-xs font-bold mt-1 {{ $isActiveDate ? 'text-white/85' : 'app-muted' }}">{{ $date['label'] ?? $safeDate($dateValue, 'd/m') }}</span>
                            @if($hasShowtimes)
                                <span class="mt-2 mx-auto block w-1.5 h-1.5 rounded-full {{ $isActiveDate ? 'bg-white' : 'bg-brand-start' }}"></span>
                            @endif
                        </a>
                    @empty
                        <div class="app-muted text-sm">Chưa có dữ liệu ngày chiếu.</div>
                    @endforelse
                </div>

                <div class="space-y-4">
                    @forelse($movieRows as $row)
                        @php
                            $movie = $row['movie'] ?? null;
                            $showtimes = collect($row['showtimes'] ?? []);
                            $genres = $movie?->genres?->pluck('name')->take(3)->join(', ') ?: 'Đang cập nhật thể loại';
                        @endphp

                        <article class="rounded-3xl border app-border app-secondary p-4 sm:p-5">
                            <div class="flex gap-4">
                                <div class="w-20 sm:w-24 shrink-0">
                                    <div class="aspect-[2/3] rounded-2xl overflow-hidden bg-gradient-to-br from-brand-start to-brand-end shadow-lg shadow-black/10">
                                        @if($movie?->poster)
                                            <img src="{{ asset('storage/' . $movie->poster) }}" alt="{{ $movie->title ?? 'Poster phim' }}" class="w-full h-full object-cover">
                                        @else
                                            <div class="w-full h-full flex flex-col items-center justify-center text-white text-center p-3">
                                                <i class="ph-fill ph-film-slate text-3xl mb-2"></i>
                                                <span class="text-xs font-black leading-tight">{{ $movie->title ?? 'MovieMate' }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2 mb-2">
                                        <span class="px-2.5 py-1 rounded-lg bg-brand-start text-white text-xs font-black">{{ $movie->age_rating ?? 'P' }}</span>
                                        <h4 class="app-text text-lg font-extrabold">{{ $movie->title ?? 'Phim MovieMate' }}</h4>
                                    </div>
                                    <p class="app-muted text-sm">{{ $genres }}</p>
                                    <p class="mt-3 inline-flex items-center gap-2 text-sm font-bold app-text">
                                        <i class="ph-fill ph-closed-captioning text-brand-start"></i>
                                        2D Phụ đề
                                    </p>

                                    <div class="mt-4 flex flex-wrap gap-2">
                                        @foreach($showtimes as $showtime)
                                            @php
                                                $pastShowtime = $isPastShowtime($showtime);
                                                $timeText = $showtimeRange($showtime, $movie);
                                            @endphp

                                            @if($pastShowtime)
                                                <span class="inline-flex flex-col items-center justify-center w-32 min-h-14 px-3 py-2.5 rounded-xl border app-border app-muted text-sm font-extrabold opacity-55 cursor-not-allowed">
                                                    <span>{{ $timeText }}</span>
                                                    <span class="text-[10px] font-bold">Đã qua</span>
                                                </span>
                                            @else
                                                <a href="{{ $bookingUrl($showtime) }}" class="inline-flex flex-col items-center justify-center w-32 min-h-14 px-3 py-2.5 rounded-xl border border-emerald-400/40 bg-emerald-500/10 text-emerald-500 font-extrabold text-sm hover:border-brand-start hover:bg-gradient-to-r hover:from-brand-start hover:to-brand-end hover:text-white hover:shadow-lg hover:shadow-brand-start/20 hover:-translate-y-0.5 transition-all">
                                                    <span>{{ $timeText }}</span>
                                                    <span class="text-[10px] font-bold opacity-75">Chọn ghế</span>
                                                </a>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-3xl border app-border app-secondary p-8 sm:p-10 text-center">
                            <div class="w-16 h-16 mx-auto rounded-2xl bg-brand-start/10 text-brand-start flex items-center justify-center mb-4">
                                <i class="ph-fill ph-calendar-x text-4xl"></i>
                            </div>
                            <h4 class="text-xl font-extrabold app-text">Chưa có suất chiếu phù hợp</h4>
                            <p class="app-muted mt-2 max-w-md mx-auto">
                                Hãy thử đổi rạp, ngày chiếu, thành phố hoặc cụm rạp để tìm lịch chiếu khác.
                            </p>
                            <a href="{{ route('user.movies.index', ['status' => 'now_showing']) }}" class="btn-primary mt-5">
                                <i class="ph-fill ph-film-strip"></i>
                                Xem phim đang chiếu
                            </a>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</section>
