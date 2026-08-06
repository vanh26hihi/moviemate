@extends('layouts.admin')

@section('title', 'Gửi vé điện tử - MovieMate')
@section('page-title', 'Gửi vé điện tử')

@section('content')
<div class="space-y-6">
    <header><h1 class="admin-page-title">Gửi vé điện tử</h1><p class="admin-page-subtitle">Theo dõi việc gửi vé qua email và xử lý các lần gửi thất bại.</p></header>

    <form method="GET" class="cinema-card grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-4">
        <label class="text-sm font-bold">Mã đặt vé<input class="cinema-input mt-1" name="booking_code" value="{{ $filters['booking_code'] ?? '' }}"></label>
        <label class="text-sm font-bold">Người nhận<input class="cinema-input mt-1" name="recipient" value="{{ $filters['recipient'] ?? '' }}" placeholder="Email cần tìm"></label>
        <label class="text-sm font-bold">Trạng thái<select class="cinema-input mt-1" name="status"><option value="">Tất cả</option>@foreach(\App\Models\BookingTicketDelivery::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ \App\Support\StatusLabel::for('ticket_delivery', $status) }}</option>@endforeach</select></label>
        <label class="text-sm font-bold">Số lần thử tối thiểu<input class="cinema-input mt-1" type="number" min="0" name="attempts_min" value="{{ $filters['attempts_min'] ?? '' }}"></label>
        <label class="text-sm font-bold">Tạo từ<input class="cinema-input mt-1" type="date" name="created_from" value="{{ $filters['created_from'] ?? '' }}"></label>
        <label class="text-sm font-bold">Tạo đến<input class="cinema-input mt-1" type="date" name="created_to" value="{{ $filters['created_to'] ?? '' }}"></label>
        <label class="text-sm font-bold">Gửi từ<input class="cinema-input mt-1" type="date" name="sent_from" value="{{ $filters['sent_from'] ?? '' }}"></label>
        <label class="text-sm font-bold">Gửi đến<input class="cinema-input mt-1" type="date" name="sent_to" value="{{ $filters['sent_to'] ?? '' }}"></label>
        <label class="text-sm font-bold">Có lỗi<select class="cinema-input mt-1" name="has_error"><option value="">Tất cả</option><option value="yes" @selected(($filters['has_error'] ?? '') === 'yes')>Có</option><option value="no" @selected(($filters['has_error'] ?? '') === 'no')>Không</option></select></label>
        <label class="text-sm font-bold">Đến hạn thử lại<select class="cinema-input mt-1" name="retry_due"><option value="">Tất cả</option><option value="yes" @selected(($filters['retry_due'] ?? '') === 'yes')>Đã đến hạn</option><option value="no" @selected(($filters['retry_due'] ?? '') === 'no')>Chưa đến hạn</option></select></label>
        <label class="text-sm font-bold">Claim quá hạn<select class="cinema-input mt-1" name="stale_claim"><option value="">Tất cả</option><option value="yes" @selected(($filters['stale_claim'] ?? '') === 'yes')>Có</option><option value="no" @selected(($filters['stale_claim'] ?? '') === 'no')>Không</option></select></label>
        <label class="text-sm font-bold">Sắp xếp<select class="cinema-input mt-1" name="sort"><option value="updated_at">Cập nhật gần nhất</option><option value="attempts" @selected(($filters['sort'] ?? '') === 'attempts')>Số lần thử</option><option value="available_at" @selected(($filters['sort'] ?? '') === 'available_at')>Lần thử tiếp theo</option><option value="sent_at" @selected(($filters['sort'] ?? '') === 'sent_at')>Thời gian gửi</option><option value="created_at" @selected(($filters['sort'] ?? '') === 'created_at')>Thời gian tạo</option></select></label>
        <div class="flex items-end gap-2 xl:col-span-4"><button class="btn-primary" type="submit"><i class="ph ph-funnel"></i>Lọc</button><a class="btn-secondary" href="{{ route('admin.ticket-deliveries.index') }}">Xóa lọc</a></div>
    </form>

    <section class="cinema-card overflow-hidden"><div class="overflow-x-auto"><table class="admin-table min-w-[80rem]"><thead><tr><th>Mã đặt vé</th><th>Người nhận</th><th>Trạng thái</th><th>Số lần thử</th><th>Được nhận xử lý lúc</th><th>Thử lại lúc</th><th>Gửi thành công lúc</th><th>Nhóm lỗi</th><th>Cập nhật gần nhất</th><th>Thao tác</th></tr></thead><tbody>
        @forelse($deliveries as $delivery)<tr>
            <td class="font-bold text-brand-start">{{ $delivery->booking?->booking_code ?? 'Không còn dữ liệu booking' }}</td>
            <td>{{ \App\Support\PrivacyMask::email($delivery->booking?->recipient_email) }}</td>
            <td><span class="status-badge {{ $delivery->status === 'sent' ? 'bg-success/10 text-success' : ($delivery->status === 'failed' ? 'bg-error/10 text-error' : 'bg-warning/10 text-warning') }}">{{ $delivery->status_label }}</span></td>
            <td>{{ $delivery->attempts }}</td><td>{{ $delivery->processing_started_at?->format('d/m/Y H:i:s') ?? '—' }}</td><td>{{ $delivery->available_at?->format('d/m/Y H:i:s') ?? '—' }}</td><td>{{ $delivery->sent_at?->format('d/m/Y H:i:s') ?? '—' }}</td>
            <td>{{ \App\Support\TicketDeliveryPresentation::error($delivery->last_error_code) }}</td><td>{{ $delivery->updated_at?->format('d/m/Y H:i:s') ?? '—' }}</td>
            <td><a class="font-bold text-brand-start" href="{{ route('admin.ticket-deliveries.show', $delivery) }}">Chi tiết</a></td>
        </tr>@empty<tr><td colspan="10" class="py-12 text-center app-muted">Không có yêu cầu gửi vé phù hợp.</td></tr>@endforelse
    </tbody></table></div>@if($deliveries->hasPages())<div class="border-t app-border p-4">{{ $deliveries->links() }}</div>@endif</section>
</div>
@endsection
