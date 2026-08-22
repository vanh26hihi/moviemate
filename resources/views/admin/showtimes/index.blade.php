@extends('layouts.admin')

@section('title', 'Quản lý suất chiếu - Quản trị MovieMate')
@section('page-title', 'Quản lý suất chiếu')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <p class="text-brand-start text-sm font-extrabold uppercase tracking-[0.22em] mb-2">Suất chiếu</p>
            <h1 class="text-3xl font-extrabold app-text">Suất chiếu</h1>
            <p class="app-muted mt-2">Lịch vận hành phòng theo múi giờ {{ $cinemaTimezone }}, gồm {{ $cleaningBufferMinutes }} phút vệ sinh.</p>
        </div>
        @can('showtimes.create')
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.showtimes.copy.index') }}" class="btn-secondary"><i class="ph-bold ph-copy"></i> Sao chép lịch</a>
                <a href="{{ route('admin.showtimes.bulk.index') }}" class="btn-secondary"><i class="ph-bold ph-list-plus"></i> Tạo nhiều suất</a>
                <a href="{{ route('admin.showtimes.create') }}" class="btn-primary"><i class="ph-bold ph-plus"></i> Thêm suất chiếu</a>
            </div>
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
                <select name="lifecycle" class="cinema-input">
                    <option value="">Tất cả trạng thái</option>
                    <option value="upcoming" @selected(request('lifecycle') === 'upcoming')>Sắp chiếu</option>
                    <option value="playing" @selected(request('lifecycle') === 'playing')>Đang chiếu</option>
                    <option value="completed" @selected(request('lifecycle') === 'completed')>Đã chiếu xong</option>
                    <option value="cancelled" @selected(request('lifecycle') === 'cancelled')>Đã hủy</option>
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
                        <th>Định dạng</th>
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
                            $lifecycle = $lifecycleSnapshots->get($showtime->id);
                            $movieCrossesMidnight = $window && !$window->movieEnd->isSameDay($window->start);
                            $readyCrossesMidnight = $window && !$window->operationalEnd->isSameDay($window->start);
                            $lifecycleClasses = match($lifecycle['state'] ?? null) {
                                'upcoming' => 'text-brand-start bg-brand-start/10',
                                'playing' => 'text-success bg-success/10',
                                'completed' => 'app-muted app-secondary',
                                'cancelled' => 'text-error bg-error/10',
                                default => 'text-warning bg-warning/10',
                            };
                        @endphp
                        <tr>
                            <td>
                                <span class="font-extrabold app-text text-sm block max-w-[240px]">{{ $showtime->movie->title }}</span>
                                <span class="text-xs app-muted">{{ $showtime->room->code }} · {{ $showtime->room->name }} · sơ đồ phiên bản {{ $showtime->roomLayout?->version ?? '?' }}</span>
                            </td>
                            <td><span class="status-badge app-secondary app-text">{{ $showtime->presentationFormat?->name ?? 'Chưa xác định' }}</span></td>
                            <td>
                                <span class="font-extrabold app-text block">{{ $window?->start->format('H:i') ?? '--:--' }}</span>
                                <span class="text-xs app-muted">{{ $window?->start->format('d/m/Y') ?? 'Thời lượng không hợp lệ' }}</span>
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
                                <span
                                    class="status-badge {{ $lifecycleClasses }}"
                                    data-showtime-lifecycle
                                    data-server-now="{{ ($lifecycle['now'] ?? null)?->toIso8601String() }}"
                                    data-start-at="{{ ($lifecycle['starts_at'] ?? null)?->toIso8601String() }}"
                                    data-end-at="{{ ($lifecycle['ends_at'] ?? null)?->toIso8601String() }}"
                                    data-cancelled="{{ $showtime->status === 'cancelled' ? 'true' : 'false' }}"
                                >{{ $lifecycle['label'] ?? 'Không xác định' }}</span>
                            </td>
                            <td>
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <a href="{{ route('admin.showtimes.show', $showtime) }}" class="admin-btn-secondary whitespace-nowrap" aria-label="Xem chi tiết suất chiếu {{ $showtime->movie->title }}">Xem chi tiết</a>
                                    @can('showtimes.update')
                                        @if($showtime->status === 'active' && ($lifecycle['state'] ?? null) === 'upcoming' && ! $showtime->bookings_exists && ! $showtime->booking_seats_exists)
                                        <a href="{{ route('admin.showtimes.edit', $showtime) }}" data-showtime-edit-action class="inline-flex items-center justify-center w-9 h-9 rounded-xl border app-border app-muted hover:text-brand-start hover:border-brand-start transition-colors" title="Chỉnh sửa"><i class="ph-bold ph-pencil-simple text-xs"></i></a>
                                        @elseif($showtime->bookings_exists || $showtime->booking_seats_exists)
                                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl border app-border app-muted" title="Suất chiếu đã có lịch sử đặt vé"><i class="ph-bold ph-lock-key text-xs"></i></span>
                                        @endif
                                    @endcan
                                    @can('showtimes.delete')
                                        @if($showtime->status === 'active' && in_array($lifecycle['state'] ?? null, ['upcoming', 'playing'], true))
                                        <form method="POST" action="{{ route('admin.showtimes.destroy', $showtime) }}" data-showtime-cancel-action onsubmit="return confirm('Bạn có chắc muốn hủy suất chiếu này? Suất đã có đơn đặt vé sẽ không thể hủy trực tiếp.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center justify-center w-9 h-9 rounded-xl border app-border app-muted hover:text-white hover:bg-error hover:border-error transition-colors" title="Hủy suất chiếu" aria-label="Hủy suất chiếu"><i class="ph-bold ph-x-circle text-xs" aria-hidden="true"></i></button>
                                        </form>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center app-muted py-10">Không có suất chiếu nào.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-5 border-t app-border">{{ $showtimes->links() }}</div>
    </div>
</div>
@endsection
