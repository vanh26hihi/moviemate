@extends('layouts.admin')

@section('title', 'Sự cố ghế #'.$incident->id.' - MovieMate')
@section('page-title', 'Chi tiết sự cố ghế')

@section('content')
<div class="space-y-6">
    <a class="inline-flex items-center gap-2 text-sm font-bold text-brand-start" href="{{ route('admin.rooms.seat-maintenance.index', $room) }}"><i class="ph ph-arrow-left"></i>Quay lại bảo trì ghế</a>
    <section class="cinema-card p-6">
        <div class="flex flex-wrap items-start justify-between gap-4"><div><h1 class="admin-page-title">Sự cố #{{ $incident->id }}</h1><p class="mt-2 app-muted">{{ $incident->cinema->name }} · {{ $incident->room->code }} · Ghế {{ $incident->incidentSeats->pluck('seat.seat_code')->filter()->join('–') }}</p></div><span class="status-badge {{ $incident->status === 'open' ? 'bg-error/10 text-error' : 'bg-success/10 text-success' }}">{{ $incident->status }}</span></div>
        <dl class="mt-5 grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-4"><div><dt class="app-muted">Lý do</dt><dd class="font-bold app-text">{{ $incident->reason }}</dd></div><div><dt class="app-muted">Người báo</dt><dd class="font-bold app-text">{{ $incident->reportedBy?->name ?? 'Tài khoản đã xóa' }}</dd></div><div><dt class="app-muted">Thời điểm</dt><dd class="font-bold app-text">{{ $incident->created_at->format('d/m/Y H:i:s') }}</dd></div><div><dt class="app-muted">Ghi chú</dt><dd class="font-bold app-text">{{ $incident->note ?: '—' }}</dd></div></dl>
    </section>
    <section class="cinema-card overflow-hidden">
        <div class="border-b app-border p-5"><h2 class="text-lg font-extrabold app-text">Booking bị ảnh hưởng</h2><p class="mt-1 text-sm app-muted">Phase 2B chỉ hiển thị và bảo toàn ảnh hưởng tài chính; chưa có đổi ghế hoặc hoàn tiền.</p></div>
        <div class="overflow-x-auto"><table class="admin-table min-w-[72rem]"><thead><tr><th>Booking</th><th>Khách hàng</th><th>Suất chiếu</th><th>Ghế ảnh hưởng</th><th>Phân loại hiện tại</th><th>Trạng thái xử lý</th><th>Trạng thái in</th></tr></thead><tbody>
            @forelse($groups as $group)
                @php($booking = $group['booking'])
                @php($tickets = $group['impacts']->pluck('bookingSeat.admissionTicket')->filter())
                @php($maxPrint = (int) $tickets->max('print_count'))
                <tr>
                    <td class="font-bold">{{ $booking->booking_code }}</td>
                    <td>{{ $booking->customer_name ?: $booking->user?->name ?: 'Khách' }}<br><span class="text-xs app-muted">{{ $booking->customer_email }}</span></td>
                    <td>{{ $group['impacts']->first()->bookingSeat->showtime->movie->title }}<br>{{ $booking->showtime_label }}</td>
                    <td>{{ $group['impacts']->pluck('bookingSeat.seat.seat_code')->join(', ') }}</td>
                    <td>
                        @if($group['classification'] === 'ordinary_hold')
                            Đã hủy đơn tạm
                        @elseif($group['classification'] === 'retained_payment')
                            Đang chờ kết quả thanh toán
                        @elseif($group['classification'] === 'paid')
                            Đã thanh toán — chờ xử lý
                        @else
                            Đã giải phóng
                        @endif
                    </td>
                    <td>{{ $group['impacts']->contains('resolution_status', 'unresolved') ? 'unresolved' : 'resolved' }}</td>
                    <td>{{ $maxPrint === 0 ? 'Chưa in' : ($maxPrint === 1 ? 'Đã in' : 'Đã in lại ('.$maxPrint.')') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-10 text-center app-muted">Không có ảnh hưởng booking.</td></tr>
            @endforelse
        </tbody></table></div>
    </section>
</div>
@endsection
