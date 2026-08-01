@extends('layouts.app')

@section('title', 'Trang chủ - MovieMate')

@php
    $featuredMovie = $nowShowingMovies->first() ?? $comingSoonMovies->first();
    $featuredGenres = $featuredMovie?->genres?->pluck('name')->take(3)->join(', ') ?: 'Điện ảnh';
@endphp

@section('content')
<section class="cinema-surface relative overflow-hidden">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-10 pb-12 md:pt-16 md:pb-16">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-center">
            <div class="lg:col-span-7">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-brand-start/30 bg-brand-start/10 text-brand-start text-sm font-bold mb-5">
                    <i class="ph-fill ph-film-strip"></i>
                    Rạp phim trực tuyến tích hợp AI
                </div>

                <h1 class="hero-title text-4xl sm:text-5xl lg:text-6xl font-extrabold app-text max-w-4xl">
                    Chọn phim hay, đặt ghế nhanh, vào rạp bằng vé QR.
                </h1>
                <p class="mt-5 text-base sm:text-lg app-muted leading-relaxed max-w-2xl">
                    MovieMate kết hợp lịch chiếu rõ ràng, chọn ghế trực quan và AI gợi ý phim để mỗi buổi xem đều dễ quyết định hơn.
                </p>

                @if($featuredMovie)
                    <div class="mt-7 cinema-card p-4 sm:p-5 max-w-2xl">
                        <p class="text-xs uppercase tracking-[0.22em] text-brand-start font-extrabold mb-2">Phim nổi bật</p>
                        <h2 class="text-2xl sm:text-3xl font-extrabold app-text">{{ $featuredMovie->title }}</h2>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                            <span class="px-3 py-1.5 rounded-full app-secondary border app-border app-text">{{ $featuredGenres }}</span>
                            <span class="px-3 py-1.5 rounded-full app-secondary border app-border app-text">{{ $featuredMovie->duration ?? '--' }} phút</span>
                            <span class="px-3 py-1.5 rounded-full bg-brand-start/10 border border-brand-start/30 text-brand-start font-bold">{{ $featuredMovie->age_rating ?? 'P' }}</span>
                            <span class="px-3 py-1.5 rounded-full app-secondary border app-border app-text"><i class="ph-fill ph-star text-brand-start"></i> 8.6</span>
                        </div>
                        <p class="mt-4 app-muted line-clamp-2">{{ $featuredMovie->description ?? 'Thông tin phim đang được cập nhật.' }}</p>
                    </div>
                @endif

                <div class="mt-8 flex flex-col sm:flex-row gap-3">
                    <a href="{{ $featuredMovie ? route('user.movies.show', $featuredMovie->slug) . '#showtimes' : route('user.movies.index') }}" class="btn-primary">
                        <i class="ph-fill ph-ticket"></i>
                        Đặt vé ngay
                    </a>
                    @if($featuredMovie)
                        <a href="{{ route('user.movies.show', $featuredMovie->slug) }}" class="btn-secondary">
                            <i class="ph ph-info"></i>
                            Xem chi tiết
                        </a>
                    @endif
                    <a href="{{ route('foods.index') }}" class="btn-secondary hover:!border-brand-start hover:!text-brand-start">
                        <i class="ph-fill ph-burger"></i>
                        Đặt đồ ăn
                    </a>
                    <a href="{{ route('user.ai.recommend') }}" class="btn-secondary hover:!border-ai-start hover:!text-ai-start">
                        <i class="ph-fill ph-sparkle"></i>
                        AI gợi ý phim
                    </a>
                </div>
            </div>

            <div class="lg:col-span-5">
                <div class="relative max-w-sm mx-auto lg:max-w-md">
                    <div class="absolute -inset-5 rounded-[2rem] bg-gradient-to-br from-brand-start/30 via-ai-start/20 to-brand-end/20 blur-2xl"></div>
                    <div class="relative cinema-card p-4">
                        <div class="poster-frame rounded-2xl shadow-2xl shadow-black/30">
                            @if($featuredMovie?->poster_url)
                                <img src="{{ $featuredMovie->poster_url }}" alt="{{ $featuredMovie->title }}" loading="lazy">
                            @else
                                <div class="fallback-poster">
                                    <i class="ph-fill ph-film-slate"></i>
                                    <strong class="text-2xl">MovieMate</strong>
                                    <span>Poster phim sẽ hiển thị tại đây</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <form action="{{ route('user.ai.recommend.submit') }}" method="POST" class="mt-10 cinema-card p-3 sm:p-4 grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-3">
            @csrf
            <input type="hidden" name="mood" value="chill">
            <input type="hidden" name="preferred_time" value="tonight">
            <input type="hidden" name="companion" value="friends">
            <label class="flex items-center gap-3 px-3 sm:px-4 py-2 app-input border app-border rounded-2xl">
                <i class="ph-fill ph-sparkle text-ai-start text-xl"></i>
                <span class="hidden md:inline app-text font-bold whitespace-nowrap">Bạn muốn xem phim gì hôm nay?</span>
                <input name="location" type="text" class="w-full bg-transparent app-text placeholder:text-text-sub/70 focus:outline-none py-2" placeholder="Ví dụ: Tôi thích phim hành động, muốn xem tối nay ở Hà Nội...">
            </label>
            <button class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-2xl bg-gradient-to-r from-ai-start to-ai-end text-white font-extrabold">
                <i class="ph-fill ph-magic-wand"></i>
                Gợi ý bằng AI
            </button>
        </form>
    </div>
</section>

<section id="showtimes" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4 mb-7">
        <div>
            <p class="text-brand-start text-sm font-extrabold uppercase tracking-[0.22em] mb-2">Lịch chiếu nhanh</p>
            <h2 class="text-3xl sm:text-4xl font-extrabold app-text">Suất chiếu gần nhất</h2>
            <p class="mt-2 app-muted max-w-2xl">Chọn nhanh suất chiếu phù hợp với bạn.</p>
        </div>
        <a href="{{ route('user.movies.index', ['status' => 'now_showing']) }}" class="btn-secondary !py-2.5 w-fit">
            Xem tất cả phim <i class="ph ph-arrow-right"></i>
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-5">
        @forelse(($latestShowtimes ?? collect())->take(6) as $showtime)
            @php
                $movie = $showtime->movie;
                $showDate = $showtime->show_date ? \Carbon\Carbon::parse($showtime->show_date)->format('d/m') : '--/--';
                $showTime = $showtime->show_time ? \Carbon\Carbon::parse($showtime->show_time)->format('H:i') : '--:--';
                $bookingUrl = route('user.bookings.selectSeat', $showtime);
                $detailUrl = $movie?->slug ? route('user.movies.show', $movie->slug) : ($movie?->id ? url('/movies/'.$movie->id) : route('user.movies.index'));
            @endphp

            <article class="cinema-card group h-full p-5 rounded-3xl border app-border transition-all duration-300 hover:-translate-y-1 hover:border-brand-start/55 hover:shadow-2xl hover:shadow-brand-start/10">
                <div class="flex h-full flex-col sm:flex-row gap-4">
                    <a href="{{ $detailUrl }}" class="w-full sm:w-24 md:w-28 shrink-0">
                        <div class="aspect-[2/3] rounded-2xl overflow-hidden bg-gradient-to-br from-slate-800 via-slate-950 to-black shadow-lg shadow-black/15">
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

                    <div class="min-w-0 flex flex-1 flex-col">
                        <div class="flex-1">
                            <div class="mb-3 flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center gap-1 rounded-full bg-brand-start/10 border border-brand-start/25 px-3 py-1 text-xs font-extrabold text-brand-start">
                                    <i class="ph-fill ph-clock"></i>{{ $showDate }} • {{ $showTime }}
                                </span>
                                @if($movie?->age_rating)
                                    <span class="rounded-full app-secondary border app-border px-2.5 py-1 text-xs font-extrabold app-text">{{ $movie->age_rating }}</span>
                                @endif
                            </div>

                            <h3 class="text-lg font-extrabold app-text line-clamp-2">
                                <a href="{{ $detailUrl }}" class="hover:text-brand-start transition-colors">{{ $movie->title ?? 'Phim MovieMate' }}</a>
                            </h3>
                            <p class="mt-2 text-sm app-muted line-clamp-2">
                                {{ $showtime->cinema->name ?? 'Rạp MovieMate' }} • {{ $showtime->room->name ?? 'Phòng chiếu' }}
                            </p>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <span class="inline-flex items-center rounded-xl app-secondary border app-border px-3 py-1.5 text-xs font-bold app-text">
                                    {{ number_format((float) ($showtime->price ?? 0), 0, ',', '.') }}đ
                                </span>
                                @if(! empty($showtime->vip_price))
                                    <span class="inline-flex items-center rounded-xl bg-ai-start/10 border border-ai-start/25 px-3 py-1.5 text-xs font-bold text-ai-start">
                                        VIP {{ number_format((float) $showtime->vip_price, 0, ',', '.') }}đ
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="mt-5 grid grid-cols-2 gap-2">
                            <a href="{{ $detailUrl }}" class="btn-secondary !rounded-2xl !px-3 !py-2.5 text-sm">
                                Chi tiết
                            </a>
                            <a href="{{ $bookingUrl }}" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-brand-start to-brand-end px-3 py-2.5 text-sm font-extrabold text-white shadow-lg shadow-brand-start/20 transition-all hover:shadow-brand-start/35">
                                <i class="ph-fill ph-ticket"></i> Đặt vé
                            </a>
                        </div>
                    </div>
                </div>
            </article>
        @empty
            <div class="col-span-full cinema-card rounded-3xl border app-border p-8 sm:p-10 text-center">
                <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-start/10 text-brand-start">
                    <i class="ph-fill ph-calendar-x text-4xl"></i>
                </div>
                <h3 class="text-2xl font-extrabold app-text">Chưa có suất chiếu gần nhất</h3>
                <p class="mx-auto mt-2 max-w-xl app-muted">Vui lòng quay lại sau hoặc xem danh sách phim đang chiếu.</p>
                <a href="{{ route('user.movies.index', ['status' => 'now_showing']) }}" class="btn-primary mt-6">
                    <i class="ph-fill ph-film-strip"></i>
                    Xem phim đang chiếu
                </a>
            </div>
        @endforelse
    </div>
</section>

<section class="relative overflow-hidden py-16">
    <div class="absolute inset-0 -z-10 bg-[linear-gradient(180deg,transparent_0%,rgba(11,16,32,0.34)_48%,transparent_100%)]"></div>
    <div class="mx-auto max-w-[1400px] px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-5 mb-8">
        <div>
            <p class="bg-gradient-to-r from-pink-500 via-red-500 to-orange-500 bg-clip-text text-sm font-black uppercase tracking-[0.28em] text-transparent mb-3">Now Showing</p>
            <h2 class="text-3xl sm:text-4xl font-black app-text">Phim đang chiếu</h2>
        </div>
        <a href="{{ route('user.movies.index', ['status' => 'now_showing']) }}" class="inline-flex w-fit items-center justify-center gap-2 rounded-xl border app-border app-card px-5 py-3 text-sm font-bold app-text-soft transition-all duration-300 hover:border-brand-start/40 hover:bg-brand-start/10 hover:text-brand-start hover:shadow-lg hover:shadow-brand-start/10">
            Xem tất cả <i class="ph ph-arrow-right"></i>
        </a>
    </div>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
        @forelse($nowShowingMovies as $movie)
            @include('user.movies._home-movie-card', ['movie' => $movie, 'type' => 'now_showing'])
        @empty
            <div class="col-span-full dark-surface rounded-3xl border border-white/[0.08] p-10 text-center shadow-[0_10px_30px_rgba(0,0,0,0.35)]">
                <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-start/10 text-brand-start">
                    <i class="ph-fill ph-film-strip text-4xl"></i>
                </div>
                <h3 class="text-2xl font-black text-white">Chưa có phim đang chiếu</h3>
                <p class="mx-auto mt-2 max-w-md text-sm text-gray-400">Danh sách phim sẽ được cập nhật khi hệ thống có dữ liệu mới.</p>
            </div>
        @endforelse
    </div>
    </div>
</section>

@include('user.partials.showtime-section')

<section class="relative overflow-hidden py-16">
    <div class="absolute inset-0 -z-10 bg-[linear-gradient(180deg,transparent_0%,rgba(11,16,32,0.34)_48%,transparent_100%)]"></div>
    <div class="mx-auto max-w-[1400px] px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-5 mb-8">
        <div>
            <p class="bg-gradient-to-r from-pink-500 via-red-500 to-orange-500 bg-clip-text text-sm font-semibold uppercase tracking-[0.28em] text-transparent mb-3 opacity-90">Coming Soon</p>
            <h2 class="text-3xl sm:text-4xl font-bold app-heading">Phim sắp chiếu</h2>
        </div>
        <a href="{{ route('user.movies.index', ['status' => 'coming_soon']) }}" class="inline-flex w-fit items-center justify-center gap-2 rounded-xl border app-border app-card px-5 py-3 text-sm font-bold app-text-soft transition-all duration-300 hover:border-orange-400/25 hover:bg-pink-500/10 hover:text-brand-start hover:shadow-lg hover:shadow-pink-500/10">
            Xem tất cả <i class="ph ph-arrow-right"></i>
        </a>
    </div>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
        @forelse($comingSoonMovies as $movie)
            @include('user.movies._home-movie-card', ['movie' => $movie, 'type' => 'coming_soon'])
        @empty
            <div class="col-span-full dark-surface rounded-3xl border border-white/[0.08] p-10 text-center shadow-[0_10px_30px_rgba(0,0,0,0.35)]">
                <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-pink-500/10 text-pink-400">
                    <i class="ph-fill ph-film-strip text-4xl"></i>
                </div>
                <h3 class="text-2xl font-black text-white">Chưa có phim sắp chiếu</h3>
                <p class="mx-auto mt-2 max-w-md text-sm text-gray-400">Danh sách phim sẽ được cập nhật khi hệ thống có dữ liệu mới.</p>
            </div>
        @endforelse
    </div>
    </div>
</section>

<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        @foreach([
            ['ph-sparkle', 'AI gợi ý phim', 'Chọn phim theo tâm trạng, thể loại và lịch rảnh.'],
            ['ph-armchair', 'Chọn ghế trực quan', 'Sơ đồ ghế rõ ràng, phân biệt ghế thường và VIP.'],
            ['ph-qr-code', 'Vé QR tiện lợi', 'Dùng mã QR để soát vé nhanh tại rạp.'],
            ['ph-lightning', 'Đặt vé nhanh', 'Luồng đặt vé gọn, dễ thao tác trên mọi thiết bị.'],
        ] as $feature)
            <div class="cinema-card p-5">
                <div class="w-11 h-11 rounded-2xl bg-brand-start/10 text-brand-start flex items-center justify-center mb-4">
                    <i class="ph-fill {{ $feature[0] }} text-2xl"></i>
                </div>
                <h3 class="app-text font-extrabold mb-2">{{ $feature[1] }}</h3>
                <p class="app-muted text-sm leading-relaxed">{{ $feature[2] }}</p>
            </div>
        @endforeach
    </div>
</section>
@endsection
