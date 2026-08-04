@extends('layouts.admin')

@section('title', 'Quản lý suất chiếu - MovieMate Admin')
@section('page-title', 'Quản lý suất chiếu')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <p class="text-brand-start text-sm font-extrabold uppercase tracking-[0.22em] mb-2">Showtimes</p>
            <h1 class="text-3xl font-extrabold app-text">Suất chiếu</h1>
            <p class="app-muted mt-2">Lịch vận hành phòng theo múi giờ {{ $cinemaTimezone }}, gồm {{ $cleaningBufferMinutes }} phút vệ sinh.</p>
        </div>
        @can('showtimes.create')
            <a href="{{ route('admin.showtimes.create') }}" class="btn-primary"><i class="ph-bold ph-plus"></i> Thêm suất chiếu</a>
        @endcan
    </div>

    <div class="cinema-card overflow-hidden">
        <div class="p-5 border-b app-border">
            <form method="GET" action="{{ route('admin.showtimes.index') }}" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-[1fr_160px_160px_auto] gap-3">
                <select name="movie_id" class="cinema-input">
                    <option value="">Tất cả phim</option>
                    @foreach($movies as $movie)
                        <option value="{{ $movie->id }}" @selected(request('movie_id') == $movie->id)>{{ $movie->title }}</option>
                    @endforeach
                </select>
                <input type="date" name="show_date" value="{{ request('show_date') }}" class="cinema-input">
                <select name="status" class="cinema-input">
                    <option value="">Trạng thái</option>
                    <option value="active" @selected(request('status') === 'active')>Đang chiếu</option>
                    <option value="cancelled" @selected(request('status') === 'cancelled')>Đã hủy</option>
                    <option value="finished" @selected(request('status') === 'finished')>Đã chiếu xong</option>
                </select>
                <button type="submit" class="btn-secondary"><i class="ph ph-funnel"></i> Lọc</button>
            </form>
            <p class="text-xs app-muted mt-4">Hiển thị <span class="app-text font-bold">{{ $showtimes->total() }}</span> suất chiếu</p>
        </div>

        <div class="overflow-x-auto">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Phim / Phòng</th>
                        <th>Bắt đầu</th>
                        <th>Phim kết thúc</th>
                        <th>Phòng sẵn sàng</th>
                        <th>Vệ sinh</th>
                        <th class="text-center">Trạng thái</th>
                        <th class="text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($showtimes as $showtime)
                        @php
                            $window = $scheduleWindows->get($showtime->id);
                            $movieCrossesMidnight = $window && !$window->movieEnd->isSameDay($window->start);
                            $readyCrossesMidnight = $window && !$window->operationalEnd->isSameDay($window->start);
                        @endphp
                        <tr>
                            <td>
                                <span class="font-extrabold app-text text-sm block max-w-[240px]">{{ $showtime->movie->title }}</span>
                                <span class="text-xs app-muted">{{ $showtime->room->code }} · {{ $showtime->room->name }} · layout v{{ $showtime->roomLayout?->version ?? '?' }}</span>
                            </td>
                            <td>
                                <span class="font-extrabold app-text block">{{ $window?->start->format('H:i') ?? '--:--' }}</span>
                                <span class="text-xs app-muted">{{ $window?->start->format('d/m/Y') ?? 'Runtime không hợp lệ' }}</span>
                            </td>
                            <td>
                                <span class="font-bold app-text block">{{ $window?->movieEnd->format('H:i') ?? '--:--' }}</span>
                                @if($window)<span class="text-xs app-muted">{{ $window->movieEnd->format('d/m/Y') }}@if($movieCrossesMidnight) (+1 ngày)@endif</span>@endif
                            </td>
                            <td>
                                <span class="font-extrabold text-brand-start block">{{ $window?->operationalEnd->format('H:i') ?? '--:--' }}</span>
                                @if($window)<span class="text-xs app-muted">{{ $window->operationalEnd->format('d/m/Y') }}@if($readyCrossesMidnight) (+1 ngày)@endif</span>@endif
                            </td>
                            <td><span class="app-text font-bold">{{ $window?->cleaningBufferMinutes ?? $cleaningBufferMinutes }} phút</span></td>
                            <td class="text-center">
                                @if($showtime->status === 'active')
                                    <span class="status-badge text-success bg-success/10">Đang chiếu</span>
                                @elseif($showtime->status === 'cancelled')
                                    <span class="status-badge text-error bg-error/10">Đã hủy</span>
                                @else
                                    <span class="status-badge text-warning bg-warning/10">Đã chiếu xong</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-2">
                                    @can('showtimes.update')
                                        <a href="{{ route('admin.showtimes.edit', $showtime) }}" class="inline-flex items-center justify-center w-9 h-9 rounded-xl border app-border app-muted hover:text-brand-start hover:border-brand-start transition-colors" title="Chỉnh sửa"><i class="ph-bold ph-pencil-simple text-xs"></i></a>
                                    @endcan
                                    @can('showtimes.delete')
                                        <form method="POST" action="{{ route('admin.showtimes.destroy', $showtime) }}" onsubmit="return confirm('Bạn có chắc muốn xóa suất chiếu này?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center justify-center w-9 h-9 rounded-xl border app-border app-muted hover:text-white hover:bg-error hover:border-error transition-colors" title="Xóa"><i class="ph-bold ph-trash text-xs"></i></button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center app-muted py-10">Không có suất chiếu nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-5 border-t app-border">{{ $showtimes->links() }}</div>
    </div>
</div>
@endsection
