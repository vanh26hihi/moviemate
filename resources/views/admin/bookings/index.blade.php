@extends('layouts.admin')

@section('title', 'Đơn đặt vé - MovieMate')
@section('page-title', 'Đơn đặt vé')

@section('content')
<div class="space-y-6">
    <header>
        <h1 class="text-2xl font-extrabold app-heading sm:text-3xl">Đơn đặt vé</h1>
        <p class="mt-2 app-muted">Quản lý các đơn đã thanh toán, gửi email và in tài liệu tại quầy.</p>
    </header>

    <section class="grid gap-3 sm:grid-cols-3" aria-label="Tổng hợp đơn thành công">
        @foreach([
            ['Đơn thành công', number_format($summary['total']), 'ph-check-circle', 'text-success'],
            ['Doanh thu', number_format($summary['revenue'], 0, ',', '.').' VNĐ', 'ph-trend-up', 'text-brand-start'],
            ['Chỗ đã bán', number_format($summary['seats']), 'ph-armchair', 'text-ai-start'],
        ] as [$label, $value, $icon, $color])
            <article class="cinema-card flex items-center gap-4 p-4 sm:p-5">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-current/10 {{ $color }}"><i class="ph {{ $icon }} text-xl" aria-hidden="true"></i></span>
                <div class="min-w-0"><p class="text-sm app-muted">{{ $label }}</p><p class="mt-1 truncate text-xl font-black app-text">{{ $value }}</p></div>
            </article>
        @endforeach
    </section>

    <form method="GET" action="{{ route('admin.bookings.index') }}" class="cinema-card p-4 sm:p-5" aria-label="Bộ lọc đơn đặt vé">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <label class="cinema-label xl:col-span-2">Tìm mã đơn / khách hàng
                <input class="cinema-input mt-1" name="search" maxlength="120" value="{{ $filters['search'] ?? '' }}" placeholder="Mã đơn, tên, email hoặc số điện thoại">
            </label>
            <label class="cinema-label">Chi nhánh
                <select class="cinema-input mt-1" name="cinema_id"><option value="">Tất cả chi nhánh được phép</option>@foreach($cinemas as $cinema)<option value="{{ $cinema->id }}" @selected((int) ($filters['cinema_id'] ?? 0) === $cinema->id)>{{ $cinema->name }}</option>@endforeach</select>
            </label>
            <label class="cinema-label">Kênh bán
                <select class="cinema-input mt-1" name="sales_channel"><option value="">Tất cả kênh</option><option value="online" @selected(($filters['sales_channel'] ?? '') === 'online')>Online</option><option value="counter" @selected(($filters['sales_channel'] ?? '') === 'counter')>Tại quầy</option></select>
            </label>
            <label class="cinema-label">Từ ngày<input class="cinema-input mt-1" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
            <label class="cinema-label">Đến ngày<input class="cinema-input mt-1" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
            <label class="cinema-label">Trạng thái vé
                <select class="cinema-input mt-1" name="ticket_status"><option value="">Tất cả</option><option value="sent" @selected(($filters['ticket_status'] ?? '') === 'sent')>Đã gửi</option><option value="pending" @selected(($filters['ticket_status'] ?? '') === 'pending')>Đang chờ gửi</option><option value="processing" @selected(($filters['ticket_status'] ?? '') === 'processing')>Đang gửi</option><option value="failed" @selected(($filters['ticket_status'] ?? '') === 'failed')>Gửi lỗi</option><option value="none" @selected(($filters['ticket_status'] ?? '') === 'none')>Không có email</option></select>
            </label>
        </div>
        <details class="mt-3 rounded-xl border app-border px-4 py-3" @if(isset($filters['sort']) || isset($filters['direction']) || isset($filters['per_page'])) open @endif>
            <summary class="cursor-pointer text-sm font-bold app-text">Bộ lọc nâng cao</summary>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <label class="cinema-label">Sắp xếp<select class="cinema-input mt-1" name="sort"><option value="paid_at" @selected(($filters['sort'] ?? 'paid_at') === 'paid_at')>Thời gian thanh toán</option><option value="show_date" @selected(($filters['sort'] ?? '') === 'show_date')>Ngày chiếu</option><option value="booking_code" @selected(($filters['sort'] ?? '') === 'booking_code')>Mã đơn</option><option value="total_amount" @selected(($filters['sort'] ?? '') === 'total_amount')>Tổng tiền</option></select></label>
                <label class="cinema-label">Thứ tự<select class="cinema-input mt-1" name="direction"><option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>Mới / cao trước</option><option value="asc" @selected(($filters['direction'] ?? '') === 'asc')>Cũ / thấp trước</option></select></label>
                <label class="cinema-label">Số dòng<select class="cinema-input mt-1" name="per_page">@foreach([15,25,50] as $size)<option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 25) === $size)>{{ $size }}</option>@endforeach</select></label>
            </div>
        </details>
        <div class="mt-4 flex flex-wrap gap-2"><button type="submit" class="btn-primary"><i class="ph ph-funnel" aria-hidden="true"></i>Lọc</button><a href="{{ route('admin.bookings.index') }}" class="btn-secondary">Đặt lại</a></div>
    </form>

    <div class="cinema-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="admin-table min-w-[64rem]">
                <thead><tr><th>Mã đơn</th><th>Khách hàng</th><th>Phim / Suất chiếu</th><th>Chi nhánh / Phòng</th><th>Ghế</th><th>Kênh bán</th><th class="text-right">Tổng tiền</th><th>Vé</th><th class="text-right">Thao tác</th></tr></thead>
                <tbody>
                    @forelse($bookings as $booking)
                        <tr>
                            <td><a class="font-extrabold text-brand-start" href="{{ route('admin.bookings.show', $booking) }}">{{ $booking->booking_code }}</a><span class="mt-1 block text-xs app-muted">{{ $booking->successful_paid_at ? \Carbon\Carbon::parse($booking->successful_paid_at)->format('d/m/Y H:i') : '—' }}</span></td>
                            <td><span class="font-bold app-text">{{ $booking->user?->name ?? $booking->customer_name ?? 'Khách đặt vé' }}</span><span class="mt-1 block text-xs app-muted">{{ $booking->recipient_email ? \App\Support\PrivacyMask::email($booking->recipient_email) : \App\Support\PrivacyMask::phone($booking->customer_phone) }}</span></td>
                            <td><span class="font-bold app-text">{{ $booking->showtime?->movie?->title ?? 'Thông tin không còn khả dụng' }}</span><span class="mt-1 block text-xs app-muted">{{ $booking->showtime?->show_time ? \Carbon\Carbon::parse($booking->showtime->show_time)->format('H:i') : '—' }} · {{ $booking->showtime?->show_date?->format('d/m/Y') ?? '—' }}</span></td>
                            <td><span class="font-bold app-text">{{ $booking->showtime?->cinema?->name ?? '—' }}</span><span class="mt-1 block text-xs app-muted">{{ $booking->showtime?->room?->name ?? $booking->showtime?->room?->code ?? '—' }}</span></td>
                            <td class="max-w-52">{{ $booking->seat_codes ?: '—' }}</td>
                            <td><span class="status-badge {{ $booking->sales_channel === 'counter' ? 'bg-ai-start/10 text-ai-start' : 'bg-success/10 text-success' }}">{{ $booking->sales_channel === 'counter' ? 'Tại quầy' : 'Online' }}</span></td>
                            <td class="text-right whitespace-nowrap"><span class="font-extrabold app-text">{{ number_format((int) $booking->total_amount, 0, ',', '.') }} VNĐ</span><span class="mt-1 block text-xs text-success">Đã thanh toán</span></td>
                            <td><span class="block font-bold app-text">{{ $booking->ticketPrint?->status === 'printed' ? 'Đã in' : 'Chưa in' }} · {{ $booking->ticketDelivery?->status === 'sent' ? 'Đã gửi email' : ($booking->ticketDelivery?->status === 'failed' ? 'Gửi email lỗi' : 'Email đang chờ') }}</span></td>
                            <td class="text-right"><a class="btn-secondary !px-3 !py-2 text-xs" href="{{ route('admin.bookings.show', $booking) }}">Xem chi tiết</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="py-12 text-center app-muted">Chưa có đơn đặt vé thành công phù hợp.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($bookings->hasPages())<div class="border-t app-border px-5 py-4">{{ $bookings->links() }}</div>@endif
    </div>
</div>
@endsection
