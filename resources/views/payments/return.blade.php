@extends('layouts.user')

@section('title', 'Trạng thái thanh toán - MovieMate')

@section('content')
@php
    $states = [
        \App\Models\Payment::STATUS_PENDING => [
            'title' => 'Đang chờ xác minh thanh toán',
            'message' => 'MovieMate chưa nhận được kết quả cuối cùng từ ZaloPay. Booking chưa phải là vé điện tử.',
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_SUCCESS => [
            'title' => 'Thanh toán đã được xác minh',
            'message' => 'Giao dịch đã được MovieMate xác minh và vé điện tử đã sẵn sàng.',
            'icon' => 'ph-check-circle',
            'colour' => 'text-success',
        ],
        \App\Models\Payment::STATUS_FAILED => [
            'title' => 'Thanh toán không thành công',
            'message' => 'ZaloPay đã trả về trạng thái không thành công. Booking này chưa có vé điện tử.',
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ],
        \App\Models\Payment::STATUS_REVIEW => [
            'title' => 'Giao dịch đang được đối soát',
            'message' => 'Dữ liệu cần được kiểm tra thêm. Đây chưa phải kết luận thanh toán thất bại và MovieMate chưa phát hành vé.',
            'icon' => 'ph-magnifying-glass',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_EXPIRED => [
            'title' => 'Lần thanh toán đã hết hạn',
            'message' => 'Thời hạn thanh toán đã kết thúc. Không có mã QR hoặc vé để tải xuống.',
            'icon' => 'ph-clock-countdown',
            'colour' => 'text-slate-500',
        ],
    ];
    $state = $states[$payment->status] ?? [
        'title' => 'Đang xử lý trạng thái',
        'message' => 'MovieMate đang kiểm tra dữ liệu giao dịch hiện tại.',
        'icon' => 'ph-spinner-gap',
        'colour' => 'text-slate-500',
    ];
    $isVerifiedPaid = $payment->status === \App\Models\Payment::STATUS_SUCCESS
        && $booking->payment_status === 'paid'
        && $booking->booking_status === 'paid';
@endphp

<main class="user-page-shell px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-3xl">
        <x-checkout-progress current="payment" class="mb-8" />

        <section class="cinema-card rounded-3xl p-5 text-center sm:p-8" data-payment-state="{{ $payment->status }}" aria-labelledby="payment-state-title">
            <span class="mx-auto inline-flex h-20 w-20 items-center justify-center rounded-full border app-border app-secondary {{ $state['colour'] }}" aria-hidden="true">
                <i class="ph-bold {{ $state['icon'] }} text-5xl"></i>
            </span>
            <p class="mt-5 text-xs font-extrabold uppercase tracking-[0.2em] {{ $state['colour'] }}">Trạng thái từ MovieMate</p>
            <h1 id="payment-state-title" class="mt-2 text-2xl font-extrabold app-text sm:text-3xl">{{ $state['title'] }}</h1>
            <p class="mx-auto mt-3 max-w-xl leading-relaxed app-muted">{{ $state['message'] }}</p>

            @if($payment->status === \App\Models\Payment::STATUS_PENDING && $payment->expires_at)
                <div class="mx-auto mt-6 max-w-md rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4" data-countdown-wrapper>
                    <p class="text-xs font-bold uppercase tracking-wide text-warning">Thời gian lần thanh toán còn lại</p>
                    <p class="mt-1 text-3xl font-extrabold app-text" data-countdown="{{ $payment->expires_at->toIso8601String() }}" data-expired-label="Đã hết thời gian">--:--</p>
                </div>
            @endif

            <dl class="mx-auto mt-7 max-w-xl rounded-2xl app-secondary p-5 text-left text-sm">
                @if($canViewBooking)
                    <div class="flex justify-between gap-4"><dt class="app-muted">Mã booking</dt><dd class="break-all text-right font-mono font-bold text-brand-start">{{ $booking->booking_code }}</dd></div>
                @else
                    <div class="flex justify-between gap-4"><dt class="app-muted">Lần thanh toán</dt><dd class="text-right font-mono font-bold text-brand-start">#{{ $payment->id }}</dd></div>
                @endif
                <div class="mt-3 flex justify-between gap-4"><dt class="app-muted">Kênh thanh toán</dt><dd class="font-bold app-text">ZaloPay</dd></div>
                <div class="mt-3 flex justify-between gap-4 border-t pt-3 app-border"><dt class="font-bold app-text">Số tiền</dt><dd class="font-extrabold text-brand-start">{{ number_format((int) $payment->amount, 0, ',', '.') }} {{ $payment->currency ?: 'VND' }}</dd></div>
            </dl>

            <p class="mx-auto mt-5 max-w-xl text-xs leading-relaxed app-muted">
                Trạng thái trên lấy từ cơ sở dữ liệu MovieMate. Dữ liệu trình duyệt
                {{ $integrityVerified ? 'có checksum hợp lệ' : 'không được xác thực' }}
                và không thể tự đánh dấu giao dịch là đã thanh toán.
            </p>

            @if($payment->status === \App\Models\Payment::STATUS_PENDING || $payment->status === \App\Models\Payment::STATUS_REVIEW)
                <div class="mx-auto mt-5 max-w-xl rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4 text-left text-sm leading-relaxed text-warning" role="note">
                    <strong>Không tạo lại thanh toán một cách mù.</strong> Nếu ZaloPay đã trừ tiền, hãy giữ mã booking và chờ MovieMate xác minh hoặc đối soát lần hiện tại.
                </div>
            @endif

            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                @if($isVerifiedPaid && $canViewTicket)
                    <a href="{{ route('user.bookings.ticket', $booking) }}" data-paid-ticket-link class="btn-primary">
                        <i class="ph-fill ph-ticket" aria-hidden="true"></i>
                        Mở vé điện tử
                    </a>
                @elseif($isVerifiedPaid)
                    <p class="app-muted">Liên kết mở vé an toàn được gửi riêng qua email và không được cấp bởi trang quay lại thanh toán.</p>
                @endif
                @if($canViewBooking)
                    <a href="{{ route('user.bookings.success', $booking) }}" class="btn-secondary">Xem chi tiết booking</a>
                @endif
            </div>
        </section>
    </div>
</main>
@endsection
