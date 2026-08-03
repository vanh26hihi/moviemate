@extends('layouts.user')

@php
    $paymentState = $booking->payment?->status;
    $isPaid = $booking->payment_status === 'paid' && $booking->booking_status === 'paid';
    $isExpired = $booking->booking_status === 'expired' || $paymentState === \App\Models\Payment::STATUS_EXPIRED;
    $isReview = $paymentState === \App\Models\Payment::STATUS_REVIEW;
    $isFailed = $paymentState === \App\Models\Payment::STATUS_FAILED;
    $isPending = !$isPaid && !$isExpired && !$isReview && !$isFailed && $booking->booking_status === 'pending_payment';
    $state = $isPaid ? [
        'title' => 'Thanh toán đã xác minh',
        'message' => 'Vé điện tử đã sẵn sàng và đơn đồ ăn (nếu có) đã được xác nhận.',
        'icon' => 'ph-check',
        'colour' => 'text-success',
    ] : ($isReview ? [
        'title' => 'Thanh toán cần đối soát',
        'message' => 'MovieMate chưa phát vé. Nhân viên sẽ kiểm tra giao dịch ZaloPay đã xác minh nhưng không khớp trạng thái đơn.',
        'icon' => 'ph-warning',
        'colour' => 'text-orange-400',
    ] : ($isExpired ? [
        'title' => 'Booking đã hết hạn',
        'message' => 'Ghế đã được giải phóng. Thanh toán đến muộn sẽ được chuyển đối soát và không tự phát vé.',
        'icon' => 'ph-clock-countdown',
        'colour' => 'text-gray-400',
    ] : ($isFailed ? [
        'title' => 'Thanh toán thất bại',
        'message' => 'ZaloPay chưa xác nhận giao dịch. Booking vẫn không phải là vé điện tử.',
        'icon' => 'ph-x',
        'colour' => 'text-error',
    ] : [
        'title' => 'Đang chờ thanh toán',
        'message' => 'Booking và ghế đang được giữ tạm. Chỉ callback/query ZaloPay hợp lệ mới có thể phát vé.',
        'icon' => 'ph-hourglass',
        'colour' => 'text-amber-400',
    ])));
@endphp

@section('title', $state['title'].' - MovieMate')

@section('content')
<main class="user-page-shell mx-auto flex min-h-[80vh] max-w-3xl items-center px-4 py-12 sm:px-6 lg:px-8">
    <section class="cinema-card w-full rounded-3xl p-7 sm:p-10" data-booking-state="{{ $isPaid ? 'paid' : ($paymentState ?: $booking->booking_status) }}">
        <nav class="mb-8 flex flex-wrap items-center gap-3 text-sm" aria-label="Tiến trình checkout">
            <span class="font-bold text-success">Ghế ✓</span><span class="app-muted">→</span>
            <span class="font-bold text-success">Đồ ăn ✓</span><span class="app-muted">→</span>
            <span class="font-bold text-success">Xác nhận ✓</span><span class="app-muted">→</span>
            <span class="font-bold {{ $state['colour'] }}" aria-current="step">Thanh toán</span>
        </nav>

        <div class="text-center">
            <i class="ph-bold {{ $state['icon'] }} text-6xl {{ $state['colour'] }}"></i>
            <h1 class="mt-4 text-3xl font-extrabold app-text">{{ $state['title'] }}</h1>
            <p class="mx-auto mt-3 max-w-xl app-muted">{{ $state['message'] }}</p>
        </div>

        <div class="mt-8 rounded-2xl app-secondary p-5 sm:p-6">
            <div class="flex items-center justify-between gap-4 border-b pb-4 app-border">
                <div><p class="text-xs app-muted">Mã booking</p><p class="font-mono text-xl font-bold text-brand-start">{{ $booking->booking_code }}</p></div>
                @if($isPaid)
                    <img src="{{ $booking->qr_code_url }}" alt="QR Code {{ $booking->booking_code }}" class="h-16 w-16 rounded bg-white p-1">
                @endif
            </div>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between gap-4"><dt class="app-muted">Phim</dt><dd class="text-right font-semibold app-text">{{ $booking->showtime->movie->title }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="app-muted">Ghế</dt><dd class="text-right font-semibold app-text">{{ $booking->seat_codes }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="app-muted">Tiền ghế</dt><dd class="font-semibold app-text">{{ number_format($booking->seat_subtotal, 0, ',', '.') }}đ</dd></div>
                <div class="flex justify-between gap-4"><dt class="app-muted">Đồ ăn</dt><dd class="font-semibold app-text">{{ number_format($booking->food_subtotal, 0, ',', '.') }}đ</dd></div>
                <div class="flex justify-between gap-4 border-t pt-3 app-border"><dt class="font-bold app-text">Tổng cộng</dt><dd class="text-xl font-extrabold text-brand-start">{{ number_format((int) $booking->total_amount, 0, ',', '.') }} VND</dd></div>
            </dl>
        </div>

        <p class="mt-5 text-center text-sm app-muted">Email nhận vé: <strong class="app-text">{{ $booking->recipient_email }}</strong></p>

        <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
            @if($isPaid)
                <a href="{{ route('user.bookings.ticket', $booking) }}" class="btn-primary">Xem vé QR của tôi</a>
            @elseif($isPending)
                <form method="POST" action="{{ route('payments.zalopay.initiate', $booking) }}">
                    @csrf
                    <button type="submit" class="btn-primary">Tiếp tục / đối soát ZaloPay</button>
                </form>
            @endif
            <a href="{{ route('home') }}" class="btn-secondary">Về trang chủ</a>
        </div>
    </section>
</main>
@endsection
