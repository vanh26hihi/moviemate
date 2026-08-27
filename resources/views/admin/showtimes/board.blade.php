@extends('layouts.admin')

@section('title', 'Bảng điều hành suất chiếu')
@section('page-title', 'Bảng điều hành suất chiếu')

@section('content')
@php
    $queryWithoutPage = request()->except('page');
    $hasFilters = filled($filters['search'] ?? null)
        || filled($filters['room_id'] ?? null)
        || filled($filters['movie_id'] ?? null)
        || filled($filters['presentation_format_id'] ?? null)
        || filled($filters['status'] ?? null);
@endphp

<div class="space-y-6" data-showtime-board>
    <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-brand-start">Operations board</p>
            <h1 class="admin-page-title mt-1">Lịch vận hành phòng chiếu</h1>
            <p class="admin-page-subtitle mt-2">
                {{ $currentCinema?->name ?? 'Tất cả rạp được phép truy cập' }} · {{ $from->format('d/m/Y') }}–{{ $to->format('d/m/Y') }} · {{ $timezone }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.showtimes.board.export', $queryWithoutPage) }}" class="admin-btn-secondary"><i class="ph-bold ph-download-simple"></i>Xuất CSV</a>
            <a href="{{ route('admin.showtimes.index') }}" class="admin-btn-secondary"><i class="ph-bold ph-list"></i>Danh sách</a>
            @can('showtimes.create')
                <a href="{{ route('admin.showtimes.create') }}" class="admin-btn-primary"><i class="ph-bold ph-plus"></i>Thêm suất</a>
            @endcan
        </div>
    </header>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6" aria-label="Tổng quan lịch chiếu">
        @foreach([
            ['label' => 'Tổng suất', 'value' => $summary['total'], 'icon' => 'ph-calendar-dots', 'tone' => 'text-brand-start'],
            ['label' => 'Đang hoạt động', 'value' => $summary['active'], 'icon' => 'ph-play-circle', 'tone' => 'text-success'],
            ['label' => 'Đã hủy', 'value' => $summary['cancelled'], 'icon' => 'ph-x-circle', 'tone' => 'text-error'],
            ['label' => 'Lượt đặt', 'value' => $summary['bookings'], 'icon' => 'ph-ticket', 'tone' => 'text-warning'],
            ['label' => 'Phút sử dụng phòng', 'value' => number_format($summary['occupied_minutes']), 'icon' => 'ph-timer', 'tone' => 'text-brand-start'],
            ['label' => 'Suất/phòng/ngày', 'value' => number_format($summary['average_showtimes_per_room_day'], 1), 'icon' => 'ph-chart-bar', 'tone' => 'text-success'],
        ] as $metric)
            <article class="admin-detail-card !p-4">
                <div class="flex items-center justify-between gap-3">
                    <div><p class="text-xs font-bold uppercase tracking-wide app-muted">{{ $metric['label'] }}</p><p class="mt-2 text-2xl font-black app-heading">{{ $metric['value'] }}</p></div>
                    <i class="ph-duotone {{ $metric['icon'] }} {{ $metric['tone'] }} text-3xl" aria-hidden="true"></i>
                </div>
            </article>
        @endforeach
    </section>

    @if($summary['invalid'] > 0)
        <div class="rounded-2xl border border-error/30 bg-error/10 px-4 py-3 text-sm font-semibold text-error" role="alert">
            <i class="ph-bold ph-warning mr-1"></i>{{ $summary['invalid'] }} suất có cấu hình thời gian không hợp lệ và cần được kiểm tra.
        </div>
    @endif

    <section class="admin-detail-card">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex gap-2">
                <a class="admin-btn-secondary" href="{{ route('admin.showtimes.board', [...request()->except(['from', 'to']), ...$previousPeriod]) }}"><i class="ph-bold ph-caret-left"></i>Kỳ trước</a>
                <a class="admin-btn-secondary" href="{{ route('admin.showtimes.board', [...request()->except(['from', 'to']), ...$nextPeriod]) }}">Kỳ sau<i class="ph-bold ph-caret-right"></i></a>
            </div>
            @if($hasFilters)<a class="text-sm font-bold text-brand-start hover:underline" href="{{ route('admin.showtimes.board', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">Xóa bộ lọc</a>@endif
        </div>

        <form method="GET" action="{{ route('admin.showtimes.board') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
            <div><label class="admin-label" for="board-from">Từ ngày</label><input class="admin-input" id="board-from" type="date" name="from" value="{{ $from->toDateString() }}" required></div>
            <div><label class="admin-label" for="board-to">Đến ngày</label><input class="admin-input" id="board-to" type="date" name="to" value="{{ $to->toDateString() }}" required></div>
            <div><label class="admin-label" for="board-search">Tìm kiếm</label><input class="admin-input" id="board-search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Tên phim hoặc phòng"></div>
            <div><label class="admin-label" for="board-room">Phòng</label><select class="admin-input" id="board-room" name="room_id"><option value="">Tất cả phòng</option>@foreach($rooms as $room)<option value="{{ $room->id }}" @selected(($filters['room_id'] ?? null) == $room->id)>{{ $room->cinema?->code }} · {{ $room->code }}</option>@endforeach</select></div>
            <div><label class="admin-label" for="board-status">Trạng thái</label><select class="admin-input" id="board-status" name="status"><option value="">Tất cả</option><option value="active" @selected(($filters['status'] ?? null) === 'active')>Hoạt động</option><option value="cancelled" @selected(($filters['status'] ?? null) === 'cancelled')>Đã hủy</option></select></div>
            <div class="flex items-end"><button class="admin-btn-primary w-full" type="submit"><i class="ph-bold ph-funnel"></i>Áp dụng</button></div>
        </form>
        @error('to')<p class="mt-3 text-sm font-semibold text-error">{{ $message }}</p>@enderror
    </section>

    <section class="admin-table-card overflow-hidden" aria-labelledby="board-grid-title">
        <div class="border-b app-border px-5 py-4"><h2 id="board-grid-title" class="text-base font-extrabold app-text">Ma trận phòng và ngày</h2><p class="mt-1 text-sm app-muted">Mỗi thẻ hiển thị từ giờ bắt đầu đến khi phòng sẵn sàng sau vệ sinh.</p></div>
        <div class="overflow-x-auto">
            <table class="min-w-[1100px] w-full border-collapse text-sm">
                <thead><tr class="app-secondary"><th class="sticky left-0 z-20 min-w-52 border-b border-r app-border app-secondary px-4 py-3 text-left">Phòng chiếu</th>@foreach($days as $day)<th class="min-w-52 border-b border-r app-border px-3 py-3 text-left"><span class="block font-extrabold app-text">{{ $day->translatedFormat('D') }}</span><span class="text-xs app-muted">{{ $day->format('d/m/Y') }}</span></th>@endforeach</tr></thead>
                <tbody>
                @forelse($rooms as $room)
                    <tr>
                        <th class="sticky left-0 z-10 border-b border-r app-border app-card px-4 py-3 text-left align-top"><span class="block font-extrabold app-text">{{ $room->code }} · {{ $room->name }}</span><span class="mt-1 block text-xs app-muted">{{ $room->cinema?->name }}</span></th>
                        @foreach($days as $day)
                            @php($cellEntries = $entriesByRoomAndDay->get($room->id.'|'.$day->toDateString(), collect()))
                            <td class="border-b border-r app-border p-2 align-top">
                                <div class="space-y-2">
                                    @forelse($cellEntries as $entry)
                                        <a href="{{ route('admin.showtimes.show', $entry['id']) }}" class="block rounded-xl border p-3 transition hover:-translate-y-0.5 hover:shadow-lg {{ $entry['status'] === 'cancelled' ? 'border-error/30 bg-error/10 opacity-70' : ($entry['invalid'] ? 'border-warning/40 bg-warning/10' : 'border-brand-start/20 bg-brand-start/5') }}">
                                            <span class="flex items-center justify-between gap-2"><strong class="text-base app-text">{{ $entry['starts_at']->format('H:i') }}</strong><span class="text-[10px] font-extrabold uppercase text-brand-start">{{ $entry['format'] }}</span></span>
                                            <span class="mt-1 block line-clamp-2 font-bold app-text">{{ $entry['movie'] }}</span>
                                            <span class="mt-2 block text-xs app-muted">Phim hết {{ $entry['movie_ends_at']?->format('H:i') ?? '—' }} · phòng rảnh {{ $entry['room_ready_at']?->format('H:i') ?? '—' }}</span>
                                            @if($entry['bookings_count'])<span class="mt-2 inline-flex items-center gap-1 text-xs font-bold text-warning"><i class="ph-bold ph-ticket"></i>{{ $entry['bookings_count'] }} lượt đặt</span>@endif
                                        </a>
                                    @empty
                                        <span class="block py-6 text-center text-xs app-muted">Trống</span>
                                    @endforelse
                                </div>
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ $days->count() + 1 }}" class="px-6 py-12 text-center app-muted">Không có phòng hoặc suất chiếu phù hợp với bộ lọc.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="admin-table-card">
        <div class="border-b app-border px-5 py-4"><h2 class="text-base font-extrabold app-text">Danh sách chi tiết</h2></div>
        <div class="divide-y app-border">
            @forelse($entries as $entry)
                <article class="grid gap-3 px-4 py-4 md:grid-cols-[110px_1fr_180px_140px] md:items-center">
                    <div><p class="text-lg font-black app-text">{{ $entry['starts_at']->format('H:i') }}</p><p class="text-xs app-muted">{{ $entry['starts_at']->format('d/m/Y') }}</p></div>
                    <div><a class="font-extrabold app-text hover:text-brand-start" href="{{ route('admin.showtimes.show', $entry['id']) }}">{{ $entry['movie'] }}</a><p class="mt-1 text-sm app-muted">{{ $entry['cinema'] }} · {{ $entry['room'] }} · {{ $entry['format'] }}</p></div>
                    <div class="text-sm"><p class="app-text">Hết phim: <strong>{{ $entry['movie_ends_at']?->format('H:i d/m') ?? '—' }}</strong></p><p class="app-muted">Phòng rảnh: {{ $entry['room_ready_at']?->format('H:i d/m') ?? '—' }}</p></div>
                    <div class="md:text-right"><span class="status-badge {{ $entry['status'] === 'cancelled' ? 'bg-error/10 text-error' : 'bg-success/10 text-success' }}">{{ $entry['lifecycle_label'] }}</span></div>
                </article>
            @empty
                <div class="px-5 py-12 text-center app-muted">Không tìm thấy suất chiếu trong khoảng đã chọn.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
