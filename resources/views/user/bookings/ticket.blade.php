@extends('layouts.user')

@section('title', 'Vé xem phim - MovieMate')
@section('body_class', 'ticket-document-page')

@php
    $tickets = isset($singleAdmissionTicket)
        ? collect([$singleAdmissionTicket])
        : ($isDeliverable ? $booking->admissionTickets : collect());
    $backUrl = $backUrl ?? (auth()->check() ? route('user.bookings.history') : route('user.bookings.success', $booking));
    $backLabel = $backLabel ?? 'Về đơn đặt vé';
    $ticketRecipient = $ticketRecipient ?? $booking->recipient_email;
    $ticketCustomer = $ticketCustomer ?? ($booking->user?->name ?? $booking->customer_name ?? 'Khách MovieMate');
    $currency = ($booking->currency ?: 'VND') === 'VND' ? 'VNĐ' : $booking->currency;
@endphp

@section('content')
<div class="ticket-preview-shell space-y-6">
    <div class="ticket-toolbar print-hidden"><a href="{{ $backUrl }}" class="ticket-toolbar-link"><i class="ph-bold ph-arrow-left"></i>{{ $backLabel }}</a></div>

    <header class="cinema-card p-6">
        <p class="text-sm font-bold uppercase tracking-widest app-muted">Đơn đặt vé</p>
        <h1 class="mt-1 text-2xl font-extrabold app-heading">{{ $booking->booking_code }}</h1>
        <p class="mt-2 app-muted">Mỗi ghế hành khách có một vé xem phim và trạng thái sử dụng độc lập.</p>
    </header>

    <section aria-labelledby="admission-ticket-title">
        <h2 id="admission-ticket-title" class="text-2xl font-extrabold app-heading">Vé xem phim</h2>
        <div class="mt-4 grid gap-5 lg:grid-cols-2">
            @forelse($tickets as $admissionTicket)
                @php
                    $state = match (true) {
                        $booking->payment_status === 'refunded' => 'refunded',
                        $booking->booking_status === 'cancelled' => 'cancelled',
                        $booking->booking_status === 'expired' => 'expired',
                        $admissionTicket->used_at !== null => 'used',
                        $isDeliverable => 'valid',
                        default => 'invalid',
                    };
                    $qrPayload = $ticketQrPayloads->get($admissionTicket->id);
                @endphp
                <article class="cinema-card overflow-hidden" data-admission-ticket="{{ $admissionTicket->id }}" data-ticket-state="{{ $state }}">
                    <header class="flex items-center justify-between gap-4 bg-slate-950 p-5 text-white">
                        <div><p class="text-xs font-bold tracking-widest">VÉ XEM PHIM</p><h3 class="mt-1 text-xl font-extrabold">Ghế {{ $admissionTicket->seat_code }}</h3></div>
                        <span class="rounded-full px-3 py-1 text-xs font-bold {{ $state === 'valid' ? 'bg-success/20 text-success' : ($state === 'used' ? 'bg-warning/20 text-warning' : 'bg-error/20 text-error') }}">
                            {{ match($state) { 'valid' => 'Chưa sử dụng', 'used' => 'Đã sử dụng', 'cancelled' => 'Đã hủy', 'expired' => 'Hết hạn', 'refunded' => 'Đã hoàn tiền', default => 'Không hợp lệ' } }}
                        </span>
                    </header>
                    <div class="grid gap-5 p-5 sm:grid-cols-[1fr_auto]">
                        <dl class="space-y-2 text-sm">
                            <div><dt class="app-muted">Phim</dt><dd class="font-bold app-text">{{ $booking->showtime?->movie?->title }}</dd></div>
                            <div><dt class="app-muted">Suất</dt><dd class="font-bold app-text">{{ $booking->showtime_label }}</dd></div>
                            <div><dt class="app-muted">Chi nhánh · Phòng</dt><dd class="font-bold app-text">{{ $booking->showtime?->cinema?->name }} · {{ $booking->showtime?->room?->name }}</dd></div>
                            <div><dt class="app-muted">Mã vé bảo mật</dt><dd class="break-all font-mono font-bold app-text">{{ $admissionTicket->ticket_code }}</dd></div>
                            @if($admissionTicket->used_at)<div><dt class="app-muted">Sử dụng lúc</dt><dd class="font-bold app-text">{{ $admissionTicket->used_at->format('d/m/Y H:i:s') }}</dd></div>@endif
                        </dl>
                        @if($qrPayload)
                            <div class="text-center"><canvas data-qr-value="{{ $qrPayload }}" data-qr-size="180" width="180" height="180" aria-label="QR vé ghế {{ $admissionTicket->seat_code }}"></canvas><p class="mt-2 text-xs app-muted">QR riêng cho ghế {{ $admissionTicket->seat_code }}</p></div>
                        @endif
                    </div>
                </article>
            @empty
                <p class="cinema-card p-6 app-muted">Đơn chưa có vé xem phim hợp lệ để sử dụng.</p>
            @endforelse
        </div>
    </section>

    @if($booking->foodPickupVoucher)
        <section class="cinema-card p-6" aria-labelledby="food-voucher-title">
            <h2 id="food-voucher-title" class="text-2xl font-extrabold app-heading">Phiếu nhận đồ ăn</h2>
            <p class="mt-1 app-muted">Một phiếu duy nhất cho toàn bộ đồ ăn trong đơn. Phiếu này không phải vé xem phim.</p>
            <p class="mt-4 font-mono font-bold app-text">{{ $booking->foodPickupVoucher->voucher_code }}</p>
            <div class="mt-4 space-y-2">
                @foreach($booking->foodOrder->items as $item)
                    <div class="flex justify-between gap-4"><span>{{ $item->snapshot_name }}</span><strong>× {{ $item->quantity }}</strong></div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="cinema-card p-6" aria-labelledby="booking-summary-title">
        <h2 id="booking-summary-title" class="text-xl font-extrabold app-heading">Chi tiết đơn đặt vé</h2>
        <dl class="mt-4 space-y-2">
            <div class="flex justify-between"><dt>Khách hàng</dt><dd class="font-bold">{{ $ticketCustomer }} · {{ $ticketRecipient }}</dd></div>
            <div class="flex justify-between"><dt>Tiền ghế</dt><dd class="font-bold">{{ number_format((int) $booking->seat_subtotal, 0, ',', '.') }} {{ $currency }}</dd></div>
            <div class="flex justify-between"><dt>Tiền đồ ăn</dt><dd class="font-bold">{{ number_format((int) $booking->food_subtotal, 0, ',', '.') }} {{ $currency }}</dd></div>
            <div class="flex justify-between border-t pt-2 text-lg"><dt class="font-extrabold">Tổng đơn</dt><dd class="font-extrabold">{{ number_format((int) $booking->total_amount, 0, ',', '.') }} {{ $currency }}</dd></div>
        </dl>
    </section>
</div>
@endsection
