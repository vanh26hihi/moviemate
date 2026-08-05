@extends('layouts.admin')

@section('title', 'Đơn đặt vé - MovieMate')
@section('page-title', 'Đơn đặt vé')

@section('content')
<div class="space-y-6">
    <header>
        <h1 class="text-3xl font-extrabold app-heading">Đơn đặt vé</h1>
        <p class="mt-2 app-muted">Theo dõi đơn đặt vé, thanh toán, vé điện tử và trạng thái soát vé.</p>
    </header>

    <form method="GET" action="{{ route('admin.bookings.index') }}" class="admin-toolbar grid gap-3 md:grid-cols-2 xl:grid-cols-4" aria-label="Bộ lọc đơn đặt vé">
        <label class="cinema-label">Mã đặt vé
            <input class="cinema-input mt-1" name="booking_code" maxlength="60" value="{{ $filters['booking_code'] ?? '' }}" placeholder="Ví dụ: MM-2026">
        </label>
        <label class="cinema-label">Tên khách hàng
            <input class="cinema-input mt-1" name="customer_name" maxlength="120" value="{{ $filters['customer_name'] ?? '' }}" placeholder="Tên tài khoản hoặc người nhận">
        </label>
        <label class="cinema-label">Email khách hàng
            <input class="cinema-input mt-1" type="email" name="customer_email" maxlength="191" value="{{ $filters['customer_email'] ?? '' }}" placeholder="Tìm chính xác theo email">
        </label>
        <label class="cinema-label">Phim
            <select class="cinema-input mt-1" name="movie_id">
                <option value="">Tất cả phim</option>
                @foreach($movies as $movie)<option value="{{ $movie->id }}" @selected((int) ($filters['movie_id'] ?? 0) === $movie->id)>{{ $movie->title }}</option>@endforeach
            </select>
        </label>
        <label class="cinema-label">Phòng
            <select class="cinema-input mt-1" name="room_id">
                <option value="">Tất cả phòng</option>
                @foreach($rooms as $room)<option value="{{ $room->id }}" @selected((int) ($filters['room_id'] ?? 0) === $room->id)>{{ $room->code }} · {{ $room->name }}</option>@endforeach
            </select>
        </label>
        <label class="cinema-label">Ngày chiếu<input class="cinema-input mt-1" type="date" name="show_date" value="{{ $filters['show_date'] ?? '' }}"></label>
        <label class="cinema-label">Tạo từ ngày<input class="cinema-input mt-1" type="date" name="created_from" value="{{ $filters['created_from'] ?? '' }}"></label>
        <label class="cinema-label">Tạo đến ngày<input class="cinema-input mt-1" type="date" name="created_to" value="{{ $filters['created_to'] ?? '' }}"></label>
        <label class="cinema-label">Trạng thái đặt vé
            <select class="cinema-input mt-1" name="booking_status">
                <option value="">Tất cả trạng thái</option>
                @foreach(\App\Models\Booking::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['booking_status'] ?? '') === $status)>{{ \App\Support\StatusLabel::for('booking_admin', $status) }}</option>@endforeach
            </select>
        </label>
        <label class="cinema-label">Trạng thái thanh toán
            <select class="cinema-input mt-1" name="payment_status">
                <option value="">Tất cả trạng thái</option>
                @foreach(\App\Models\Booking::PAYMENT_STATUSES as $status)<option value="{{ $status }}" @selected(($filters['payment_status'] ?? '') === $status)>{{ \App\Support\StatusLabel::for('booking_payment', $status) }}</option>@endforeach
            </select>
        </label>
        <label class="cinema-label">Trạng thái gửi vé
            <select class="cinema-input mt-1" name="ticket_status">
                <option value="">Tất cả trạng thái</option>
                @foreach(\App\Models\BookingTicketDelivery::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['ticket_status'] ?? '') === $status)>{{ \App\Support\StatusLabel::for('ticket_delivery', $status) }}</option>@endforeach
            </select>
        </label>
        <label class="cinema-label">Trạng thái soát vé
            <select class="cinema-input mt-1" name="checkin_status">
                <option value="">Tất cả trạng thái</option>
                <option value="used" @selected(($filters['checkin_status'] ?? '') === 'used')>Đã soát vé</option>
                <option value="not_used" @selected(($filters['checkin_status'] ?? '') === 'not_used')>Chưa soát vé</option>
            </select>
        </label>
        <label class="cinema-label">Sắp xếp
            <select class="cinema-input mt-1" name="sort">
                <option value="created_at" @selected(($filters['sort'] ?? 'created_at') === 'created_at')>Ngày tạo</option>
                <option value="show_date" @selected(($filters['sort'] ?? '') === 'show_date')>Ngày chiếu</option>
                <option value="booking_code" @selected(($filters['sort'] ?? '') === 'booking_code')>Mã đặt vé</option>
                <option value="total_amount" @selected(($filters['sort'] ?? '') === 'total_amount')>Tổng thanh toán</option>
            </select>
        </label>
        <label class="cinema-label">Thứ tự
            <select class="cinema-input mt-1" name="direction">
                <option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>Mới / cao trước</option>
                <option value="asc" @selected(($filters['direction'] ?? '') === 'asc')>Cũ / thấp trước</option>
            </select>
        </label>
        <label class="cinema-label">Số dòng
            <select class="cinema-input mt-1" name="per_page">
                @foreach([15, 25, 50] as $size)<option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 25) === $size)>{{ $size }}</option>@endforeach
            </select>
        </label>
        <div class="flex flex-wrap items-end gap-2">
            <button type="submit" class="btn-primary"><i class="ph ph-funnel" aria-hidden="true"></i>Lọc</button>
            <a href="{{ route('admin.bookings.index') }}" class="btn-secondary">Xóa bộ lọc</a>
        </div>
    </form>

    <div class="cinema-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="admin-table min-w-[118rem]">
                <thead><tr>
                    <th>Mã đặt vé</th><th>Khách hàng</th><th>Phim</th><th>Suất chiếu</th><th>Phòng</th><th>Ghế</th>
                    <th class="text-right">Tiền ghế</th><th class="text-right">Tiền đồ ăn</th><th class="text-right">Tổng thanh toán</th>
                    <th>Đặt vé</th><th>Thanh toán</th><th>Vé điện tử</th><th>Soát vé</th><th>Ngày tạo</th><th class="text-right">Thao tác</th>
                </tr></thead>
                <tbody>
                    @forelse($bookings as $booking)
                        <tr>
                            <td><a class="font-extrabold text-brand-start" href="{{ route('admin.bookings.show', $booking) }}">{{ $booking->booking_code }}</a></td>
                            <td><span class="font-bold app-text">{{ $booking->user?->name ?? 'Khách đặt vé' }}</span><span class="mt-1 block text-xs app-muted">{{ \App\Support\PrivacyMask::email($booking->recipient_email) }}</span></td>
                            <td>{{ $booking->showtime?->movie?->title ?? 'Thông tin không còn khả dụng' }}</td>
                            <td>{{ $booking->showtime?->show_date?->format('d/m/Y') ?? '—' }}<span class="block text-xs app-muted">{{ $booking->showtime?->show_time ? \Carbon\Carbon::parse($booking->showtime->show_time)->format('H:i') : '—' }}</span></td>
                            <td>{{ $booking->showtime?->room?->code ?? '—' }}</td>
                            <td class="max-w-64">{{ $booking->seat_codes ?: '—' }}</td>
                            <td class="text-right whitespace-nowrap">{{ number_format((int) $booking->seat_subtotal, 0, ',', '.') }} VNĐ</td>
                            <td class="text-right whitespace-nowrap">{{ number_format((int) $booking->food_subtotal, 0, ',', '.') }} VNĐ</td>
                            <td class="text-right whitespace-nowrap font-extrabold app-text">{{ number_format((int) $booking->total_amount, 0, ',', '.') }} VNĐ</td>
                            <td><span class="status-badge bg-ai-start/10 text-ai-start">{{ \App\Support\StatusLabel::for('booking_admin', $booking->booking_status) }}</span></td>
                            <td><span class="status-badge {{ $booking->payment_status === 'paid' ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning' }}">{{ $booking->payment_status_label }}</span></td>
                            <td>{{ $booking->ticketDelivery?->status_label ?? 'Chưa có yêu cầu gửi' }}</td>
                            <td>{{ $booking->booking_status === 'used' ? 'Đã soát vé' : 'Chưa soát vé' }}</td>
                            <td class="whitespace-nowrap">{{ $booking->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="text-right"><a class="btn-secondary !px-3 !py-2 text-xs" href="{{ route('admin.bookings.show', $booking) }}">Xem chi tiết</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="15" class="py-12 text-center app-muted">Không có đơn đặt vé phù hợp.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($bookings->hasPages())<div class="border-t app-border px-5 py-4">{{ $bookings->links() }}</div>@endif
    </div>
</div>
@endsection
