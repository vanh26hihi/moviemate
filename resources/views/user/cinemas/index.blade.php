@extends('layouts.user')

@section('title', 'Rạp MovieMate')
@section('meta_description', 'Tìm rạp MovieMate theo khu vực, lịch chiếu và khoảng cách.')

@section('content')
<section class="cinema-surface py-10 md:py-14">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <p class="text-sm font-extrabold uppercase tracking-[0.24em] text-brand-start">Hệ thống chi nhánh</p>
        <h1 class="hero-title mt-3 text-4xl font-extrabold app-text md:text-5xl">Rạp MovieMate</h1>
        <p class="mt-3 max-w-2xl app-muted">Tìm rạp theo khu vực, xem lịch chiếu và chọn đúng chi nhánh trước khi đặt vé.</p>

        <form method="GET" action="{{ route('cinemas.index') }}" class="cinema-card mt-8 grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-[1fr_180px_180px_auto_auto]" aria-label="Lọc danh sách rạp">
            <label><span class="sr-only">Tên rạp hoặc địa chỉ</span><input class="cinema-input" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Tên rạp hoặc địa chỉ"></label>
            <label><span class="sr-only">Thành phố hoặc tỉnh</span><select class="cinema-input" name="city"><option value="">Tất cả thành phố</option>@foreach($cities as $city)<option value="{{ $city }}" @selected(($filters['city'] ?? '') === $city)>{{ $city }}</option>@endforeach</select></label>
            <label><span class="sr-only">Quận hoặc huyện</span><select class="cinema-input" name="district"><option value="">Tất cả quận/huyện</option>@foreach($districts as $district)<option value="{{ $district }}" @selected(($filters['district'] ?? '') === $district)>{{ $district }}</option>@endforeach</select></label>
            <label class="flex items-center gap-2 rounded-2xl border app-border px-4"><input type="checkbox" name="open" value="1" @checked(!empty($filters['open']))> <span class="text-sm font-bold app-text">Đang nhận lịch</span></label>
            <button class="btn-primary" type="submit"><i class="ph ph-magnifying-glass" aria-hidden="true"></i>Tìm rạp</button>
        </form>
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <button id="nearbyCinemaBtn" type="button" class="btn-secondary" data-nearby-url="{{ route('cinemas.index', request()->only(['search', 'city', 'district', 'open'])) }}"><i class="ph-fill ph-navigation-arrow" aria-hidden="true"></i>Rạp gần bạn</button>
            <p id="nearbyCinemaStatus" class="text-sm app-muted" role="status" aria-live="polite">Vị trí chỉ được yêu cầu khi bạn bấm nút.</p>
            @if(request()->hasAny(['search','city','district','open','nearby']))<a href="{{ route('cinemas.index') }}" class="text-sm font-bold text-brand-start">Đặt lại bộ lọc</a>@endif
        </div>
    </div>
</section>

<section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse($cinemas as $cinema)
            <article class="cinema-card flex flex-col rounded-3xl p-6">
                <div class="flex items-start justify-between gap-3"><div><p class="text-xs font-extrabold uppercase tracking-wider text-brand-start">{{ $cinema->district ?: $cinema->city }}</p><h2 class="mt-2 text-2xl font-extrabold app-text">{{ $cinema->name }}</h2></div>@if($preferredCinema?->is($cinema))<span class="rounded-full bg-brand-start/10 px-3 py-1 text-xs font-bold text-brand-start">Rạp ưu tiên</span>@endif</div>
                <p class="mt-3 text-sm leading-relaxed app-muted">{{ $cinema->address }}</p>
                @if($cinema->phone)<p class="mt-2 text-sm app-muted"><i class="ph ph-phone" aria-hidden="true"></i> {{ $cinema->phone }}</p>@endif
                <p class="mt-4 text-sm font-bold {{ $cinema->is_accepting_showtimes ? 'text-emerald-500' : 'app-muted' }}">{{ $cinema->today_hours }}</p>
                <dl class="mt-5 grid grid-cols-2 gap-3 text-sm"><div class="rounded-2xl app-secondary p-3"><dt class="app-muted">Phim khả dụng</dt><dd class="mt-1 text-xl font-extrabold app-text">{{ $cinema->available_movie_count }}</dd></div><div class="rounded-2xl app-secondary p-3"><dt class="app-muted">Suất sắp tới</dt><dd class="mt-1 text-xl font-extrabold app-text">{{ $cinema->upcoming_showtime_count }}</dd></div></dl>
                @if($cinema->distance_km !== null)<p class="mt-4 text-sm font-extrabold text-brand-start">Cách bạn khoảng {{ number_format($cinema->distance_km, 1, ',', '.') }} km</p>@endif
                <div class="mt-auto flex gap-3 pt-6"><a href="{{ route('cinemas.show', $cinema->code) }}" class="btn-primary flex-1">Xem lịch chiếu</a><form method="POST" action="{{ route('cinema-context.update') }}">@csrf<input type="hidden" name="cinema" value="{{ $cinema->code }}"><button class="btn-secondary" type="submit" aria-label="Chọn {{ $cinema->name }} làm rạp ưu tiên"><i class="ph ph-push-pin" aria-hidden="true"></i></button></form></div>
            </article>
        @empty
            <div class="cinema-card col-span-full rounded-3xl p-10 text-center"><i class="ph-fill ph-buildings text-4xl text-brand-start" aria-hidden="true"></i><h2 class="mt-4 text-xl font-extrabold app-text">Không tìm thấy rạp phù hợp.</h2><p class="mt-2 app-muted">Hãy thử thay đổi tên, thành phố hoặc quận/huyện.</p></div>
        @endforelse
    </div>
</section>
@endsection
