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
        <div class="border-b app-border p-5"><h2 class="text-lg font-extrabold app-text">Booking bị ảnh hưởng</h2><p class="mt-1 text-sm app-muted">Xử lý vận hành không làm thay đổi tổng tiền, thanh toán hoặc khuyến mãi của đơn.</p></div>
        <div class="overflow-x-auto"><table class="admin-table min-w-[72rem]"><thead><tr><th>Booking</th><th>Khách hàng</th><th>Suất chiếu</th><th>Ghế ảnh hưởng</th><th>Phân loại hiện tại</th><th>Trạng thái xử lý</th><th>Trạng thái in</th></tr></thead><tbody>
            @forelse($groups as $group)
                @php($booking = $group['booking'])
                @php($tickets = $group['impacts']->pluck('bookingSeat.admissionTicket')->filter())
                @php($maxPrint = (int) $tickets->max('print_count'))
                <tr>
                    <td class="font-bold">{{ $booking->booking_code }}</td>
                    <td>{{ $booking->customer_name ?: $booking->user?->name ?: 'Khách' }}<br><span class="text-xs app-muted">{{ $booking->customer_email }}</span></td>
                    <td>{{ $group['impacts']->first()->bookingSeat->showtime->movie->title }}<br>{{ $booking->showtime_label }}</td>
                    <td>{{ $group['impacts']->pluck('resolution.originalSeat.seat_code')->filter()->merge($group['impacts']->whereNull('resolution')->pluck('bookingSeat.seat.seat_code'))->join(', ') }}</td>
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
                    <td>
                        @if($group['impacts']->pluck('resolution')->filter()->contains('resolution_type', 'requires_refund'))
                            Cần xử lý hoàn tiền
                        @elseif($group['impacts']->pluck('resolution')->filter()->contains(fn($resolution) => $resolution->reprint_required && !$resolution->reprint_satisfied_at))
                            Cần in lại vé
                        @elseif($group['impacts']->contains('resolution_status', 'unresolved'))
                            Chưa xử lý
                        @elseif($group['impacts']->pluck('resolution')->filter()->isNotEmpty())
                            Đã xử lý đổi ghế
                        @else
                            Đã xử lý
                        @endif
                    </td>
                    <td>{{ $maxPrint === 0 ? 'Chưa in' : ($maxPrint === 1 ? 'Đã in' : 'Đã in lại ('.$maxPrint.')') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-10 text-center app-muted">Không có ảnh hưởng booking.</td></tr>
            @endforelse
        </tbody></table></div>
    </section>

    @if($incident->status === 'open')
        <section class="space-y-4" aria-labelledby="relocation-title">
            <div><h2 id="relocation-title" class="text-2xl font-extrabold app-heading">Xử lý đổi ghế</h2><p class="mt-1 text-sm app-muted">Quản lý chọn ghế đã được máy chủ lọc; ghế tương đương được ưu tiên trước.</p></div>
            @foreach($relocationOptions as $primaryImpactId => $option)
                <article class="cinema-card p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div><p class="text-sm app-muted">Ghế ban đầu</p><h3 class="text-2xl font-extrabold app-heading">{{ $option['original_label'] }}</h3><p class="text-sm app-muted">Giá trị trước khuyến mãi đã chốt: {{ number_format($option['original_amount'], 0, ',', '.') }} VNĐ</p></div>
                        <span class="status-badge bg-warning/10 text-warning">Khách không trả thêm</span>
                    </div>
                    @if($option['equivalent']->isNotEmpty())
                        <h4 class="mt-5 font-extrabold app-text">TƯƠNG ĐƯƠNG</h4>
                        <div class="mt-3 flex flex-wrap gap-3">
                            @foreach($option['equivalent'] as $candidate)
                                <form method="POST" action="{{ route('admin.rooms.seat-incidents.relocate', [$room, $incident, $primaryImpactId]) }}" data-submit-once>
                                    @csrf<input type="hidden" name="replacement_seat_id" value="{{ $candidate['seat_id'] }}">
                                    <button class="btn-secondary" type="submit">{{ $candidate['label'] }}</button>
                                </form>
                            @endforeach
                        </div>
                    @endif
                    @if($option['upgrade']->isNotEmpty())
                        <h4 class="mt-5 font-extrabold app-text">NÂNG HẠNG</h4>
                        @if($option['equivalent']->isNotEmpty())<p class="mt-1 text-sm app-muted">Chỉ khả dụng khi không còn ghế tương đương phù hợp.</p>@endif
                        <div class="mt-3 flex flex-wrap gap-3">
                            @foreach($option['upgrade'] as $candidate)
                                <form method="POST" action="{{ route('admin.rooms.seat-incidents.relocate', [$room, $incident, $primaryImpactId]) }}" data-submit-once>
                                    @csrf<input type="hidden" name="replacement_seat_id" value="{{ $candidate['seat_id'] }}">
                                    <button class="btn-secondary" type="submit" @disabled($option['equivalent']->isNotEmpty())>{{ $candidate['label'] }} · {{ number_format($candidate['hypothetical_amount'], 0, ',', '.') }} VNĐ</button>
                                </form>
                            @endforeach
                        </div>
                    @endif
                    @if($option['equivalent']->isEmpty() && $option['upgrade']->isEmpty())
                        <p class="mt-5 rounded-xl bg-warning/10 p-4 font-bold text-warning">Không còn ghế phù hợp.</p>
                        <form method="POST" action="{{ route('admin.rooms.seat-incidents.requires-refund', [$room, $incident, $primaryImpactId]) }}" class="mt-3" data-submit-once>
                            @csrf<button class="btn-secondary" type="submit">CHUYỂN SANG CẦN HOÀN TIỀN</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </section>
    @endif
</div>
@endsection
