@extends('layouts.user')

@section('title', 'Đơn đặt vé - MovieMate')

@php
    $backUrl = $backUrl ?? (auth()->check() ? route('user.bookings.history') : route('user.bookings.success', $booking));
    $backLabel = $backLabel ?? 'Về đơn đặt vé';
    $ticketRecipient = $ticketRecipient ?? $booking->recipient_email;
    $ticketCustomer = $ticketCustomer ?? ($booking->user?->name ?? $booking->customer_name ?? 'Khách MovieMate');
    $currency = ($booking->currency ?: 'VND') === 'VND' ? 'VNĐ' : $booking->currency;
@endphp

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <div><a href="{{ $backUrl }}" class="font-bold text-brand-start"><i class="ph-bold ph-arrow-left"></i>{{ $backLabel }}</a></div>

    <article class="cinema-card overflow-hidden" data-booking-ticket data-ticket-state="{{ $ticketState }}">
        <header class="bg-slate-950 p-6 text-center text-white">
            <p class="text-xs font-bold tracking-[.25em]">ĐƠN ĐẶT VÉ</p>
            <h1 class="mt-2 font-mono text-2xl font-extrabold">{{ $booking->booking_code }}</h1>
        </header>
        <div class="grid gap-6 p-6 md:grid-cols-[1fr_auto]">
            <dl class="space-y-3 text-sm">
                <div><dt class="app-muted">Phim</dt><dd class="font-bold app-text">{{ $booking->showtime?->movie?->title }}</dd></div>
                <div><dt class="app-muted">Rạp</dt><dd class="font-bold app-text">{{ $booking->showtime?->cinema?->name }}</dd></div>
                <div><dt class="app-muted">Suất chiếu</dt><dd class="font-bold app-text">{{ $booking->showtime_label }}</dd></div>
                <div><dt class="app-muted">Định dạng</dt><dd class="font-bold app-text">{{ $booking->showtime?->presentationFormat?->name ?? 'Không xác định' }}</dd></div>
                <div><dt class="app-muted">Ghế</dt><dd class="font-bold app-text">{{ $booking->seat_codes }}</dd></div>
                <div><dt class="app-muted">Khách hàng</dt><dd class="font-bold app-text">{{ $ticketCustomer }} · {{ $ticketRecipient }}</dd></div>
            </dl>
            @if($bookingQrPayload)
                <div class="text-center">
                    <canvas data-qr-value="{{ $bookingQrPayload }}" data-qr-size="200" width="200" height="200" aria-label="QR đơn đặt vé"></canvas>
                    <p class="mt-2 text-xs font-bold app-muted">QR ĐƠN ĐẶT VÉ</p>
                </div>
            @endif
        </div>
    </article>

    @if(($relocations ?? collect())->isNotEmpty())
        <section class="cinema-card border-l-4 border-brand-start p-6" aria-labelledby="relocation-notice-title">
            <h2 id="relocation-notice-title" class="text-xl font-extrabold app-heading">Cập nhật ghế do sự cố</h2>
            @foreach($relocations as $relocation)
                <p class="mt-2 app-text">Ghế {{ $relocation->originalSeat->seat_code }} đã được đổi sang <strong>{{ $relocation->replacementSeat->seat_code }}</strong> do sự cố ghế.</p>
            @endforeach
            <p class="mt-3 font-bold text-success">Bạn không phải thanh toán thêm.</p>
            @if($relocations->contains(fn($relocation) => $relocation->reprint_required && !$relocation->reprint_satisfied_at))
                <p class="mt-2 text-sm app-muted">Vé giấy cũ cần được thay thế. Vui lòng đến quầy nhân viên.</p>
            @endif
        </section>
    @endif

    @if($booking->foodOrder?->items->isNotEmpty())
        <section class="cinema-card p-6" aria-labelledby="food-title">
            <h2 id="food-title" class="text-xl font-extrabold app-heading">Đồ ăn</h2>
            <div class="mt-4 space-y-2">
                @foreach($booking->foodOrder->items as $item)
                    <div class="flex justify-between gap-4"><span>{{ $item->snapshot_name }}</span><strong>× {{ $item->quantity }}</strong></div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="cinema-card p-6" aria-labelledby="booking-summary-title">
        <h2 id="booking-summary-title" class="text-xl font-extrabold app-heading">Thanh toán</h2>
        <dl class="mt-4 space-y-2">
            <div class="flex justify-between"><dt>Tiền ghế</dt><dd class="font-bold">{{ number_format((int) $booking->seat_subtotal, 0, ',', '.') }} {{ $currency }}</dd></div>
            <div class="flex justify-between"><dt>Tiền đồ ăn</dt><dd class="font-bold">{{ number_format((int) $booking->food_subtotal, 0, ',', '.') }} {{ $currency }}</dd></div>
            @if((int) $booking->promotion_discount_amount > 0)<div class="flex justify-between text-success"><dt>Khuyến mãi</dt><dd class="font-bold">-{{ number_format((int) $booking->promotion_discount_amount, 0, ',', '.') }} {{ $currency }}</dd></div>@endif
            <div class="flex justify-between border-t pt-2 text-lg"><dt class="font-extrabold">Tổng thanh toán</dt><dd class="font-extrabold">{{ number_format((int) $booking->total_amount, 0, ',', '.') }} {{ $currency }}</dd></div>
        </dl>
    </section>

    @if($bookingQrPayload)
        <p class="rounded-2xl bg-brand-start/10 p-4 text-center font-bold text-brand-start">Vui lòng xuất trình mã đơn hoặc QR đơn đặt vé tại quầy để nhận vé.</p>
    @else
        <p class="rounded-2xl bg-warning/10 p-4 text-center font-bold text-warning">Đơn chưa đủ điều kiện phát hành QR đơn đặt vé.</p>
    @endif
</div>
@endsection
