@extends('layouts.admin')

@section('title', 'Gửi vé '.$booking?->booking_code.' - MovieMate')
@section('page-title', 'Chi tiết gửi vé điện tử')

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"><div><a class="mb-3 inline-flex items-center gap-2 font-bold text-brand-start" href="{{ route('admin.ticket-deliveries.index') }}"><i class="ph ph-arrow-left"></i>Danh sách gửi vé</a><h1 class="admin-page-title">{{ $booking?->booking_code ?? 'Booking không còn khả dụng' }}</h1><p class="admin-page-subtitle">Thông tin an toàn từ outbox gửi vé hiện hữu.</p></div>
        <div class="flex flex-wrap gap-2">
            @can('bookings.view')
            @if($booking)
                <a class="btn-secondary" href="{{ route('admin.bookings.show', $booking) }}">Xem booking</a>
            @endif
            @endcan
            @can('tickets.print')
                @if($booking && $eligible)<a class="btn-secondary" href="{{ route('staff.tickets.operations', $booking) }}">Vận hành in vé</a>@endif
            @endcan
            @can('ticket_deliveries.retry')
                @if($retryAllowed)
                    <form method="POST" action="{{ route('admin.ticket-deliveries.retry', $delivery) }}" onsubmit="return confirm('Đưa yêu cầu gửi vé này vào hàng đợi an toàn?');">
                        @csrf
                        <button class="btn-primary" type="submit"><i class="ph ph-arrow-clockwise"></i>Thử gửi lại</button>
                    </form>
                @endif
            @endcan
        </div>
    </header>

    <section class="cinema-card p-6"><h2 class="text-xl font-extrabold app-heading">Tóm tắt gửi vé</h2><dl class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div><dt class="app-muted">Mã đặt vé</dt><dd class="font-bold">{{ $booking?->booking_code ?? '—' }}</dd></div><div><dt class="app-muted">Người nhận</dt><dd class="font-bold">{{ $recipientMasked }}</dd></div><div><dt class="app-muted">Trạng thái</dt><dd class="font-bold">{{ $delivery->status_label }}</dd></div><div><dt class="app-muted">Số lần thử</dt><dd class="font-bold">{{ $delivery->attempts }}</dd></div>
        <div><dt class="app-muted">Claim lúc</dt><dd class="font-bold">{{ $delivery->processing_started_at?->format('d/m/Y H:i:s') ?? '—' }}</dd></div><div><dt class="app-muted">Hết lease lúc</dt><dd class="font-bold">{{ $delivery->lease_expires_at?->format('d/m/Y H:i:s') ?? '—' }}</dd></div><div><dt class="app-muted">Thử lại lúc</dt><dd class="font-bold">{{ $delivery->available_at?->format('d/m/Y H:i:s') ?? '—' }}</dd></div><div><dt class="app-muted">Gửi thành công</dt><dd class="font-bold">{{ $delivery->sent_at?->format('d/m/Y H:i:s') ?? '—' }}</dd></div>
        <div><dt class="app-muted">Tạo lúc</dt><dd class="font-bold">{{ $delivery->created_at?->format('d/m/Y H:i:s') ?? '—' }}</dd></div><div><dt class="app-muted">Cập nhật gần nhất</dt><dd class="font-bold">{{ $delivery->updated_at?->format('d/m/Y H:i:s') ?? '—' }}</dd></div>
    </dl></section>

    <div class="grid gap-6 xl:grid-cols-2"><section class="cinema-card p-6"><h2 class="text-xl font-extrabold app-heading">Booking</h2><dl class="mt-4 grid gap-4 sm:grid-cols-2"><div><dt class="app-muted">Phim</dt><dd class="font-bold">{{ $booking?->showtime?->movie?->title ?? '—' }}</dd></div><div><dt class="app-muted">Suất chiếu</dt><dd class="font-bold">{{ $booking?->showtime_label ?? '—' }}</dd></div><div><dt class="app-muted">Phòng</dt><dd class="font-bold">{{ $booking?->showtime?->room?->code ?? '—' }}</dd></div><div><dt class="app-muted">Ghế</dt><dd class="font-bold">{{ $seatGroups->pluck('label')->join(', ') ?: '—' }}</dd></div><div><dt class="app-muted">Booking</dt><dd class="font-bold">{{ \App\Support\StatusLabel::for('booking_admin', $booking?->booking_status) }}</dd></div><div><dt class="app-muted">Thanh toán</dt><dd class="font-bold">{{ \App\Support\StatusLabel::for('booking_payment', $booking?->payment_status) }}</dd></div></dl></section>
        <section class="cinema-card p-6"><h2 class="text-xl font-extrabold app-heading">Thông tin lỗi an toàn</h2><p class="mt-4 font-bold {{ $delivery->last_error_code ? 'text-error' : 'text-success' }}">{{ \App\Support\TicketDeliveryPresentation::error($delivery->last_error_code) }}</p><p class="mt-2 text-sm app-muted">Không hiển thị stack trace, phản hồi SMTP thô hoặc thông tin xác thực máy chủ thư.</p></section></div>

    <section class="cinema-card p-6"><h2 class="text-xl font-extrabold app-heading">Vòng đời</h2><ol class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4"><li><strong>Đã tạo</strong><span class="block app-muted">{{ $delivery->created_at?->format('d/m/Y H:i:s') }}</span></li>@if($delivery->processing_started_at)<li><strong>Đã nhận xử lý</strong><span class="block app-muted">{{ $delivery->processing_started_at->format('d/m/Y H:i:s') }}</span></li>@endif @if($delivery->sent_at)<li><strong>Đã gửi</strong><span class="block app-muted">{{ $delivery->sent_at->format('d/m/Y H:i:s') }}</span></li>@elseif($delivery->last_error_code)<li><strong>Gửi thất bại</strong><span class="block app-muted">{{ $delivery->updated_at?->format('d/m/Y H:i:s') }}</span></li>@endif</ol></section>

    @can('activity_logs.view')
        <section class="cinema-card overflow-hidden"><div class="border-b app-border p-6"><h2 class="text-xl font-extrabold app-heading">Nhật ký vận hành</h2></div><div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Thời gian</th><th>Người thực hiện</th><th>Hành động</th><th>Request ID</th></tr></thead><tbody>@forelse($activities as $activity)<tr><td>{{ $activity->created_at?->format('d/m/Y H:i:s') }}</td><td>{{ $activity->actor?->name ?? 'Hệ thống' }}</td><td>{{ $activity->action }}</td><td class="font-mono text-xs">{{ $activity->request_id }}</td></tr>@empty<tr><td colspan="4" class="py-8 text-center app-muted">Chưa có nhật ký vận hành.</td></tr>@endforelse</tbody></table></div></section>
    @endcan
</div>
@endsection
