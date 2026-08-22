@extends('layouts.admin')

@section('title', 'Chi tiết suất chiếu - MovieMate')
@section('page-title', 'Chi tiết suất chiếu')

@section('content')
@php
    $lifecycleClasses = match($lifecycle['state']) {
        'upcoming' => 'text-brand-start bg-brand-start/10',
        'playing' => 'text-success bg-success/10',
        'completed' => 'app-muted app-secondary',
        'cancelled' => 'text-error bg-error/10',
        default => 'text-warning bg-warning/10',
    };
    $sourceVersions = $showtime->ticketPrices
        ->pluck('priceBookVersion')
        ->filter()
        ->unique('id')
        ->values();
@endphp

<div class="space-y-6">
    <header class="admin-page-header items-start">
        <div class="min-w-0">
            <a href="{{ route('admin.showtimes.index') }}" class="mb-3 inline-flex items-center gap-2 text-sm font-semibold app-muted hover:text-brand-start"><i class="ph ph-arrow-left" aria-hidden="true"></i>Về lịch vận hành</a>
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-start">Suất chiếu #{{ $showtime->id }}</p>
            <h1 class="admin-page-title mt-2 break-words">{{ $showtime->movie->title }}</h1>
            <p class="admin-page-subtitle">{{ $lifecycle['starts_at']->format('d/m/Y H:i') }} · {{ $showtime->cinema->name }} · {{ $showtime->presentationFormat->name }}</p>
        </div>
        <div class="flex w-full flex-wrap gap-2 sm:w-auto">
            @can('showtimes.update')
                @if($canEdit)
                    <a href="{{ route('admin.showtimes.edit', $showtime) }}" class="btn-secondary"><i class="ph ph-pencil-simple" aria-hidden="true"></i>Chỉnh sửa lịch</a>
                @endif
            @endcan
            @can('showtimes.delete')
                @if($canCancel)
                    <form method="POST" action="{{ route('admin.showtimes.destroy', $showtime) }}" onsubmit="return confirm('Bạn có chắc muốn hủy suất chiếu này?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-secondary text-error"><i class="ph ph-x-circle" aria-hidden="true"></i>Hủy suất chiếu</button>
                    </form>
                @endif
            @endcan
        </div>
    </header>

    @if($hasBookingHistory)
        <div class="rounded-2xl border border-warning/30 bg-warning/10 p-4 text-sm">
            <p class="font-bold app-text">Suất chiếu đã có lịch sử đặt vé.</p>
            <p class="mt-1 app-muted">Tác vụ thay đổi cấu trúc lịch không được hiển thị để bảo toàn lịch sử.</p>
        </div>
    @endif

    <section class="grid gap-4 md:grid-cols-3" aria-label="Tổng quan suất chiếu">
        <div class="cinema-card p-5">
            <p class="text-sm app-muted">Trạng thái lưu trữ</p>
            <p class="mt-2 font-extrabold app-text">{{ $showtime->status_label }}</p>
        </div>
        <div class="cinema-card p-5">
            <p class="text-sm app-muted">Vòng đời phim</p>
            <p class="mt-2"><span class="status-badge {{ $lifecycleClasses }}" data-showtime-state="{{ $lifecycle['state'] }}">{{ $lifecycle['label'] }}</span></p>
        </div>
        <div class="cinema-card p-5">
            <p class="text-sm app-muted">Trạng thái phòng</p>
            <p class="mt-2 font-extrabold app-text" data-room-state="{{ $roomState['key'] }}">{{ $roomState['label'] }}</p>
        </div>
    </section>

    <section class="cinema-card p-5 sm:p-6" aria-labelledby="showtime-timing-title">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="showtime-timing-title" class="text-xl font-extrabold app-heading">Thời gian vận hành</h2>
            <span class="text-sm app-muted">Múi giờ {{ $lifecycle['starts_at']->getTimezone()->getName() }}</span>
        </div>
        <dl class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div><dt class="text-sm app-muted">Ngày nghiệp vụ</dt><dd class="mt-1 font-bold app-text">{{ $lifecycle['starts_at']->format('d/m/Y') }}</dd></div>
            <div><dt class="text-sm app-muted">Bắt đầu</dt><dd class="mt-1 font-bold app-text">{{ $lifecycle['starts_at']->format('d/m/Y H:i') }}</dd></div>
            <div><dt class="text-sm app-muted">Kết thúc phim</dt><dd class="mt-1 font-bold app-text">{{ $lifecycle['ends_at']->format('d/m/Y H:i') }}</dd></div>
            <div><dt class="text-sm app-muted">Thời gian vệ sinh</dt><dd class="mt-1 font-bold app-text">{{ $lifecycle['window']->cleaningBufferMinutes }} phút</dd></div>
            <div><dt class="text-sm app-muted">Phòng sẵn sàng</dt><dd class="mt-1 font-bold text-brand-start">{{ $lifecycle['room_ready_at']->format('d/m/Y H:i') }}</dd></div>
        </dl>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="cinema-card p-5 sm:p-6" aria-labelledby="showtime-movie-title">
            <h2 id="showtime-movie-title" class="text-xl font-extrabold app-heading">Phim</h2>
            <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-sm app-muted">Tựa phim</dt><dd class="mt-1 font-bold app-text">@can('movies.view')<a class="hover:text-brand-start" href="{{ route('admin.movies.show', $showtime->movie) }}">{{ $showtime->movie->title }}</a>@else{{ $showtime->movie->title }}@endcan</dd></div>
                <div><dt class="text-sm app-muted">Thời lượng</dt><dd class="mt-1 font-bold app-text">{{ $showtime->movie->duration }} phút</dd></div>
                <div><dt class="text-sm app-muted">Phân loại</dt><dd class="mt-1 font-bold app-text">{{ $showtime->movie->age_rating ?: '—' }}</dd></div>
            </dl>
        </section>

        <section class="cinema-card p-5 sm:p-6" aria-labelledby="showtime-room-title">
            <h2 id="showtime-room-title" class="text-xl font-extrabold app-heading">Phòng & cấu hình trình chiếu</h2>
            <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-sm app-muted">Chi nhánh</dt><dd class="mt-1 font-bold app-text">{{ $showtime->cinema->name }}</dd></div>
                <div><dt class="text-sm app-muted">Phòng</dt><dd class="mt-1 font-bold app-text">@can('rooms.view')<a class="hover:text-brand-start" href="{{ route('admin.rooms.show', $showtime->room) }}">{{ $showtime->room->code }} · {{ $showtime->room->name }}</a>@else{{ $showtime->room->code }} · {{ $showtime->room->name }}@endcan</dd></div>
                <div><dt class="text-sm app-muted">Loại phòng</dt><dd class="mt-1 font-bold app-text">{{ $showtime->room->room_type_label }}</dd></div>
                <div><dt class="text-sm app-muted">Định dạng trình chiếu</dt><dd class="mt-1 font-bold app-text">{{ $showtime->presentationFormat->code }} · {{ $showtime->presentationFormat->name }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-sm app-muted">Sơ đồ phòng đã ghim</dt><dd class="mt-1 font-bold app-text">{{ $showtime->roomLayout->display_name }} · Phiên bản {{ $showtime->roomLayout->version }} · {{ $showtime->roomLayout->status_label }}</dd><p class="mt-1 text-xs app-muted">Dùng đúng phiên bản đã ghim cho suất chiếu; không tự chuyển sang sơ đồ mới nhất.</p></div>
            </dl>
        </section>
    </div>

    <section data-showtime-price-snapshots class="cinema-card overflow-hidden" aria-labelledby="showtime-price-title">
        <div class="border-b app-border p-5 sm:p-6">
            <h2 id="showtime-price-title" class="text-xl font-extrabold app-heading">Giá đã khóa cho suất chiếu</h2>
            <p class="mt-1 text-sm app-muted">Snapshot cấu hình bán theo từng đơn vị logic; không lấy lại giá từ bảng giá hiện tại.</p>
            @if($sourceVersions->count() === 1)
                <p class="mt-2 text-sm font-bold text-brand-start">Nguồn bảng giá: v{{ $sourceVersions->first()->version_number }}</p>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="admin-table">
                <thead><tr><th scope="col">Loại ghế</th><th scope="col">Đơn vị tính</th><th scope="col" class="text-right">Số tiền đã khóa</th></tr></thead>
                <tbody>
                    @forelse($showtime->ticketPrices->sortBy(fn($price) => [$price->seatType?->sort_order ?? 999, $price->seat_type_id]) as $price)
                        <tr data-showtime-price-row>
                            <td class="font-bold app-text">{{ $price->seatType?->name ?? $price->seatType?->code ?? 'Loại ghế lịch sử' }}</td>
                            <td>{{ $price->seatType?->is_pair ? 'Một đơn vị logic = một cặp ghế vật lý' : 'Một ghế vật lý' }}</td>
                            <td class="text-right font-extrabold text-brand-start">{{ number_format((int) $price->final_unit_amount_vnd, 0, ',', '.') }} VNĐ</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-8 text-center app-muted">Không còn dữ liệu snapshot giá cho suất chiếu.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="cinema-card overflow-hidden" aria-labelledby="showtime-bookings-title">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b app-border p-5 sm:p-6">
            <div>
                <h2 id="showtime-bookings-title" class="text-xl font-extrabold app-heading">Đơn đặt vé liên quan</h2>
                <p class="mt-1 text-sm app-muted">Tổng đơn đặt vé: <strong class="app-text">{{ $bookingCount }} đơn</strong>. Hiển thị tối đa 10 đơn gần nhất.</p>
            </div>
        </div>
        @if($recentBookings->isEmpty())
            <p class="p-6 app-muted">Chưa có đơn đặt vé cho suất chiếu này.</p>
        @else
            <div class="overflow-x-auto">
                <table class="admin-table min-w-[56rem]">
                    <thead><tr><th scope="col">Mã đơn</th><th scope="col">Khách / kênh</th><th scope="col">Trạng thái</th><th scope="col" class="text-center">Ghế</th><th scope="col" class="text-right">Tổng cuối cùng</th><th scope="col" class="text-right">Thao tác</th></tr></thead>
                    <tbody>
                        @foreach($recentBookings as $booking)
                            <tr>
                                <td class="font-extrabold app-text">{{ $booking->booking_code }}</td>
                                <td><span class="block font-semibold app-text">{{ $booking->customer_name ?: 'Khách đặt vé' }}</span><span class="text-xs app-muted">{{ $booking->sales_channel === 'counter' ? 'Tại quầy' : 'Online' }}</span></td>
                                <td>{{ \App\Support\StatusLabel::for('booking_admin', $booking->booking_status) }}</td>
                                <td class="text-center">{{ $booking->booking_seats_count }}</td>
                                <td class="text-right font-bold app-text">{{ number_format((int) $booking->total_amount, 0, ',', '.') }} {{ strtoupper($booking->currency ?: 'VND') === 'VND' ? 'VNĐ' : strtoupper($booking->currency) }}</td>
                                <td class="text-right"><a class="admin-btn-secondary whitespace-nowrap" href="{{ route('admin.bookings.show', $booking) }}">Mở đơn</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
