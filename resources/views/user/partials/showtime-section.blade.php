@php
    $dateList = collect($scheduleDates ?? []);
    $movieRows = collect($scheduleMovies ?? []);
    $availableDates = collect($showtimeDates ?? []);
    $selectedDate = $selectedDate ?? now('Asia/Ho_Chi_Minh')->toDateString();
    $safeTime = fn ($value) => blank($value) ? '--:--' : \Carbon\Carbon::parse($value)->format('H:i');
    $isPast = fn ($showtime) => \Carbon\Carbon::parse(\Carbon\Carbon::parse($showtime->show_date)->toDateString().' '.$showtime->show_time, 'Asia/Ho_Chi_Minh')->isPast();
@endphp

<section id="home-showtime-calendar" class="showtime-section max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="cinema-card rounded-[24px] border app-border shadow-2xl shadow-black/10 overflow-hidden">
        <div class="p-5 lg:p-6 border-b app-border flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div><p class="text-brand-start text-sm font-extrabold uppercase tracking-[0.22em]">Cơ sở duy nhất</p><h2 class="text-2xl sm:text-3xl font-extrabold app-text mt-2">{{ $cinema->name }}</h2><p class="app-muted text-sm mt-2">{{ $cinema->address }}</p></div>
            <a href="{{ $cinema->map_url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 text-brand-start font-bold"><i class="ph-fill ph-map-trifold"></i> Bản đồ</a>
        </div>
        <div class="p-5 lg:p-6">
            <div class="flex gap-2 overflow-x-auto hide-scrollbar pb-4 mb-5 border-b app-border">
                @foreach($dateList as $date)
                    @php($active = ($date['date'] ?? null) === $selectedDate)
                    <a href="{{ route('home', ['date' => $date['date']]).'#home-showtime-calendar' }}" class="shrink-0 min-w-24 px-4 py-3 rounded-2xl border text-center {{ $active ? 'border-transparent bg-gradient-to-br from-brand-start to-brand-end text-white' : 'app-border app-secondary app-text' }}">
                        <span class="block text-2xl font-black">{{ $date['day'] }}</span><span class="block text-xs font-bold mt-1">{{ $date['label'] }}</span>
                        @if($availableDates->contains($date['date']))<span class="mt-2 mx-auto block w-1.5 h-1.5 rounded-full {{ $active ? 'bg-white' : 'bg-brand-start' }}"></span>@endif
                    </a>
                @endforeach
            </div>
            <div class="space-y-4">
                @forelse($movieRows as $row)
                    @php($movie = $row['movie'])
                    <article class="rounded-3xl border app-border app-secondary p-5"><h3 class="text-lg font-extrabold app-text">{{ $movie->title }}</h3><p class="app-muted text-sm mt-1">{{ $movie->genres->pluck('name')->join(', ') }}</p>
                        <div class="mt-4 flex flex-wrap gap-2">@foreach($row['showtimes'] as $showtime)
                            @if($isPast($showtime))<span class="px-4 py-3 rounded-xl border app-border app-muted opacity-60">{{ $safeTime($showtime->show_time) }} · Đã qua</span>
                            @else<a href="{{ route('user.bookings.selectSeat', $showtime) }}" class="px-4 py-3 rounded-xl border border-brand-start/35 text-brand-start font-extrabold hover:bg-brand-start hover:text-white">{{ $safeTime($showtime->show_time) }} · {{ $showtime->room->name }}</a>@endif
                        @endforeach</div>
                    </article>
                @empty
                    <div class="rounded-3xl border app-border app-secondary p-8 text-center"><i class="ph-fill ph-calendar-x text-4xl text-brand-start"></i><h3 class="app-text font-bold mt-3">Chưa có suất chiếu trong ngày</h3></div>
                @endforelse
            </div>
        </div>
    </div>
</section>
