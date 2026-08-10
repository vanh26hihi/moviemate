@extends('layouts.app')

@section('title', 'Trang chủ - MovieMate')

@php
    $featuredMovie = $nowShowing->first() ?? $comingSoon->first();
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
                            <x-movie-media :src="$featuredMovie?->poster_url" :alt="$featuredMovie?->title ?? 'Phim MovieMate'" fallback-title="MovieMate" fallback-text="Ảnh áp phích phim sẽ hiển thị tại đây" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if (\Illuminate\Support\Facades\Route::has('user.ai.recommend.submit'))
            @include('components.home-ai-search')
        @endif
    </div>
</section>

<section class="relative overflow-hidden py-16">
    <div class="absolute inset-0 -z-10 bg-[linear-gradient(180deg,transparent_0%,rgba(11,16,32,0.34)_48%,transparent_100%)]"></div>
    <div class="mx-auto max-w-[1400px] px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-5 mb-8">
        <div>
            <p class="bg-gradient-to-r from-pink-500 via-red-500 to-orange-500 bg-clip-text text-sm font-black uppercase tracking-[0.28em] text-transparent mb-3">Đang chiếu</p>
            <h2 class="text-3xl sm:text-4xl font-black app-text">Phim đang chiếu</h2>
        </div>
        <a href="{{ route('user.movies.index', ['status' => 'now_showing']) }}" class="inline-flex w-fit items-center justify-center gap-2 rounded-xl border app-border app-card px-5 py-3 text-sm font-bold app-text-soft transition-all duration-300 hover:border-brand-start/40 hover:bg-brand-start/10 hover:text-brand-start hover:shadow-lg hover:shadow-brand-start/10">
            Xem tất cả <i class="ph ph-arrow-right"></i>
        </a>
    </div>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
        @forelse($nowShowing as $movie)
            @include('components.home-movie-card', ['movie' => $movie, 'type' => 'now_showing'])
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
            <p class="bg-gradient-to-r from-pink-500 via-red-500 to-orange-500 bg-clip-text text-sm font-semibold uppercase tracking-[0.28em] text-transparent mb-3 opacity-90">Sắp chiếu</p>
            <h2 class="text-3xl sm:text-4xl font-bold app-heading">Phim sắp chiếu</h2>
        </div>
        <a href="{{ route('user.movies.index', ['status' => 'coming_soon']) }}" class="inline-flex w-fit items-center justify-center gap-2 rounded-xl border app-border app-card px-5 py-3 text-sm font-bold app-text-soft transition-all duration-300 hover:border-orange-400/25 hover:bg-pink-500/10 hover:text-brand-start hover:shadow-lg hover:shadow-pink-500/10">
            Xem tất cả <i class="ph ph-arrow-right"></i>
        </a>
    </div>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
        @forelse($comingSoon as $movie)
            @include('components.home-movie-card', ['movie' => $movie, 'type' => 'coming_soon'])
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
