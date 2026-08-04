@php
    $movie = $showtime->movie;
    $showDate = $showtime->show_date ? \Carbon\Carbon::parse($showtime->show_date)->format('d/m') : '--/--';
    $showTime = $showtime->show_time ? \Carbon\Carbon::parse($showtime->show_time)->format('H:i') : '--:--';
    $bookingUrl = route('user.bookings.selectSeat', $showtime);
    $detailUrl = $movie?->slug ? route('user.movies.show', $movie->slug) : ($movie?->id ? url('/movies/'.$movie->id) : route('user.movies.index'));
@endphp

<article class="cinema-card group h-full rounded-3xl border app-border p-5 transition-all duration-300 hover:-translate-y-1 hover:border-brand-start/55 hover:shadow-2xl hover:shadow-brand-start/10">
    <div class="flex h-full flex-col gap-4 sm:flex-row">
        <a href="{{ $detailUrl }}" class="w-full shrink-0 sm:w-24 md:w-28">
            <div class="aspect-[2/3] overflow-hidden rounded-2xl bg-gradient-to-br from-slate-800 via-slate-950 to-black shadow-lg shadow-black/15">
                @if($movie?->poster_url)
                    <img src="{{ $movie->poster_url }}" alt="{{ $movie->title }}" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy">
                @else
                    <div class="flex h-full w-full flex-col items-center justify-center px-3 text-center text-white">
                        <div class="mb-2 rounded-full bg-brand-start/20 p-3 text-brand-start">
                            <i class="ph-fill ph-film-slate text-2xl"></i>
                        </div>
                        <p class="text-sm font-black">MovieMate</p>
                        <p class="mt-1 line-clamp-2 text-[11px] text-slate-300">{{ $movie->title ?? 'Phim đang chiếu' }}</p>
                    </div>
                @endif
            </div>
        </a>

        <div class="flex min-w-0 flex-1 flex-col">
            <div class="flex-1">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1 rounded-full border border-brand-start/25 bg-brand-start/10 px-3 py-1 text-xs font-extrabold text-brand-start">
                        <i class="ph-fill ph-clock"></i>{{ $showDate }} • {{ $showTime }}
                    </span>
                    @if($movie?->age_rating)
                        <span class="rounded-full border app-border app-secondary px-2.5 py-1 text-xs font-extrabold app-text">{{ $movie->age_rating }}</span>
                    @endif
                </div>

                <h3 class="line-clamp-2 text-lg font-extrabold app-text">
                    <a href="{{ $detailUrl }}" class="transition-colors hover:text-brand-start">{{ $movie->title ?? 'Phim MovieMate' }}</a>
                </h3>
                <p class="mt-2 line-clamp-2 text-sm app-muted">
                    {{ $showtime->cinema->name ?? 'Rạp MovieMate' }} • {{ $showtime->room->name ?? 'Phòng chiếu' }}
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="inline-flex items-center rounded-xl border app-border app-secondary px-3 py-1.5 text-xs font-bold app-text">
                        {{ number_format((float) ($showtime->price ?? 0), 0, ',', '.') }}đ
                    </span>
                    @if(! empty($showtime->vip_price))
                        <span class="inline-flex items-center rounded-xl border border-ai-start/25 bg-ai-start/10 px-3 py-1.5 text-xs font-bold text-ai-start">
                            VIP {{ number_format((float) $showtime->vip_price, 0, ',', '.') }}đ
                        </span>
                    @endif
                </div>
            </div>

            <div class="mt-5 grid grid-cols-2 gap-2">
                <a href="{{ $detailUrl }}" class="btn-secondary !rounded-2xl !px-3 !py-2.5 text-sm">Chi tiết</a>
                <a href="{{ $bookingUrl }}" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-brand-start to-brand-end px-3 py-2.5 text-sm font-extrabold text-white shadow-lg shadow-brand-start/20 transition-all hover:shadow-brand-start/35">
                    <i class="ph-fill ph-ticket"></i> Đặt vé
                </a>
            </div>
        </div>
    </div>
</article>
