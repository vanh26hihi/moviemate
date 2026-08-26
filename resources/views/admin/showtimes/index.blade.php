@extends('layouts.admin')

@section('title', 'Quản lý suất chiếu - Quản trị MovieMate')
@section('page-title', 'Quản lý suất chiếu')

@section('content')
@php
    $hasActiveFilters = request()->filled('show_date') || request()->filled('movie_id') || request()->filled('lifecycle');
    $activeFilterCount = collect(['show_date', 'movie_id', 'lifecycle'])->filter(fn ($filter) => request()->filled($filter))->count();
@endphp

<div class="space-y-5" data-showtime-workspace>
    <header class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
        <div class="max-w-3xl">
            <p class="mb-2 text-sm font-extrabold uppercase tracking-[0.18em] text-brand-start">Lịch vận hành</p>
            <h1 class="admin-page-title">Suất chiếu</h1>
            <p class="admin-page-subtitle max-w-2xl text-sm leading-6">
                Xem nhanh phim, phòng và thời gian sử dụng phòng. Lịch dùng múi giờ {{ $cinemaTimezone }} và đã gồm {{ $cleaningBufferMinutes }} phút vệ sinh.
            </p>
        </div>

        @can('showtimes.create')
            <div class="flex w-full flex-col gap-2 sm:w-auto sm:items-end" aria-label="Tác vụ tạo suất chiếu">
                <a href="{{ route('admin.showtimes.create') }}" class="admin-btn-primary w-full sm:w-auto">
                    <i class="ph-bold ph-plus" aria-hidden="true"></i>
                    Thêm suất chiếu
                </a>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <a href="{{ route('admin.showtimes.copy.index') }}" class="admin-btn-secondary w-full sm:w-auto">
                        <i class="ph-bold ph-copy" aria-hidden="true"></i>
                        Sao chép lịch
                    </a>
                    <a href="{{ route('admin.showtimes.bulk.index') }}" class="admin-btn-secondary w-full sm:w-auto">
                        <i class="ph-bold ph-list-plus" aria-hidden="true"></i>
                        Tạo nhiều suất
                    </a>
                </div>
            </div>
        @endcan
    </header>

    <section class="admin-form-card !p-4 sm:!p-5" aria-labelledby="showtime-filters-title">
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 id="showtime-filters-title" class="text-base font-extrabold app-text">Tìm suất chiếu</h2>
                <p class="mt-1 text-sm app-muted">Chọn một hoặc nhiều tiêu chí, rồi bấm Áp dụng.</p>
            </div>
            @if($hasActiveFilters)
                <span class="inline-flex w-fit items-center gap-1.5 rounded-full app-secondary px-3 py-1 text-xs font-bold app-text">
                    <i class="ph-bold ph-check-circle text-brand-start" aria-hidden="true"></i>
                    {{ $activeFilterCount }} bộ lọc đang dùng
                </span>
            @endif
        </div>

        <form method="GET" action="{{ route('admin.showtimes.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-[minmax(160px,0.8fr)_minmax(260px,1.5fr)_minmax(190px,1fr)_auto] xl:items-end">
            <div>
                <label for="showtime-show-date" class="admin-label">Ngày chiếu</label>
                <input id="showtime-show-date" type="date" name="show_date" value="{{ request('show_date') }}" class="admin-input">
            </div>

            <div>
                <label for="showtime-movie" class="admin-label">Phim</label>
                <select id="showtime-movie" name="movie_id" class="admin-input">
                    <option value="">Tất cả phim</option>
                    @foreach($movies as $movie)
                        <option value="{{ $movie->id }}" @selected(request('movie_id') == $movie->id)>{{ $movie->title }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="showtime-lifecycle" class="admin-label">Trạng thái</label>
                <select id="showtime-lifecycle" name="lifecycle" class="admin-input">
                    <option value="">Tất cả trạng thái</option>
                    <option value="upcoming" @selected(request('lifecycle') === 'upcoming')>Sắp chiếu</option>
                    <option value="playing" @selected(request('lifecycle') === 'playing')>Đang chiếu</option>
                    <option value="completed" @selected(request('lifecycle') === 'completed')>Đã chiếu xong</option>
                    <option value="cancelled" @selected(request('lifecycle') === 'cancelled')>Đã hủy</option>
                </select>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row md:col-span-2 xl:col-span-1">
                <button type="submit" class="admin-btn-primary w-full sm:w-auto">
                    <i class="ph-bold ph-funnel" aria-hidden="true"></i>
                    Áp dụng
                </button>
                @if($hasActiveFilters)
                    <a href="{{ route('admin.showtimes.index') }}" class="admin-btn-secondary w-full sm:w-auto">Xóa bộ lọc</a>
                @endif
            </div>
        </form>
    </section>

    <section class="admin-table-card" aria-labelledby="showtime-list-title">
        <div class="flex flex-col gap-2 border-b app-border px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
            <div>
                <h2 id="showtime-list-title" class="text-base font-extrabold app-text">Danh sách suất chiếu</h2>
                <p class="mt-1 text-sm app-muted">
                    <span class="font-extrabold app-text">{{ $showtimes->total() }}</span>
                    {{ $hasActiveFilters ? 'suất phù hợp với bộ lọc' : 'suất trong lịch vận hành' }}
                </p>
            </div>
            <p class="flex items-center gap-2 text-xs app-muted">
                <i class="ph-bold ph-broom" aria-hidden="true"></i>
                “Phòng sẵn sàng” đã tính thời gian vệ sinh
            </p>
        </div>

        <div class="hidden xl:block" data-showtime-desktop-list>
            <table class="admin-table !whitespace-normal table-fixed">
                <thead>
                    <tr>
                        <th class="w-[29%]">Phim và phòng</th>
                        <th class="w-[31%]">Thời gian</th>
                        <th class="w-[11%]">Định dạng</th>
                        <th class="w-[12%]">Trạng thái</th>
                        <th class="w-[17%] text-right">Thao tác</th>
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
                            <td class="!whitespace-normal">
                                <span class="block break-words text-sm font-extrabold leading-5 app-text">{{ $showtime->movie->title }}</span>
                                <span class="mt-1 block text-xs leading-5 app-muted">{{ $showtime->room->code }} · {{ $showtime->room->name }} · sơ đồ phiên bản {{ $showtime->roomLayout?->version ?? '?' }}</span>
                            </td>
                            <td class="!whitespace-normal">
                                <dl class="grid grid-cols-2 gap-x-4 gap-y-2">
                                    <div>
                                        <dt class="text-[11px] font-bold uppercase tracking-wide app-muted">Bắt đầu</dt>
                                        <dd class="mt-0.5 text-base font-extrabold app-text">{{ $window?->start->format('H:i') ?? '--:--' }}</dd>
                                        <dd class="text-xs app-muted">{{ $window?->start->format('d/m/Y') ?? 'Thời lượng không hợp lệ' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[11px] font-bold uppercase tracking-wide app-muted">Kết thúc phim</dt>
                                        <dd class="mt-0.5 text-base font-bold app-text">{{ $window?->movieEnd->format('H:i') ?? '--:--' }}</dd>
                                        @if($window)<dd class="text-xs app-muted">{{ $window->movieEnd->format('d/m/Y') }}@if($movieCrossesMidnight) (+1 ngày)@endif</dd>@endif
                                    </div>
                                    <div class="col-span-2 mt-1 flex items-center gap-2 rounded-lg app-secondary px-3 py-2">
                                        <i class="ph-bold ph-broom text-brand-start" aria-hidden="true"></i>
                                        <div>
                                            <dt class="text-[11px] font-bold uppercase tracking-wide app-muted">Phòng sẵn sàng</dt>
                                            <dd class="font-extrabold text-brand-start">
                                                {{ $window?->operationalEnd->format('H:i') ?? '--:--' }}
                                                @if($window)<span class="block text-xs font-bold">{{ $window->operationalEnd->format('d/m/Y') }}@if($readyCrossesMidnight) (+1 ngày)@endif</span>@endif
                                            </dd>
                                        </div>
                                    </div>
                                </dl>
                            </td>
                            <td><span class="status-badge app-secondary app-text">{{ $showtime->presentationFormat?->name ?? 'Chưa xác định' }}</span></td>
                            <td>
                                <span class="status-badge {{ $lifecycleClasses }}" data-showtime-lifecycle data-server-now="{{ ($lifecycle['now'] ?? null)?->toIso8601String() }}" data-start-at="{{ ($lifecycle['starts_at'] ?? null)?->toIso8601String() }}" data-end-at="{{ ($lifecycle['ends_at'] ?? null)?->toIso8601String() }}" data-cancelled="{{ $showtime->status === 'cancelled' ? 'true' : 'false' }}">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>
                                    {{ $lifecycle['label'] ?? 'Không xác định' }}
                                </span>
                            </td>
                            <td class="!whitespace-normal">
                                <div class="flex flex-col items-stretch gap-2">
                                    <a href="{{ route('admin.showtimes.show', $showtime) }}" class="admin-btn-secondary !justify-start whitespace-nowrap" aria-label="Xem chi tiết suất chiếu {{ $showtime->movie->title }}"><i class="ph-bold ph-eye" aria-hidden="true"></i>Xem chi tiết</a>
                                    @can('showtimes.update')
                                        @if($showtime->status === 'active' && ($lifecycle['state'] ?? null) === 'upcoming' && ! $showtime->bookings_exists && ! $showtime->booking_seats_exists)
                                            <a href="{{ route('admin.showtimes.edit', $showtime) }}" data-showtime-edit-action class="admin-btn-secondary !justify-start whitespace-nowrap focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-start" aria-label="Chỉnh sửa suất chiếu {{ $showtime->movie->title }} lúc {{ $window?->start->format('H:i d/m/Y') }}"><i class="ph-bold ph-pencil-simple" aria-hidden="true"></i>Chỉnh sửa</a>
                                        @elseif($showtime->bookings_exists || $showtime->booking_seats_exists)
                                            <span class="inline-flex items-center gap-2 px-1 text-xs font-semibold app-muted" title="Suất chiếu đã có lịch sử đặt vé"><i class="ph-bold ph-lock-key" aria-hidden="true"></i>Đã khóa chỉnh sửa</span>
                                        @endif
                                    @endcan
                                    @can('showtimes.delete')
                                        @if($showtime->status === 'active' && in_array($lifecycle['state'] ?? null, ['upcoming', 'playing'], true))
                                            <a href="{{ route('admin.showtimes.cancellation', $showtime) }}" data-showtime-cancel-action class="admin-btn-danger w-full !justify-start whitespace-nowrap" aria-label="Xem tác động hủy suất chiếu {{ $showtime->movie->title }}">
                                                <i class="ph-bold ph-x-circle" aria-hidden="true"></i>{{ $showtime->bookings_exists ? 'Xử lý hủy' : 'Hủy suất' }}
                                            </a>
                                            @if($showtime->bookings_exists)<span class="px-1 text-[11px] app-muted">{{ $showtime->pending_bookings_count }} chờ · {{ $showtime->paid_bookings_count }} đã trả</span>@endif
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="!whitespace-normal px-6 py-12 text-center">
                                <div class="mx-auto max-w-md">
                                    <i class="ph-duotone ph-calendar-x text-4xl text-brand-start" aria-hidden="true"></i>
                                    <h3 class="mt-3 text-lg font-extrabold app-text">Không tìm thấy suất chiếu phù hợp.</h3>
                                    <p class="mt-2 text-sm leading-6 app-muted">Hãy thử đổi ngày, chọn phim khác hoặc xóa bớt bộ lọc.</p>
                                    <div class="mt-4 flex flex-wrap justify-center gap-2">
                                        @if($hasActiveFilters)<a href="{{ route('admin.showtimes.index') }}" class="admin-btn-secondary">Xóa bộ lọc</a>@endif
                                        @can('showtimes.create')<a href="{{ route('admin.showtimes.create') }}" class="admin-btn-primary">Thêm suất chiếu</a>@endcan
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="divide-y app-border xl:hidden" data-showtime-mobile-list>
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
                <article class="p-4 sm:p-5" data-showtime-card>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="break-words text-base font-extrabold leading-6 app-text">{{ $showtime->movie->title }}</h3>
                            <p class="mt-1 text-sm app-muted">{{ $showtime->room->code }} · {{ $showtime->room->name }} · sơ đồ phiên bản {{ $showtime->roomLayout?->version ?? '?' }}</p>
                        </div>
                        <span class="status-badge shrink-0 {{ $lifecycleClasses }}"><span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>{{ $lifecycle['label'] ?? 'Không xác định' }}</span>
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-3 rounded-xl app-secondary p-3">
                        <div>
                            <dt class="text-[11px] font-bold uppercase tracking-wide app-muted">Bắt đầu</dt>
                            <dd class="mt-1 text-lg font-extrabold app-text">{{ $window?->start->format('H:i') ?? '--:--' }}</dd>
                            <dd class="text-xs app-muted">{{ $window?->start->format('d/m/Y') ?? 'Thời lượng không hợp lệ' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-bold uppercase tracking-wide app-muted">Kết thúc phim</dt>
                            <dd class="mt-1 text-lg font-bold app-text">{{ $window?->movieEnd->format('H:i') ?? '--:--' }}</dd>
                            @if($window)<dd class="text-xs app-muted">{{ $window->movieEnd->format('d/m/Y') }}@if($movieCrossesMidnight) (+1 ngày)@endif</dd>@endif
                        </div>
                        <div class="col-span-2 flex items-center justify-between gap-3 border-t app-border pt-3">
                            <dt class="flex items-center gap-2 text-sm font-bold app-muted"><i class="ph-bold ph-broom" aria-hidden="true"></i>Phòng sẵn sàng</dt>
                            <dd class="text-right text-base font-extrabold text-brand-start">{{ $window?->operationalEnd->format('H:i') ?? '--:--' }}@if($window)<span class="block text-xs">{{ $window->operationalEnd->format('d/m/Y') }}@if($readyCrossesMidnight) (+1 ngày)@endif</span>@endif</dd>
                        </div>
                    </dl>

                    <div class="mt-3 flex items-center justify-between gap-3"><span class="text-xs font-bold app-muted">Định dạng</span><span class="status-badge app-secondary app-text">{{ $showtime->presentationFormat?->name ?? 'Chưa xác định' }}</span></div>

                    <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <a href="{{ route('admin.showtimes.show', $showtime) }}" class="admin-btn-secondary" aria-label="Xem chi tiết suất chiếu {{ $showtime->movie->title }}"><i class="ph-bold ph-eye" aria-hidden="true"></i>Xem chi tiết</a>
                        @can('showtimes.update')
                            @if($showtime->status === 'active' && ($lifecycle['state'] ?? null) === 'upcoming' && ! $showtime->bookings_exists && ! $showtime->booking_seats_exists)
                                <a href="{{ route('admin.showtimes.edit', $showtime) }}" data-showtime-edit-action class="admin-btn-secondary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-start" aria-label="Chỉnh sửa suất chiếu {{ $showtime->movie->title }} lúc {{ $window?->start->format('H:i d/m/Y') }}"><i class="ph-bold ph-pencil-simple" aria-hidden="true"></i>Chỉnh sửa</a>
                            @elseif($showtime->bookings_exists || $showtime->booking_seats_exists)
                                <span class="inline-flex items-center justify-center gap-2 rounded-xl border app-border px-3 py-2 text-xs font-semibold app-muted"><i class="ph-bold ph-lock-key" aria-hidden="true"></i>Đã khóa chỉnh sửa</span>
                            @endif
                        @endcan
                        @can('showtimes.delete')
                            @if($showtime->status === 'active' && in_array($lifecycle['state'] ?? null, ['upcoming', 'playing'], true))
                                <a href="{{ route('admin.showtimes.cancellation', $showtime) }}" class="admin-btn-danger sm:col-span-2" data-showtime-cancel-action aria-label="Xem tác động hủy suất chiếu {{ $showtime->movie->title }}"><i class="ph-bold ph-x-circle" aria-hidden="true"></i>{{ $showtime->bookings_exists ? 'Xử lý hủy' : 'Hủy suất' }}</a>
                                @if($showtime->bookings_exists)<p class="text-center text-xs app-muted sm:col-span-2">{{ $showtime->pending_bookings_count }} đơn chờ · {{ $showtime->paid_bookings_count }} đơn đã trả</p>@endif
                            @endif
                        @endcan
                    </div>
                </article>
            @empty
                <div class="px-5 py-12 text-center">
                    <i class="ph-duotone ph-calendar-x text-4xl text-brand-start" aria-hidden="true"></i>
                    <h3 class="mt-3 text-lg font-extrabold app-text">Không tìm thấy suất chiếu phù hợp.</h3>
                    <p class="mt-2 text-sm leading-6 app-muted">Hãy thử đổi ngày, chọn phim khác hoặc xóa bớt bộ lọc.</p>
                    <div class="mt-4 flex flex-wrap justify-center gap-2">
                        @if($hasActiveFilters)<a href="{{ route('admin.showtimes.index') }}" class="admin-btn-secondary">Xóa bộ lọc</a>@endif
                        @can('showtimes.create')<a href="{{ route('admin.showtimes.create') }}" class="admin-btn-primary">Thêm suất chiếu</a>@endcan
                    </div>
                </div>
            @endforelse
        </div>

        @if($showtimes->hasPages())
            <div class="border-t app-border p-4 sm:p-5">{{ $showtimes->links() }}</div>
        @endif
    </section>
</div>
@endsection
