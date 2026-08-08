@props(['entries', 'showCinema' => false])
@php
    $entries = collect($entries);
    $first = $entries->first();
    $movie = $first['movie'];
@endphp
<article class="cinema-card rounded-3xl p-4 sm:p-6">
    <div class="flex gap-4 sm:gap-6">
        <div class="h-32 w-24 shrink-0 overflow-hidden rounded-2xl app-secondary sm:h-40 sm:w-28">
            @if($first['poster'])
                <img src="{{ $first['poster'] }}" alt="Áp phích {{ $movie->title }}" class="h-full w-full object-cover" loading="lazy">
            @else
                <div class="flex h-full flex-col items-center justify-center gap-2 p-2 text-center app-muted"><i class="ph-fill ph-film-slate text-3xl" aria-hidden="true"></i><span class="text-xs font-bold">MovieMate</span></div>
            @endif
        </div>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div><h3 class="text-lg font-extrabold app-text sm:text-xl"><a href="{{ route('user.movies.show', ['slug' => $movie->slug, 'cinema' => $first['cinema']->code, 'date' => $first['date']]) }}" class="hover:text-brand-start">{{ $movie->title }}</a></h3>
                    <p class="mt-1 text-sm app-muted">@if($movie->age_rating)<strong class="app-text">{{ $movie->age_rating }}</strong> · @endif{{ $movie->genres->pluck('name')->join(', ') ?: 'Đang cập nhật thể loại' }}@if($movie->duration) · {{ $movie->duration }} phút @endif</p>
                </div>
                @if($showCinema)<a href="{{ route('cinemas.show', ['cinema' => $first['cinema']->code, 'date' => $first['date']]) }}" class="text-sm font-bold text-brand-start">{{ $first['cinema']->name }}</a>@endif
            </div>
            <div class="mt-4 space-y-4">
                @foreach($entries->groupBy('room_type') as $roomType => $typeEntries)
                    <div><h4 class="mb-2 text-sm font-black uppercase tracking-wider app-text">{{ $roomType }}</h4>
                        <div class="flex flex-wrap gap-2">@foreach($typeEntries as $showtime)<x-customer.showtimes.time-chip :showtime="$showtime" />@endforeach</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</article>
