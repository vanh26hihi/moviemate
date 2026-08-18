@extends('layouts.staff')

@section('title', 'Tra cứu & in đơn '.$booking->booking_code.' - MovieMate')
@section('page-title', 'Tra cứu & in đơn')

@section('content')
<div class="space-y-6">
    <header>
        <a href="{{ route('staff.tickets.index') }}" class="text-sm font-bold text-brand-start">← Tra cứu đơn khác</a>
        <p class="mt-4 text-sm font-bold uppercase tracking-widest app-muted">Đơn đặt vé</p>
        <h1 class="text-3xl font-extrabold app-heading">{{ $booking->booking_code }}</h1>
        <p class="mt-2 app-muted">{{ $booking->showtime?->movie?->title }} · {{ $booking->showtime_label }} · {{ $booking->showtime?->cinema?->name }} · {{ $booking->showtime?->room?->name }}</p>
    </header>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="cinema-card p-6" aria-labelledby="staff-showtime-context-title">
            <h2 id="staff-showtime-context-title" class="text-xl font-extrabold app-heading">Ngữ cảnh trình chiếu</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-sm app-muted">Loại phòng</dt><dd class="font-extrabold app-text">{{ $booking->showtime?->room?->room_type_label ?? '—' }}</dd></div>
                <div><dt class="text-sm app-muted">Định dạng trình chiếu</dt><dd class="font-extrabold app-text">{{ $booking->showtime?->presentationFormat?->name ?? '—' }}</dd></div>
            </dl>
        </section>

        <section class="cinema-card p-6" aria-labelledby="staff-payment-evidence-title">
            <h2 id="staff-payment-evidence-title" class="text-xl font-extrabold app-heading">Bằng chứng thanh toán</h2>
            @if($authoritativePayment)
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div><dt class="text-sm app-muted">Phương thức</dt><dd class="font-extrabold app-text">{{ \App\Support\PaymentPresentation::providerLabel($authoritativePayment->provider) }}</dd></div>
                    <div><dt class="text-sm app-muted">Trạng thái</dt><dd class="font-extrabold app-text">{{ $authoritativePayment->status_label }}</dd></div>
                    <div><dt class="text-sm app-muted">Đã xác minh lúc</dt><dd class="font-bold app-text">{{ $authoritativePayment->verified_at?->format('d/m/Y H:i:s') ?? 'Không áp dụng' }}</dd></div>
                    <div><dt class="text-sm app-muted">Đã thu tiền lúc</dt><dd class="font-bold app-text">{{ $authoritativePayment->settled_at?->format('d/m/Y H:i:s') ?? 'Không áp dụng' }}</dd></div>
                </dl>
                <p class="mt-4 rounded-xl bg-success/10 p-3 text-sm font-bold text-success">
                    {{ match($authoritativePayment->provider) {
                        \App\Models\Payment::PROVIDER_COUNTER_CASH => 'Đã thu tiền tại quầy với người thu và thời điểm quyết toán được lưu.',
                        \App\Models\Payment::PROVIDER_INTERNAL_ZERO => 'Đơn 0 VNĐ đã được xác nhận nội bộ; không yêu cầu giao dịch nhà cung cấp.',
                        default => 'Thanh toán online đã có bằng chứng xác minh từ nhà cung cấp.',
                    } }}
                </p>
            @else
                <p class="mt-4 rounded-xl bg-warning/10 p-3 text-sm font-bold text-warning">Chưa có thanh toán mang bằng chứng xác minh hoặc quyết toán có thẩm quyền.</p>
            @endif
        </section>
    </div>

    <section class="cinema-card border p-5 {{ $canPrint ? 'border-success/40 bg-success/5' : 'border-warning/40 bg-warning/5' }}" aria-live="polite">
        <div class="flex items-start gap-3">
            <i class="ph {{ $canPrint ? 'ph-check-circle text-success' : 'ph-lock-key text-warning' }} mt-0.5 text-2xl" aria-hidden="true"></i>
            <div>
                <h2 class="font-extrabold app-heading">{{ $canPrint ? 'Đơn đủ điều kiện phát hành tài liệu' : 'Đơn chỉ được xem, không được in' }}</h2>
                <p class="mt-1 text-sm {{ $canPrint ? 'text-success' : 'text-warning' }}">{{ $eligibilityMessage }}</p>
                @unless($canPrint)<p class="mt-2 text-sm app-muted">Nút in vé và phiếu nhận đồ ăn được khóa để không phát hành tài liệu cho đơn chưa quyết toán, đã hủy, hết hạn hoặc đã hoàn tiền.</p>@endunless
            </div>
        </div>
    </section>

    @can('tickets.print')
        @if($canPrint && $booking->admissionTickets->isNotEmpty() && $booking->admissionTickets->every(fn($ticket) => $ticket->printState === null) && (!$booking->foodPickupVoucher || $booking->foodPickupVoucher->print_count === 0))
            <form method="POST" action="{{ route('staff.tickets.print-all', $booking) }}" target="_blank" data-submit-once>
                @csrf
                <button class="btn-primary" type="submit"><i class="ph ph-printer"></i>In toàn bộ</button>
            </form>
        @endif
    @endcan

    <section aria-labelledby="physical-tickets-title">
        <h2 id="physical-tickets-title" class="text-2xl font-extrabold app-heading">{{ $canPrint ? 'Vé xem phim theo ghế' : 'Ghế trong đơn' }}</h2>
        <div class="mt-4 grid gap-5 xl:grid-cols-2">
            @foreach($booking->admissionTickets as $ticket)
                @php($state = $ticket->printState)
                @php($incidentResolution = $incidentReprints->get($ticket->booking_seat_id))
                <article class="cinema-card p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div><p class="text-sm app-muted">Ghế hành khách</p><h3 class="text-3xl font-extrabold text-brand-start">{{ $ticket->seat_code }}</h3>@if($canPrint)<p class="mt-1 break-all font-mono text-xs app-muted">{{ $ticket->ticket_code }}</p>@endif</div>
                        <span class="status-badge {{ $canPrint ? 'bg-brand-start/10 text-brand-start' : 'bg-warning/10 text-warning' }}">{{ $canPrint ? 'Vé vật lý' : 'Chưa phát hành' }}</span>
                    </div>
                    <dl class="mt-5 grid gap-3 text-sm">
                        <div><dt class="app-muted">Số bản đã in</dt><dd class="font-bold">{{ $ticket->print_count }}</dd></div>
                    </dl>

                    @can('tickets.print')
                        @if($canPrint)
                        <div class="mt-5">
                            @if($incidentResolution)
                                <div class="rounded-xl bg-warning/10 p-4">
                                    <p class="font-extrabold text-warning">Cần in lại do đổi ghế</p>
                                    <p class="mt-1 text-sm app-muted">Lý do được hệ thống xác thực từ sự cố ghế; không cần nhập thủ công.</p>
                                    <form method="POST" action="{{ route('staff.admission-tickets.print.incident-reprint', [$ticket, $incidentResolution]) }}" class="mt-3" data-submit-once>
                                        @csrf<button class="btn-primary" type="submit">In vé thay thế</button>
                                    </form>
                                </div>
                            @elseif(!$state)
                                <form method="POST" action="{{ route('staff.admission-tickets.print.start', $ticket) }}" data-submit-once>@csrf<button class="btn-primary" type="submit">In vé</button></form>
                            @elseif(in_array($state->status, ['printed', 'retry_allowed', 'retry_requires_authorization', 'retry_authorized'], true))
                                <form method="POST" action="{{ route('staff.admission-tickets.print.reprint', $ticket) }}" class="space-y-3" data-submit-once>
                                    @csrf
                                    <label class="cinema-label">Lý do in lại
                                        <select name="reason_code" class="cinema-input mt-1" required>
                                            <option value="">Chọn lý do</option>
                                            @foreach(\App\Services\Tickets\TicketPrintService::REPRINT_REASONS as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach
                                        </select>
                                    </label>
                                    <label class="cinema-label">Ghi chú nếu chọn lý do khác<textarea name="safe_note" class="cinema-input mt-1" maxlength="300" data-validation-required-if="reason_code:other"></textarea></label>
                                    <button class="btn-secondary" type="submit">In lại vé</button>
                                </form>
                            @else
                                <p class="rounded-xl bg-warning/10 p-3 font-bold text-warning">Phiên in đang chờ xác nhận kết quả.</p>
                            @endif
                        </div>
                        @endif
                    @endcan

                    @if($state?->events->isNotEmpty())
                        <details class="mt-5"><summary class="cursor-pointer font-bold">Lịch sử in</summary><ul class="mt-3 space-y-2 text-sm">
                            @foreach($state->events->sortByDesc('id') as $event)<li>#{{ $event->attempt_number }} · {{ $event->event_type }} · {{ $event->actor?->name ?? 'Hệ thống' }} @if($event->failure_code)· {{ $event->failure_code === \App\Services\Tickets\TicketPrintService::INCIDENT_REPRINT_REASON ? 'Đổi ghế do sự cố' : (\App\Services\Tickets\TicketPrintService::REPRINT_REASONS[$event->failure_code] ?? \App\Services\Tickets\TicketPrintService::FAILURE_REASONS[$event->failure_code] ?? $event->failure_code) }}@endif</li>@endforeach
                        </ul></details>
                    @endif
                </article>
            @endforeach
        </div>
    </section>

    @if($booking->foodPickupVoucher)
        @php($voucher = $booking->foodPickupVoucher)
        <section class="cinema-card p-6" aria-labelledby="food-voucher-title">
            <h2 id="food-voucher-title" class="text-2xl font-extrabold app-heading">Phiếu nhận đồ ăn</h2>
            <p class="mt-1 app-muted">Một phiếu cho toàn bộ đồ ăn của đơn; không phải vé xem phim.</p>
            @if($canPrint)<p class="mt-3 font-mono font-bold">{{ $voucher->voucher_code }}</p>@endif
            <ul class="mt-4 space-y-1">@foreach($booking->foodOrder->items as $item)<li>{{ $item->snapshot_name }} × {{ $item->quantity }}</li>@endforeach</ul>
            @can('tickets.print')
                @if($canPrint)
                <form method="POST" action="{{ route('staff.food-pickup-vouchers.print', $voucher) }}" class="mt-5 space-y-3" target="_blank" data-submit-once>
                    @csrf
                    @if($voucher->print_count > 0)<label class="cinema-label">Lý do in lại phiếu<textarea name="reason" class="cinema-input mt-1" maxlength="300" required></textarea></label>@endif
                    <button type="submit" class="btn-secondary">{{ $voucher->print_count > 0 ? 'In lại phiếu nhận đồ ăn' : 'In phiếu nhận đồ ăn' }}</button>
                </form>
                @endif
            @endcan
            @if($voucher->printEvents->isNotEmpty())<p class="mt-3 text-sm app-muted">Đã in {{ $voucher->print_count }} lần. Lần gần nhất: {{ $voucher->last_printed_at?->format('d/m/Y H:i:s') }}.</p>@endif
        </section>
    @endif

</div>
@endsection
