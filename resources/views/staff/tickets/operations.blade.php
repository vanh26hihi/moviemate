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

    @can('tickets.print')
        @if($booking->admissionTickets->isNotEmpty() && $booking->admissionTickets->every(fn($ticket) => $ticket->printState === null) && (!$booking->foodPickupVoucher || $booking->foodPickupVoucher->print_count === 0))
            <form method="POST" action="{{ route('staff.tickets.print-all', $booking) }}" target="_blank" data-submit-once>
                @csrf
                <button class="btn-primary" type="submit"><i class="ph ph-printer"></i>In toàn bộ</button>
            </form>
        @endif
    @endcan

    <section aria-labelledby="physical-tickets-title">
        <h2 id="physical-tickets-title" class="text-2xl font-extrabold app-heading">Vé xem phim theo ghế</h2>
        <div class="mt-4 grid gap-5 xl:grid-cols-2">
            @foreach($booking->admissionTickets as $ticket)
                @php($state = $ticket->printState)
                <article class="cinema-card p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div><p class="text-sm app-muted">Ghế hành khách</p><h3 class="text-3xl font-extrabold text-brand-start">{{ $ticket->seat_code }}</h3><p class="mt-1 break-all font-mono text-xs app-muted">{{ $ticket->ticket_code }}</p></div>
                        <span class="status-badge bg-brand-start/10 text-brand-start">Vé vật lý</span>
                    </div>
                    <dl class="mt-5 grid gap-3 text-sm">
                        <div><dt class="app-muted">Số bản đã in</dt><dd class="font-bold">{{ $ticket->print_count }}</dd></div>
                    </dl>

                    @can('tickets.print')
                        <div class="mt-5">
                            @if(!$state)
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
                                    <label class="cinema-label">Ghi chú nếu chọn lý do khác<textarea name="safe_note" class="cinema-input mt-1" maxlength="300"></textarea></label>
                                    <button class="btn-secondary" type="submit">In lại vé</button>
                                </form>
                            @else
                                <p class="rounded-xl bg-warning/10 p-3 font-bold text-warning">Phiên in đang chờ xác nhận kết quả.</p>
                            @endif
                        </div>
                    @endcan

                    @if($state?->events->isNotEmpty())
                        <details class="mt-5"><summary class="cursor-pointer font-bold">Lịch sử in</summary><ul class="mt-3 space-y-2 text-sm">
                            @foreach($state->events->sortByDesc('id') as $event)<li>#{{ $event->attempt_number }} · {{ $event->event_type }} · {{ $event->actor?->name ?? 'Hệ thống' }} @if($event->failure_code)· {{ \App\Services\Tickets\TicketPrintService::REPRINT_REASONS[$event->failure_code] ?? \App\Services\Tickets\TicketPrintService::FAILURE_REASONS[$event->failure_code] ?? $event->failure_code }}@endif</li>@endforeach
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
            <p class="mt-3 font-mono font-bold">{{ $voucher->voucher_code }}</p>
            <ul class="mt-4 space-y-1">@foreach($booking->foodOrder->items as $item)<li>{{ $item->snapshot_name }} × {{ $item->quantity }}</li>@endforeach</ul>
            @can('tickets.print')
                <form method="POST" action="{{ route('staff.food-pickup-vouchers.print', $voucher) }}" class="mt-5 space-y-3" target="_blank" data-submit-once>
                    @csrf
                    @if($voucher->print_count > 0)<label class="cinema-label">Lý do in lại phiếu<textarea name="reason" class="cinema-input mt-1" maxlength="300" required></textarea></label>@endif
                    <button type="submit" class="btn-secondary">{{ $voucher->print_count > 0 ? 'In lại phiếu nhận đồ ăn' : 'In phiếu nhận đồ ăn' }}</button>
                </form>
            @endcan
            @if($voucher->printEvents->isNotEmpty())<p class="mt-3 text-sm app-muted">Đã in {{ $voucher->print_count }} lần. Lần gần nhất: {{ $voucher->last_printed_at?->format('d/m/Y H:i:s') }}.</p>@endif
        </section>
    @endif

</div>
@endsection
