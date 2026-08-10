@php
    $cinemas = collect($cinemas ?? []);
    $entries = collect($scheduleShowtimes ?? []);
    $entriesByDate = $entries->groupBy('date');
    $selectedDate = $selectedDate ?? now(config('cinema.timezone', 'Asia/Ho_Chi_Minh'))->toDateString();
@endphp
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
                    @php
                        $active = $selectedDate === $date['date'];
                    @endphp
                    <button type="button" id="showtime-date-{{ $date['date'] }}" data-showtime-date="{{ $date['date'] }}" aria-controls="{{ $entriesByDate->has($date['date']) ? 'showtime-panel-'.$date['date'] : 'showtime-empty-panel' }}" aria-pressed="{{ $active ? 'true' : 'false' }}" class="shrink-0 min-w-20 rounded-2xl border px-4 py-3 text-center {{ $active ? 'border-transparent bg-gradient-to-br from-brand-start to-brand-end text-white' : 'app-border app-secondary app-text' }}">
                        <span class="block text-lg font-black">{{ $date['day'] }}</span><span class="block text-xs font-bold">{{ $date['label'] }}</span>
                    </button>
                @endforeach
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
