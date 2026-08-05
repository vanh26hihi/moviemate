@extends('layouts.user')

@section('title', $movie->title . ' - MovieMate')

@php
    $poster = $movie->poster_url;
    $cover = $movie->cover_url ?: $poster;
    $genresText = $movie->genres->pluck('name')->join(', ') ?: 'Đang cập nhật';
    $showtimesByDate = $showtimes->groupBy(fn ($showtime) => \Carbon\Carbon::parse($showtime->show_date)->format('Y-m-d'));
@endphp

@section('content')
<div class="cinema-surface relative overflow-hidden">
    <div class="absolute inset-x-0 top-0 h-[28rem] opacity-40">
        @if($cover)
            <img src="{{ $cover }}" alt="{{ $movie->title }}" class="h-full w-full scale-105 object-cover blur-sm" loading="lazy">
            <div class="absolute inset-0 bg-gradient-to-b from-dark-main/60 via-dark-main/80 to-dark-main"></div>
        @else
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_18%_18%,rgba(255,61,87,0.28),transparent_34%),radial-gradient(circle_at_82%_12%,rgba(124,58,237,0.22),transparent_32%)]"></div>
        @endif
    </div>

    <div class="relative mx-auto max-w-7xl px-4 py-10 sm:px-6 md:py-14 lg:px-8">
        <div class="grid grid-cols-1 items-start gap-8 lg:grid-cols-12 lg:gap-12">
            <div class="lg:col-span-4">
                <div class="poster-frame cinema-card overflow-hidden rounded-3xl shadow-2xl shadow-black/30">
                    @if($poster)
                        <img src="{{ $poster }}" alt="{{ $movie->title }}" loading="lazy">
                    @else
                        <div class="fallback-poster">
                            <i class="ph-fill ph-film-slate"></i>
                            <strong class="text-2xl">MovieMate</strong>
                            <span>{{ $movie->title }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <div class="pt-2 lg:col-span-8 lg:pt-8">
                <div class="mb-5 flex flex-wrap items-center gap-3">
                    <span class="rounded-full px-3 py-1.5 text-xs font-extrabold uppercase tracking-wider text-white {{ $movie->status === 'now_showing' ? 'bg-brand-start' : 'bg-ai-start' }}">
                        {{ $movie->status === 'now_showing' ? 'Đang chiếu' : 'Sắp chiếu' }}
                    </span>
                    @if($movie->age_rating)
                        <span class="rounded-full border app-border px-3 py-1.5 text-xs font-extrabold app-text">{{ $movie->age_rating }}</span>
                    @endif
                </div>

                <h1 class="hero-title mb-6 text-4xl font-extrabold app-text md:text-6xl">{{ $movie->title }}</h1>

                <div class="mb-7 grid grid-cols-2 gap-3 md:grid-cols-4">
                    <div class="cinema-card p-4"><p class="mb-1 text-xs app-muted">Thể loại</p><p class="line-clamp-1 font-bold app-text">{{ $genresText }}</p></div>
                    <div class="cinema-card p-4"><p class="mb-1 text-xs app-muted">Thời lượng</p><p class="font-bold app-text">{{ $movie->duration ?? '--' }} phút</p></div>
                    <div class="cinema-card p-4"><p class="mb-1 text-xs app-muted">Quốc gia</p><p class="font-bold app-text">{{ $movie->country ?? 'Đang cập nhật' }}</p></div>
                    <div class="cinema-card p-4"><p class="mb-1 text-xs app-muted">Khởi chiếu</p><p class="font-bold app-text">{{ $movie->release_date ? \Carbon\Carbon::parse($movie->release_date)->format('d/m/Y') : 'Chưa xác định' }}</p></div>
                </div>

                <div class="cinema-card mb-6 p-5 sm:p-6">
                    <h2 class="mb-3 border-l-4 border-brand-start pl-3 text-xl font-extrabold app-text">Nội dung phim</h2>
                    <p class="leading-relaxed app-muted">{{ $movie->description ?? 'Nội dung phim đang được cập nhật.' }}</p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <a href="#showtimes" class="btn-primary"><i class="ph-fill ph-ticket"></i> Xem lịch chiếu</a>
                    @if($movie->trailer_url)
                        <a href="{{ $movie->trailer_url }}" target="_blank" rel="noopener noreferrer" class="btn-secondary"><i class="ph-fill ph-play-circle text-xl"></i> Xem video giới thiệu</a>
                    @endif
                </div>
            </div>
        </div>

        <section id="showtimes" class="mt-14">
            <div class="mb-6 flex items-center gap-3">
                <span class="h-8 w-1 rounded-full bg-gradient-to-b from-brand-start to-brand-end"></span>
                <div><h2 class="text-2xl font-extrabold app-text md:text-3xl">Lịch chiếu</h2><p class="mt-1 text-sm app-muted">Chọn giờ chiếu phù hợp tại MovieMate Cinema – FPT Polytechnic.</p></div>
            </div>

            @if($showtimes->isEmpty())
                <div class="cinema-card p-8 text-center"><i class="ph-fill ph-calendar-x text-4xl text-brand-start"></i><h3 class="mt-4 text-xl font-extrabold app-text">Chưa có suất chiếu</h3><p class="mt-2 app-muted">Hiện chưa có suất chiếu khả dụng cho phim này.</p></div>
            @else
                <div class="space-y-5">
                    @foreach($showtimesByDate as $date => $items)
                        <div class="cinema-card p-5">
                            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div><p class="text-sm font-extrabold uppercase tracking-wider text-brand-start">{{ \Carbon\Carbon::parse($date)->translatedFormat('l') }}</p><h3 class="text-xl font-extrabold app-text">{{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</h3></div>
                                <span class="text-sm app-muted">{{ $items->count() }} suất chiếu</span>
                            </div>
                            @php($first = $items->first())
                            <div class="rounded-2xl border app-border app-secondary p-4">
                                <h4 class="font-extrabold app-text">{{ $first->cinema->name }}</h4>
                                <p class="mt-1 text-sm app-muted">{{ $first->cinema->address }}</p>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    @foreach($items as $show)
                                        <a href="{{ route('user.bookings.selectSeat', $show->id) }}" class="rounded-xl border border-brand-start/30 bg-brand-start/10 px-4 py-2 font-extrabold text-brand-start transition-colors hover:bg-brand-start hover:text-white">
                                            {{ \Carbon\Carbon::parse($show->show_time)->format('H:i') }} · {{ $show->room->name }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</div>
@endsection
