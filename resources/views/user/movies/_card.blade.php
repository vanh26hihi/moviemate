@php
    $releaseDate = $movie->release_date ? \Carbon\Carbon::parse($movie->release_date)->format('d/m/Y') : 'Đang cập nhật';
    $isNowShowing = $movie->status === 'now_showing';
    $genresText = $movie->genres?->pluck('name')->take(2)->join(', ') ?: 'Đang cập nhật';
@endphp

<article class="movie-card group dark-surface border border-white/10 rounded-[1.25rem] overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:border-brand-start/60">
    <a href="{{ route('user.movies.show', $movie->slug) }}" class="block">
        <div class="poster-frame">
            @if($movie->poster_url)
                <img src="{{ $movie->poster_url }}" alt="{{ $movie->title }}" loading="lazy">
            @else
                <div class="fallback-poster">
                    <i class="ph-fill ph-film-slate"></i>
                    <strong class="text-lg">MovieMate</strong>
                    <span>{{ $movie->title ?? 'Phim MovieMate' }}</span>
                </div>
            @endif

            <div class="absolute inset-x-0 top-0 z-10 p-3 flex items-start justify-between gap-2">
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold text-white {{ $isNowShowing ? 'bg-brand-start' : 'bg-ai-start' }}">
                    {{ $isNowShowing ? 'Đang chiếu' : 'Sắp chiếu' }}
                </span>
                @if($movie->age_rating)
                    <span class="rounded-full bg-black/55 px-2.5 py-1 text-[11px] font-bold text-white backdrop-blur">
                        {{ $movie->age_rating }}
                    </span>
                @endif
            </div>

            <div class="absolute inset-x-0 bottom-0 z-10 p-3 bg-gradient-to-t from-black/90 via-black/45 to-transparent opacity-0 group-hover:opacity-100 transition-opacity">
                <div class="grid grid-cols-2 gap-2">
                    <span class="inline-flex items-center justify-center gap-1 rounded-xl bg-white text-slate-950 px-3 py-2 text-xs font-extrabold">
                        <i class="ph-fill ph-star text-brand-start"></i> 8.6
                    </span>
                    <span class="inline-flex items-center justify-center gap-1 rounded-xl bg-brand-start text-white px-3 py-2 text-xs font-extrabold">
                        <i class="ph-fill ph-ticket"></i> Đặt vé
                    </span>
                </div>
            </div>
        </div>
    </a>

    <div class="p-4">
        <h3 class="text-white font-bold text-sm sm:text-base line-clamp-2 min-h-[2.75rem]">
            <a href="{{ route('user.movies.show', $movie->slug) }}" class="hover:text-brand-start transition-colors">
                {{ $movie->title ?? 'Chưa có tên phim' }}
            </a>
        </h3>

        <p class="mt-2 text-xs text-slate-300 line-clamp-1">{{ $genresText }}</p>

        <div class="mt-3 grid grid-cols-2 gap-2 text-slate-300 text-xs">
            <span class="inline-flex items-center gap-1"><i class="ph ph-clock"></i>{{ $movie->duration ?? '--' }} phút</span>
            <span class="inline-flex items-center gap-1 justify-end"><i class="ph ph-calendar-blank"></i>{{ $releaseDate }}</span>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-2">
            <a href="{{ route('user.movies.show', $movie->slug) }}" class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 px-3 py-2 text-xs font-bold text-white transition-colors hover:bg-white/10 hover:border-white/20">
                Chi tiết
            </a>
            <a href="{{ route('user.movies.show', $movie->slug) }}#showtimes" class="inline-flex items-center justify-center gap-2 rounded-xl primary-gradient px-3 py-2 text-xs font-bold text-white transition-all hover:shadow-lg hover:shadow-brand-start/25">
                Đặt vé
            </a>
        </div>
    </div>
</article>
