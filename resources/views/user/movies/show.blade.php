@extends('layouts.user')

@section('title', $movie->title . ' - MovieMate')

@php
    $poster = $movie->poster_url;
    $cover = $movie->cover_url ?: $poster;
    $showtimes = collect($showtimes ?? []);
    $cinemas = collect($cinemas ?? []);
    $selectedCinema = $selectedCinema ?? null;
    $preferredCinema = $preferredCinema ?? null;
    $selectedDate = $selectedDate ?? now(config('cinema.timezone', 'Asia/Ho_Chi_Minh'))->toDateString();
    $dates = collect($dates ?? [['date' => $selectedDate, 'label' => 'Hôm nay', 'day' => now()->format('d/m')]]);
    $genresText = $movie->genres->pluck('name')->join(', ') ?: 'Đang cập nhật';
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
            <div class="mb-6 flex items-center gap-3"><span class="h-8 w-1 rounded-full bg-gradient-to-b from-brand-start to-brand-end"></span><div><h2 class="text-2xl font-extrabold app-text md:text-3xl">Lịch chiếu theo rạp</h2><p class="mt-1 text-sm app-muted">Chọn chi nhánh và ngày; các rạp khác vẫn hiển thị khi chưa lọc.</p></div></div>
            <div class="mb-3 flex flex-wrap items-center gap-3"><button id="nearbyCinemaBtn" type="button" class="btn-secondary" data-nearby-target="movieCinemaSelect"><i class="ph-fill ph-navigation-arrow" aria-hidden="true"></i> Gần bạn</button><p id="nearbyCinemaStatus" class="text-sm app-muted" role="status" aria-live="polite"></p></div>
            <form method="GET" action="{{ route('user.movies.show', $movie->slug) }}" data-showtime-filter-form data-filter-endpoint="{{ route('showtimes.filter') }}" data-filter-context="movie" data-movie-slug="{{ $movie->slug }}" class="cinema-card mb-6 grid gap-4 p-4">
                <label><span class="mb-1 block text-sm font-bold app-text">Rạp</span><select id="movieCinemaSelect" name="cinema" class="cinema-input"><option value="">Tất cả rạp</option>@foreach($cinemas as $filterCinema)<option value="{{ $filterCinema->code }}" data-latitude="{{ $filterCinema->latitude }}" data-longitude="{{ $filterCinema->longitude }}" @selected($selectedCinema?->is($filterCinema))>{{ $filterCinema->name }}</option>@endforeach</select></label>
                <x-customer.showtimes.date-rail :dates="$dates" :selected-date="$selectedDate" />
                <button type="submit" class="btn-primary self-end">Xem lịch</button>
            </form>
            @if($preferredCinema && ! $selectedCinema)<p class="mb-4 text-sm app-muted">Rạp ưu tiên <strong class="app-text">{{ $preferredCinema->name }}</strong> được xếp trước; các rạp khác vẫn khả dụng.</p>@endif
            <div data-showtime-filter-status class="sr-only" role="status" aria-live="polite"></div>
            <div data-showtime-results>@include('user.partials.showtime-results', ['context' => 'movie', 'cinema' => $selectedCinema])</div>
        </section>
        @isset($publicReviews)
        <section id="reviews" class="mt-14" aria-labelledby="reviews-title">
            <div class="flex items-end justify-between gap-4"><div><h2 id="reviews-title" class="text-2xl font-extrabold app-text md:text-3xl">Đánh giá từ khách đã xem</h2><p class="mt-1 text-sm app-muted">Chỉ tài khoản có vé đã thanh toán, check-in và xem hết phim mới được đánh giá.</p></div><span class="text-sm font-bold text-brand-start">{{ $movie->reviews_count }} đánh giá</span></div>
            @auth
                @if($reviewBooking)
                    <form method="POST" action="{{ route('user.reviews.store',$movie) }}" class="cinema-card mt-6 rounded-3xl p-5">@csrf<input type="hidden" name="booking_id" value="{{ $reviewBooking->id }}"><h3 class="font-bold app-text">{{ $existingReview ? 'Cập nhật đánh giá' : 'Viết đánh giá' }}</h3><div class="mt-3 grid gap-3 sm:grid-cols-[160px_1fr_auto]"><label><span class="sr-only">Điểm từ 1 đến 10</span><input type="number" name="rating" min="1" max="10" value="{{ old('rating',$existingReview?->rating ?? 10) }}" class="user-form-control" required></label><label><span class="sr-only">Nhận xét</span><textarea name="comment" maxlength="2000" rows="3" class="user-form-control" placeholder="Chia sẻ cảm nhận của bạn">{{ old('comment',$existingReview?->comment) }}</textarea></label><button class="btn-primary self-end">Lưu đánh giá</button></div></form>
                @elseif($existingReview)<p class="cinema-card mt-6 rounded-2xl p-4 app-muted">Đánh giá của bạn đang ở trạng thái: {{ $existingReview->moderation_status_label }}.</p>
                @else<p class="cinema-card mt-6 rounded-2xl p-4 app-muted">Bạn có thể đánh giá sau khi vé được check-in và phim đã kết thúc.</p>@endif
            @else<p class="mt-5 app-muted"><a class="font-bold text-brand-start" href="{{ route('login') }}">Đăng nhập</a> để gửi đánh giá xác thực.</p>@endauth
            <div class="mt-6 grid gap-4 md:grid-cols-2">@forelse($publicReviews as $review)<article class="cinema-card rounded-2xl p-5"><div class="flex justify-between gap-3"><strong class="app-text">{{ $review->user->name }}</strong><strong class="text-brand-start">{{ $review->rating }}/10</strong></div><p class="mt-1 text-xs text-success"><i class="ph-fill ph-seal-check"></i> Đã xác thực vé xem phim</p><p class="mt-3 whitespace-pre-line app-muted">{{ $review->comment ?: 'Không có nhận xét.' }}</p></article>@empty<p class="app-muted">Chưa có đánh giá được công bố.</p>@endforelse</div>{{ $publicReviews->links() }}
        </section>
        @endisset
    </div>
</div>
@endsection
