@extends('layouts.staff')

@section('title', 'Bán vé tại quầy - MovieMate')
@section('page-title', 'Bán vé tại quầy')

@section('content')
<div class="space-y-6">
    <header class="admin-page-header">
        <div><h1 class="admin-page-title">Bán vé tại quầy</h1><p class="admin-page-subtitle">Chọn suất chiếu thuộc chi nhánh đang vận hành.</p></div>
    </header>

    @if($accessible->count() > 1)
        <form method="POST" action="{{ route('staff.counter.cinema') }}" class="cinema-card flex flex-wrap items-end gap-3 p-5">
            @csrf
            <label class="cinema-label min-w-64">Chi nhánh vận hành
                <select name="cinema_id" class="cinema-input mt-1" required>
                    <option value="">Chọn chi nhánh</option>
                    @foreach($accessible as $option)<option value="{{ $option->id }}" @selected($cinema?->id === $option->id)>{{ $option->name }}</option>@endforeach
                </select>
            </label>
            <button class="btn-secondary" type="submit">Áp dụng</button>
        </form>
    @endif

    @if($cinema)
        <form method="GET" action="{{ route('staff.counter.index') }}" class="cinema-card grid gap-4 p-5 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)_auto] sm:items-end">
            <label class="cinema-label">Ngày chiếu
                <input name="date" type="date" class="cinema-input mt-1" value="{{ $selectedDate?->toDateString() }}" min="{{ now($cinema->timezone)->toDateString() }}" max="{{ now($cinema->timezone)->addDays(\App\Services\PublicShowtimeCatalog::WINDOW_DAYS - 1)->toDateString() }}">
            </label>
            <label class="cinema-label">Phim
                <select name="movie" class="cinema-input mt-1">
                    <option value="">Tất cả phim</option>
                    @foreach($movies as $movie)<option value="{{ $movie->id }}" @selected((int) request('movie') === $movie->id)>{{ $movie->title }}</option>@endforeach
                </select>
            </label>
            <button class="btn-secondary" type="submit"><i class="ph ph-funnel"></i>Lọc suất chiếu</button>
        </form>
    @endif

    @if($accessible->isEmpty() && !auth()->user()->hasRole('admin'))
        <x-empty-state title="Bạn chưa được phân công chi nhánh" description="Liên hệ quản lý để được cấp phạm vi vận hành trước khi bán vé." icon="ph-map-pin" />
    @elseif(!$cinema && !auth()->user()->hasRole('admin'))
        <x-empty-state title="Chọn chi nhánh vận hành" description="Tài khoản có nhiều chi nhánh. Hãy chọn một chi nhánh trước khi bán vé." icon="ph-map-pin" />
    @elseif($showtimes->isEmpty())
        <x-empty-state title="Chưa có suất chiếu có thể bán" description="Không tìm thấy suất chiếu còn hạn bán, có layout đã phát hành và cấu hình giá hợp lệ." icon="ph-calendar-x" />
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach($showtimes as $showtime)
                <article class="cinema-card p-5">
                    <p class="text-xs font-extrabold uppercase tracking-wider text-brand-start">{{ $showtime->cinema->name }}</p>
                    <h2 class="mt-2 text-xl font-extrabold app-heading">{{ $showtime->movie->title }}</h2>
                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="app-muted">Thời gian</dt><dd class="font-bold">{{ $showtime->show_date->format('d/m/Y') }} · {{ \Carbon\Carbon::parse($showtime->show_time)->format('H:i') }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="app-muted">Phòng</dt><dd class="font-bold">{{ $showtime->room->name }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="app-muted">Định dạng</dt><dd class="font-bold">{{ $showtime->room->room_type ?: '2D' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="app-muted">Giá từ</dt><dd class="font-bold">{{ number_format((int)$showtime->starting_price, 0, ',', '.') }} VNĐ</dd></div>
                    </dl>
                    <a href="{{ route('staff.counter.seats', $showtime) }}" class="btn-primary mt-5 w-full">Chọn ghế</a>
                </article>
            @endforeach
        </div>
    @endif
</div>
@endsection
