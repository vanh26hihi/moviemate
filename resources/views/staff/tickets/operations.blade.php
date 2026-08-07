@extends('layouts.staff')

@section('title', 'Vận hành vé '.$booking->booking_code.' - MovieMate')
@section('page-title', 'Xem trước vé')

@section('content')
<div class="space-y-6">
    @if(session('checkin_result'))
        @php($result = session('checkin_result'))
        <section class="rounded-2xl border p-5 {{ $result['result'] === 'accepted' ? 'border-success/40 bg-success/10 text-success' : 'border-warning/40 bg-warning/10 text-warning' }}" role="alert">
            <h2 class="text-xl font-extrabold">{{ $result['result'] === 'accepted' ? 'Đã soát vé thành công' : 'Vé này đã được soát' }}</h2>
            <p class="mt-1">{{ $result['message'] }} @if($result['used_at']) Thời gian: {{ $result['used_at'] }}.@endif</p>
        </section>
    @endif
    <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <a href="{{ route('staff.tickets.index') }}" class="mb-3 inline-flex items-center gap-2 text-sm font-bold text-brand-start"><i class="ph ph-arrow-left"></i>Quét vé khác</a>
            <p class="text-sm font-bold uppercase tracking-widest app-muted">Mã vé</p>
            <h1 class="mt-1 text-3xl font-extrabold app-heading" data-resolved-booking-code>{{ $booking->booking_code }}</h1>
            <p class="mt-2 app-muted">{{ $eligibilityMessage }}</p>
        </div>
        <span class="status-badge {{ $booking->booking_status === 'paid' && $booking->payment_status === 'paid' ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning' }}">
            {{ $booking->booking_status === 'paid' && $booking->payment_status === 'paid' ? 'Vé hợp lệ' : $eligibilityMessage }}
        </span>
    </header>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="cinema-card p-5"><p class="text-sm app-muted">Chi nhánh</p><p class="mt-2 font-extrabold app-text">{{ $booking->showtime?->cinema?->name ?? '—' }}</p></div>
        <div class="cinema-card p-5"><p class="text-sm app-muted">Thanh toán</p><p class="mt-2 font-extrabold app-text">{{ $booking->payment_status_label }}</p></div>
        <div class="cinema-card p-5"><p class="text-sm app-muted">Trạng thái in vé</p><p class="mt-2 font-extrabold app-text">{{ $printState?->status_label ?? 'Chưa có dữ liệu in' }}</p></div>
        <div class="cinema-card p-5"><p class="text-sm app-muted">Trạng thái soát vé</p><p class="mt-2 font-extrabold app-text">{{ $booking->booking_status === 'used' ? 'Đã soát vé' : 'Chưa soát vé' }}</p></div>
    </section>

    <section class="cinema-card p-6">
        <h2 class="text-xl font-extrabold app-heading">Thông tin đã xác thực</h2>
        <dl class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div><dt class="text-sm app-muted">Phim</dt><dd class="font-bold app-text">{{ $booking->showtime?->movie?->title ?? '—' }}</dd></div>
            <div><dt class="text-sm app-muted">Suất chiếu</dt><dd class="font-bold app-text">{{ $booking->showtime_label }}</dd></div>
            <div><dt class="text-sm app-muted">Phòng</dt><dd class="font-bold app-text">{{ $booking->showtime?->room?->name ?? '—' }}</dd></div>
            <div><dt class="text-sm app-muted">Ghế</dt><dd class="font-bold app-text">{{ $booking->seat_codes ?: '—' }}</dd></div>
            <div><dt class="text-sm app-muted">Khách hàng</dt><dd class="font-bold app-text">{{ $customerName }} · {{ $customerEmail }}</dd></div>
            <div><dt class="text-sm app-muted">Gửi vé điện tử</dt><dd class="font-bold app-text">{{ $booking->ticketDelivery?->status_label ?? 'Chưa có dữ liệu' }}</dd></div>
        </dl>
    </section>

    @if($printState || $checkinEvent)
        <section class="cinema-card p-6">
            <h2 class="text-xl font-extrabold app-heading">Lịch sử vận hành</h2>
            <dl class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div><dt class="text-sm app-muted">Số lần thử in</dt><dd class="font-bold app-text">{{ $printState?->attempts_count ?? 0 }}</dd></div>
                <div><dt class="text-sm app-muted">Lần in gần nhất</dt><dd class="font-bold app-text">{{ $printState?->printed_at?->format('d/m/Y H:i') ?? $printState?->last_failed_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                <div><dt class="text-sm app-muted">Người in</dt><dd class="font-bold app-text">{{ $printState?->printedBy?->name ?? '—' }}</dd></div>
                <div><dt class="text-sm app-muted">Soát vé</dt><dd class="font-bold app-text">{{ $checkinEvent ? $checkinEvent->scanned_at?->format('d/m/Y H:i').' · '.($checkinEvent->actor?->name ?? '—') : 'Chưa soát' }}</dd></div>
            </dl>
        </section>
    @endif

    <section class="cinema-card p-6">
        <h2 class="text-xl font-extrabold app-heading">Thao tác riêng biệt</h2>
        <p class="mt-2 app-muted">In vé cứng không đánh dấu đã soát. Soát vé vẫn là một thao tác xác nhận độc lập.</p>
        <div class="mt-5 flex flex-wrap gap-3">
            @can('tickets.print')
                @if($booking->booking_status === 'paid' && $booking->payment_status === 'paid' && (!$printState || in_array($printState->status, ['retry_allowed', 'retry_authorized'], true)))
                    @if($printState?->status === 'retry_allowed')
                        <p class="w-full rounded-xl bg-warning/10 px-4 py-3 font-bold text-warning">Được phép in lại do lần in trước gặp lỗi: {{ \App\Services\Tickets\TicketPrintService::FAILURE_REASONS[$printState->last_failure_code] ?? 'Lỗi đã được ghi nhận' }}.</p>
                    @elseif($printState?->status === 'retry_authorized')
                        <p class="w-full rounded-xl bg-success/10 px-4 py-3 font-bold text-success">Quản lý đã phê duyệt thêm một lần in.</p>
                    @endif
                    <form method="POST" action="{{ route('staff.tickets.print.start', $booking) }}" data-submit-once>@csrf
                        <button type="submit" class="btn-primary"><i class="ph ph-printer"></i>In vé cứng</button>
                    </form>
                @elseif($printState?->status === 'printed')
                    <span class="rounded-xl bg-success/10 px-4 py-3 font-bold text-success">Đã in thành công {{ $printState->printed_at?->format('d/m/Y H:i') }}</span>
                    <span class="rounded-xl bg-success/10 px-4 py-3 font-bold text-success">Người in: {{ $printState->printedBy?->name ?? '—' }}</span>
                @elseif($printState?->status === 'printing' && $printState->active_operation_expires_at?->isPast())
                    @if((int) $printState->active_operator_user_id === (int) auth()->id())
                        <div class="w-full rounded-xl bg-warning/10 p-4 text-warning">
                            <p class="font-bold">Phiên in trước đã hết hiệu lực. Vui lòng xác nhận kết quả lần in trước trước khi tiếp tục.</p>
                            <form method="POST" action="{{ route('staff.tickets.print.recover-expired', $booking) }}" class="mt-3" data-submit-once>@csrf
                                <button type="submit" class="btn-secondary text-error">Báo lỗi in: Trình duyệt/phiên in bị gián đoạn</button>
                            </form>
                        </div>
                    @else
                        <span class="rounded-xl bg-warning/10 px-4 py-3 font-bold text-warning">Phiên in hết hiệu lực phải do nhân viên đã bắt đầu phiên xử lý.</span>
                    @endif
                @elseif($printState?->status === 'printing')
                    <span class="rounded-xl bg-warning/10 px-4 py-3 font-bold text-warning">Một phiên in đang chờ nhân viên xác nhận kết quả.</span>
                @elseif($printState?->status === 'retry_requires_authorization')
                    <span class="rounded-xl bg-warning/10 px-4 py-3 font-bold text-warning">Đã hết lượt in lại. Vui lòng yêu cầu Quản lý phê duyệt.</span>
                @endif
            @endcan
            @can('tickets.checkin')
                @if($booking->booking_status === 'paid' && $booking->payment_status === 'paid')
                    <form method="POST" action="{{ route('staff.tickets.consume-booking', $booking) }}" data-submit-once>@csrf
                        <button type="submit" class="btn-secondary"><i class="ph ph-check-circle"></i>Soát vé</button>
                    </form>
                @elseif($booking->booking_status === 'used')
                    <span class="rounded-xl bg-success/10 px-4 py-3 font-bold text-success">Đã soát vé {{ $checkinEvent?->scanned_at?->format('d/m/Y H:i') }}</span>
                @endif
            @endcan
        </div>
    </section>
</div>
@endsection
