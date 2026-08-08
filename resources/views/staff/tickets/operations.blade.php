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
            @if($printEvents->isNotEmpty())
                <div class="mt-5 overflow-x-auto">
                    <table class="admin-table">
                        <thead><tr><th>Thời gian</th><th>Nhân viên</th><th>Hành động</th><th>Lần in</th><th>Lý do</th><th>Kết quả</th></tr></thead>
                        <tbody>
                            @foreach($printEvents as $event)
                                <tr>
                                    <td>{{ $event->created_at?->format('d/m/Y H:i:s') }}</td>
                                    <td>{{ $event->actor?->name ?? 'Hệ thống' }}</td>
                                    <td>{{ $event->attempt_number === 1 ? 'In lần đầu' : 'In lại' }}</td>
                                    <td>#{{ $event->attempt_number }}</td>
                                    <td>{{ $event->event_type === 'reprint_requested' || ($event->event_type === 'print_started' && $event->attempt_number > 1) ? (\App\Services\Tickets\TicketPrintService::REPRINT_REASONS[$event->failure_code] ?? '—') : ($event->event_type === 'print_failed' ? (\App\Services\Tickets\TicketPrintService::FAILURE_REASONS[$event->failure_code] ?? '—') : '—') }}@if($event->safe_note)<span class="mt-1 block max-w-md break-words text-xs app-muted">{{ $event->safe_note }}</span>@endif</td>
                                    <td>{{ match($event->event_type) { 'print_succeeded' => 'Thành công', 'print_failed' => 'Lỗi', 'reprint_requested' => 'Đã ghi nhận lý do', 'print_started' => 'Đang xử lý', 'stale_print_released' => 'Hết hạn', 'retry_authorized' => 'Dữ liệu duyệt cũ', default => 'Đã ghi nhận' } }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    <section class="cinema-card p-6">
        <h2 class="text-xl font-extrabold app-heading">Thao tác riêng biệt</h2>
        <p class="mt-2 app-muted">In vé cứng không đánh dấu đã soát. Soát vé vẫn là một thao tác xác nhận độc lập.</p>
        <div class="mt-5 flex flex-wrap gap-3">
            @can('tickets.print')
                @if($booking->booking_status === 'paid' && $booking->payment_status === 'paid' && !$printState)
                    <form method="POST" action="{{ route('staff.tickets.print.start', $booking) }}" data-submit-once>@csrf
                        <button type="submit" class="btn-primary"><i class="ph ph-printer"></i>In vé cứng</button>
                    </form>
                @elseif($booking->booking_status === 'paid' && $booking->payment_status === 'paid' && in_array($printState?->status, ['printed', 'retry_allowed', 'retry_requires_authorization', 'retry_authorized'], true))
                    @if($printState->status === 'printed')
                        <p class="w-full rounded-xl bg-success/10 px-4 py-3 font-bold text-success">Đã in {{ $printState->printed_at?->format('d/m/Y H:i') }} · Người in: {{ $printState->printedBy?->name ?? '—' }} · {{ $printState->attempts_count }} lần in @if($printState->attempts_count > 1)· In lại {{ $printState->attempts_count - 1 }} lần @endif.</p>
                    @else
                        <p class="w-full rounded-xl bg-warning/10 px-4 py-3 font-bold text-warning">Lần in trước gặp lỗi: {{ \App\Services\Tickets\TicketPrintService::FAILURE_REASONS[$printState->last_failure_code] ?? 'Lỗi đã được ghi nhận' }}. Vui lòng ghi lý do trước khi in lại.</p>
                    @endif
                    <button type="button" class="btn-primary" data-modal-open="ticket-reprint" aria-haspopup="dialog" aria-controls="ticket-reprint"><i class="ph ph-printer"></i>In lại vé</button>
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

    @can('tickets.print')
        @if($booking->booking_status === 'paid' && $booking->payment_status === 'paid' && in_array($printState?->status, ['printed', 'retry_allowed', 'retry_requires_authorization', 'retry_authorized'], true))
            <x-ui.modal id="ticket-reprint" title="In lại vé" description-id="ticket-reprint-description">
                <p id="ticket-reprint-description" class="app-muted">Vui lòng chọn lý do cần in thêm một bản vé. Thông tin này sẽ được lưu vào lịch sử vận hành.</p>
                <form method="POST" action="{{ route('staff.tickets.print.reprint', $booking) }}" class="mt-5 space-y-4" data-submit-once>
                    @csrf
                    <label class="cinema-label" for="reprint-reason">Lý do in lại
                        <select id="reprint-reason" name="reason_code" class="cinema-input mt-1" required data-modal-initial-focus>
                            <option value="">Chọn lý do</option>
                            @foreach(\App\Services\Tickets\TicketPrintService::REPRINT_REASONS as $code => $label)
                                <option value="{{ $code }}" @selected(old('reason_code') === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="cinema-label" for="reprint-note">Ghi chú ngắn
                        <textarea id="reprint-note" name="safe_note" maxlength="300" rows="3" class="cinema-input mt-1" placeholder="Bắt buộc khi chọn Lý do khác">{{ old('safe_note') }}</textarea>
                    </label>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="btn-secondary" data-modal-close="ticket-reprint">Hủy</button>
                        <button type="submit" class="btn-primary">Xác nhận in lại</button>
                    </div>
                </form>
            </x-ui.modal>
        @endif
    @endcan
</div>
@endsection
