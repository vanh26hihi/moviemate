@extends('layouts.user')

@section('title', $movie->title . ' - MovieMate')

@php
    $poster = $movie->poster_url;
    $cover = $movie->cover_url ?: $poster;
    $genresText = $movie->genres->pluck('name')->join(', ') ?: 'Đang cập nhật';

    $showtimesByDate = $showtimes->groupBy(
        fn ($showtime) => \Carbon\Carbon::parse($showtime->show_date)->format('Y-m-d')
    );
@endphp

@section('content')
<div class="cinema-surface relative overflow-hidden">

    {{-- Background cover --}}
    <div class="absolute inset-x-0 top-0 h-[28rem] opacity-40">
        @if($cover)
            <img
                src="{{ $cover }}"
                alt="{{ $movie->title }}"
                class="w-full h-full object-cover blur-sm scale-105"
                loading="lazy"
            >

            <div class="absolute inset-0 bg-gradient-to-b from-dark-main/60 via-dark-main/80 to-dark-main"></div>
        @else
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_18%_18%,rgba(255,61,87,0.28),transparent_34%),radial-gradient(circle_at_82%_12%,rgba(124,58,237,0.22),transparent_32%)]"></div>
        @endif
    </div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 md:py-14">

        {{-- Thông tin phim --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-start">

            {{-- Poster --}}
            <div class="lg:col-span-4">
                <div class="poster-frame rounded-3xl cinema-card overflow-hidden shadow-2xl shadow-black/30">

                    @if($poster)
                        <img
                            src="{{ $poster }}"
                            alt="{{ $movie->title }}"
                            loading="lazy"
                        >
                    @else
                        <div class="fallback-poster">
                            <i class="ph-fill ph-film-slate"></i>

                            <strong class="text-2xl">
                                MovieMate
                            </strong>

                            <span>
                                {{ $movie->title }}
                            </span>
                        </div>
                    @endif

                </div>
            </div>


            {{-- Chi tiết phim --}}
            <div class="lg:col-span-8 pt-2 lg:pt-8">

                {{-- Badge --}}
                <div class="flex flex-wrap items-center gap-3 mb-5">

                    <span
                        class="{{ $movie->status === 'now_showing'
                            ? 'bg-brand-start'
                            : 'bg-ai-start' }}
                        text-white text-xs font-extrabold
                        px-3 py-1.5 rounded-full uppercase tracking-wider"
                    >
                        {{ $movie->status === 'now_showing'
                            ? 'Đang chiếu'
                            : 'Sắp chiếu' }}
                    </span>

                    @if($movie->age_rating)
                        <span class="border app-border app-text text-xs font-extrabold px-3 py-1.5 rounded-full">
                            {{ $movie->age_rating }}
                        </span>
                    @endif

                    <span class="border app-border app-text text-xs font-extrabold px-3 py-1.5 rounded-full">
                        <i class="ph-fill ph-star text-brand-start"></i>
                        8.6
                    </span>

                </div>


                {{-- Tên phim --}}
                <h1 class="hero-title text-4xl md:text-6xl font-extrabold app-text mb-6">
                    {{ $movie->title }}
                </h1>


                {{-- Thông tin nhanh --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-7">

                    <div class="cinema-card p-4">
                        <p class="text-xs app-muted mb-1">
                            Thể loại
                        </p>

                        <p class="app-text font-bold line-clamp-1">
                            {{ $genresText }}
                        </p>
                    </div>

                    <div class="cinema-card p-4">
                        <p class="text-xs app-muted mb-1">
                            Thời lượng
                        </p>

                        <p class="app-text font-bold">
                            {{ $movie->duration ?? '--' }} phút
                        </p>
                    </div>

                    <div class="cinema-card p-4">
                        <p class="text-xs app-muted mb-1">
                            Quốc gia
                        </p>

                        <p class="app-text font-bold">
                            {{ $movie->country ?? 'Đang cập nhật' }}
                        </p>
                    </div>

                    <div class="cinema-card p-4">
                        <p class="text-xs app-muted mb-1">
                            Khởi chiếu
                        </p>

                        <p class="app-text font-bold">
                            {{ $movie->release_date
                                ? \Carbon\Carbon::parse($movie->release_date)->format('d/m/Y')
                                : 'Chưa xác định' }}
                        </p>
                    </div>

                </div>


                {{-- Nội dung --}}
                <div class="cinema-card p-5 sm:p-6 mb-6">

                    <h2 class="text-xl font-extrabold app-text mb-3 border-l-4 border-brand-start pl-3">
                        Nội dung phim
                    </h2>

                    <p class="app-muted leading-relaxed">
                        {{ $movie->description ?? 'Nội dung phim đang được cập nhật.' }}
                    </p>

                </div>


                {{-- Button --}}
                <div class="flex flex-wrap gap-3">

                    <a
                        href="#showtimes"
                        class="btn-primary"
                    >
                        <i class="ph-fill ph-ticket"></i>
                        Xem lịch chiếu
                    </a>

                    @if($movie->trailer_url)
                        <a
                            href="{{ $movie->trailer_url }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="btn-secondary"
                        >
                            <i class="ph-fill ph-play-circle text-xl"></i>
                            Xem trailer
                        </a>
                    @endif

                </div>

            </div>
        </div>


        {{-- Lịch chiếu --}}
        <section id="showtimes" class="mt-14">

            <div class="flex items-center gap-3 mb-6">

                <span class="w-1 h-8 rounded-full bg-gradient-to-b from-brand-start to-brand-end"></span>

                <div>
                    <h2 class="text-2xl md:text-3xl font-extrabold app-text">
                        Lịch chiếu
                    </h2>
                    <p class="text-sm font-semibold text-brand-start">
                        {{ $availableShowtimesCount }} suất chiếu đang khả dụng
                    </p>
                    <p class="app-muted text-sm mt-1">
                        Chọn ngày, rạp và giờ chiếu phù hợp để đặt vé.
                    </p>
                </div>

            </div>


            @if($showtimes->isEmpty())

                <div class="cinema-card p-8 text-center">

                    <i class="ph-fill ph-calendar-x text-4xl text-brand-start"></i>

                    <h3 class="mt-4 text-xl font-extrabold app-text">
                        Chưa có suất chiếu
                    </h3>

                    <p class="mt-2 app-muted">
                        Hiện chưa có suất chiếu khả dụng cho phim này.
                    </p>

                </div>

            @else

                <div class="space-y-5">

                    @foreach($showtimesByDate as $date => $items)

                        <div class="cinema-card p-5">

                            {{-- Ngày chiếu --}}
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">

                                <div>
                                    <p class="text-brand-start text-sm font-extrabold uppercase tracking-wider">
                                        {{ \Carbon\Carbon::parse($date)->translatedFormat('l') }}
                                    </p>

                                    <div class="flex items-center gap-2">
                                        <h3 class="text-xl font-extrabold app-text">
                                            {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}
                                        </h3>
                                    
                                        @if(\Carbon\Carbon::parse($date)->isToday())
                                            <span class="rounded-full bg-brand-start px-2.5 py-1 text-xs font-extrabold text-white">
                                                Hôm nay
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <span class="app-muted text-sm">
                                    {{ $items->count() }} suất chiếu
                                </span>

                            </div>


                            {{-- Nhóm theo rạp --}}
                            <div class="space-y-4">

                                @foreach($items->groupBy('cinema_id') as $cinemaShowtimes)

                                    @php
                                        $first = $cinemaShowtimes->first();
                                    @endphp

                                    <div class="app-secondary border app-border rounded-2xl p-4">

                                        <div class="flex flex-col gap-4">

                                            {{-- Thông tin rạp --}}
                                            <div>

                                                <h4 class="app-text font-extrabold">
                                                    {{ $first->cinema->name }}
                                                </h4>

                                                @if($first->cinema->address)
                                                    <p class="mt-1 text-sm app-muted">
                                                        {{ $first->cinema->address }}
                                                    </p>
                                                @endif

                                                <p class="mt-1 text-xs font-semibold text-brand-start">
                                                    {{ $cinemaShowtimes->count() }} suất chiếu khả dụng
                                                </p>

                                            </div>


                                            {{-- Các suất chiếu --}}
                                            <div class="flex flex-wrap gap-2">

                                                @foreach($cinemaShowtimes as $show)
                                                @php
                                                $showDateTime = \Carbon\Carbon::parse(
                                                    $show->show_date->format('Y-m-d') . ' ' . $show->show_time,
                                                    'Asia/Ho_Chi_Minh'
                                                );
                                            
                                                $minutesUntilShow = now('Asia/Ho_Chi_Minh')->diffInMinutes(
                                                    $showDateTime,
                                                    false
                                                );
                                            
                                                $isStartingSoon = $minutesUntilShow >= 0 && $minutesUntilShow <= 60;
                                            @endphp
                                                    <a
                                                        href="{{ route('user.bookings.selectSeat', $show->id) }}"
                                                        class="min-w-[110px] rounded-xl border border-brand-start/30 bg-brand-start/10 px-4 py-2 text-brand-start transition-colors hover:bg-brand-start hover:text-white"
                                                    >

                                                        {{-- Giờ --}}
                                                        <div class="font-extrabold text-center">
                                                            {{ \Carbon\Carbon::parse($show->show_time)->format('H:i') }}
                                                            @if($isStartingSoon)
                                                            <div class="mt-1 text-center text-[10px] font-extrabold">
                                                                Sắp bắt đầu
                                                            </div>
                                                        @endif
                                                                @endif
                                                                @if($minutesUntilShow > 0 && $minutesUntilShow <= 120)
                                                                        <div class="mt-1 text-center text-[10px] font-semibold opacity-80">
                                                                            Còn {{ $minutesUntilShow }} phút
                                                                        </div>
                                                                    @endif
                                                        </div>

                                                        {{-- Phòng --}}
                                                        <div class="mt-1 text-center text-[11px] font-semibold opacity-80">
                                                            {{ $show->room->name }}
                                                        </div>

                                                        {{-- Giá thường --}}
                                                        <div class="mt-1 text-center text-[11px] opacity-80">
                                                            {{ number_format((float) $show->price, 0, ',', '.') }}đ
                                                        </div>

                                                        {{-- Giá VIP --}}
                                                        @if($show->vip_price)
                                                            <div class="text-center text-[10px] opacity-70">
                                                                VIP:
                                                                {{ number_format((float) $show->vip_price, 0, ',', '.') }}đ
                                                            </div>
                                                        @endif

                                                    </a>

                                                @endforeach

                                            </div>

                                        </div>

                                    </div>

                                @endforeach

                            </div>

                        </div>

                    @endforeach

                </div>

            @endif

        </section>

    </div>
</div>
@endsection