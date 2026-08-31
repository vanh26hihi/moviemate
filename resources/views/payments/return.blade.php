@extends('layouts.user')

@section('title', 'Trạng thái thanh toán - MovieMate')

@section('content')
@php
    $providerLabel = match ($payment->provider) {
        'vnpay' => 'VNPAY',
        'payos' => 'payOS',
        default => 'ZaloPay',
    };
    $isVerifiedPaid = $payment->status === \App\Models\Payment::STATUS_SUCCESS
        && $payment->verified_at !== null
        && $booking->payment_status === 'paid'
        && $booking->booking_status === 'paid';
    $isCinemaCancelled = $booking->booking_status === 'cancelled' && $booking->showtimeCancellationImpact !== null;
    $stateStatus = $payment->status === \App\Models\Payment::STATUS_SUCCESS && ! $isVerifiedPaid
        ? \App\Models\Payment::STATUS_REVIEW
        : $payment->status;
    if ($booking->booking_status === 'pending_payment'
        && $stateStatus === \App\Models\Payment::STATUS_FAILED) {
        $stateStatus = \App\Models\Payment::STATUS_PENDING;
    }
    if ($booking->booking_status === 'expired'
        && $payment->failure_reason === 'vnpay_terminal_expired') {
        $stateStatus = \App\Models\Payment::STATUS_EXPIRED;
    }
    $states = [
        \App\Models\Payment::STATUS_PENDING => [
            'title' => 'Đang xác minh kết quả thanh toán',
            'message' => "MovieMate chưa nhận được kết quả cuối cùng từ {$providerLabel}. Hệ thống sẽ cập nhật đơn khi có kết quả chính thức.",
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_SUCCESS => [
            'title' => 'Đặt vé thành công',
            'message' => 'Thanh toán đã được xác minh. Đơn đặt vé và QR đơn đặt vé đã sẵn sàng để xuất trình tại quầy.',
            'icon' => 'ph-check-circle',
            'colour' => 'text-success',
        ],
        \App\Models\Payment::STATUS_FAILED => [
            'title' => 'Thanh toán không thành công',
            'message' => "{$providerLabel} đã trả về trạng thái không thành công. Đơn đặt vé này chưa đủ điều kiện nhận vé.",
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ],
        \App\Models\Payment::STATUS_REVIEW => [
            'title' => 'Giao dịch cần được hỗ trợ',
            'message' => 'Dữ liệu giao dịch cần bộ phận hỗ trợ kiểm tra thêm trước khi có kết luận chính thức.',
            'icon' => 'ph-magnifying-glass',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_EXPIRED => [
            'title' => 'Lần thanh toán đã hết hạn',
            'message' => 'Thời hạn thanh toán đã kết thúc. Đơn chưa đủ điều kiện phát hành QR đơn đặt vé.',
            'icon' => 'ph-clock-countdown',
            'colour' => 'text-slate-500',
        ],
    ];
    $states[\App\Models\Payment::STATUS_UNRESOLVED] = $states[\App\Models\Payment::STATUS_PENDING];
    $states[\App\Models\Payment::STATUS_PROCESSING] = $states[\App\Models\Payment::STATUS_PENDING];
    $isConfirmedCancellation = $booking->booking_status === 'cancelled'
        && $payment->status === \App\Models\Payment::STATUS_FAILED;
    if ($isConfirmedCancellation) {
        $states[\App\Models\Payment::STATUS_FAILED] = [
            'title' => 'Thanh toán đã được hủy',
            'message' => $payment->provider === 'vnpay' && $payment->failure_reason === 'vnpay_customer_cancelled'
                ? 'Bạn đã hủy giao dịch VNPAY. Các ghế đã giữ cho đơn này đã được giải phóng.'
                : 'Các ghế đã giữ cho đơn này đã được giải phóng.',
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ];
    }
    if (($cancelRequested ?? false) && ! $isConfirmedCancellation
        && in_array($payment->status, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true)) {
        $states[$stateStatus] = [
            'title' => 'Đang xác minh việc hủy thanh toán',
            'message' => 'Ghế của bạn vẫn được giữ tạm thời để tránh mất vé trong khi hệ thống xác minh trạng thái giao dịch.',
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ];
    }
    if ($isCinemaCancelled) {
        $stateStatus = 'showtime_cancelled';
        $states[$stateStatus] = [
            'title' => 'Suất chiếu đã bị rạp hủy',
            'message' => match($booking->refundCase?->status) {
                \App\Models\RefundCase::STATUS_REQUIRED => 'Thanh toán đã được xác minh. Cần xử lý hoàn tiền; đơn vẫn bị hủy và không phát hành QR hoặc vé sử dụng.',
                \App\Models\RefundCase::STATUS_RESOLVED => 'Thanh toán đã được giữ trong lịch sử và rạp đã ghi nhận hoàn tiền.',
                default => 'Bạn chưa có khoản thanh toán cần hoàn.',
            },
            'icon' => 'ph-calendar-x',
            'colour' => 'text-error',
        ];
    }
    $state = $states[$stateStatus] ?? [
        'title' => 'Đang xử lý trạng thái',
        'message' => 'MovieMate đang kiểm tra dữ liệu giao dịch hiện tại.',
        'icon' => 'ph-spinner-gap',
        'colour' => 'text-slate-500',
    ];
    $holdExpiresAt = $booking->expires_at ?? $payment->expires_at;
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

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true) && $holdExpiresAt)
                <div class="mx-auto mt-6 max-w-md rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4" data-countdown-wrapper>
                    <p class="text-xs font-bold uppercase tracking-wide text-warning">Thời gian lần thanh toán còn lại</p>
                    <p class="mt-1 text-3xl font-extrabold app-text" data-countdown="{{ $holdExpiresAt->toIso8601String() }}" data-expired-label="Đã hết thời gian">--:--</p>
                </div>
            @endif

            <dl class="mx-auto mt-7 max-w-xl rounded-2xl app-secondary p-5 text-left text-sm">
                @if($canViewBooking)
                    <div class="flex justify-between gap-4"><dt class="app-muted">Mã đặt vé</dt><dd class="break-all text-right font-mono font-bold text-brand-start">{{ $booking->booking_code }}</dd></div>
                @else
                    <div class="flex justify-between gap-4"><dt class="app-muted">Lần thanh toán</dt><dd class="text-right font-mono font-bold text-brand-start">#{{ $payment->id }}</dd></div>
                @endif
                <div class="mt-3 flex justify-between gap-4"><dt class="app-muted">Kênh thanh toán</dt><dd class="font-bold app-text">{{ $providerLabel }}</dd></div>
                <div class="mt-3 flex justify-between gap-4 border-t pt-3 app-border"><dt class="font-bold app-text">Số tiền</dt><dd class="font-extrabold text-brand-start">{{ number_format((int) $payment->amount, 0, ',', '.') }} {{ ($payment->currency ?: 'VND') === 'VND' ? 'VNĐ' : $payment->currency }}</dd></div>
            </dl>

            <p class="mx-auto mt-5 max-w-xl text-xs leading-relaxed app-muted">
                Trạng thái trên lấy từ cơ sở dữ liệu MovieMate. Dữ liệu trình duyệt
                {{ $integrityVerified ? 'có checksum hợp lệ' : 'không được xác thực' }}
                và không thể tự đánh dấu giao dịch là đã thanh toán.
            </p>

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING, \App\Models\Payment::STATUS_REVIEW], true))
                <div class="mx-auto mt-5 max-w-xl rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4 text-left text-sm leading-relaxed text-warning" role="note">
                    <strong>Không tạo lại thanh toán khi chưa rõ kết quả.</strong> Nếu {{ $providerLabel }} đã trừ tiền, hãy giữ mã đặt vé và chờ MovieMate xác minh hoặc đối soát giao dịch hiện tại.
                </div>
            @endif

            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                @if($isVerifiedPaid && $canViewTicket)
                    <a href="{{ route('user.bookings.ticket', $booking) }}" data-paid-ticket-link class="btn-primary">
                        <i class="ph-fill ph-ticket" aria-hidden="true"></i>
                        Mở đơn đặt vé
                    </a>
                @elseif($isVerifiedPaid)
                    <p class="app-muted">Liên kết mở đơn đặt vé an toàn được gửi riêng qua email và không được cấp bởi trang quay lại thanh toán.</p>
                @endif
                @if($canViewBooking)
                    <a href="{{ route('user.bookings.success', $booking) }}" class="btn-secondary">Xem chi tiết đơn đặt vé</a>
                @endif
            </div>
        </section>
    </div>
</main>
@endsection
@extends('layouts.user')

@section('title', 'Trạng thái thanh toán - MovieMate')

@section('content')
@php
    $providerLabel = match ($payment->provider) {
        'vnpay' => 'VNPAY',
        'payos' => 'payOS',
        default => 'ZaloPay',
    };
    $isVerifiedPaid = $payment->status === \App\Models\Payment::STATUS_SUCCESS
        && $payment->verified_at !== null
        && $booking->payment_status === 'paid'
        && $booking->booking_status === 'paid';
    $isCinemaCancelled = $booking->booking_status === 'cancelled' && $booking->showtimeCancellationImpact !== null;
    $stateStatus = $payment->status === \App\Models\Payment::STATUS_SUCCESS && ! $isVerifiedPaid
        ? \App\Models\Payment::STATUS_REVIEW
        : $payment->status;
    if ($booking->booking_status === 'pending_payment'
        && $stateStatus === \App\Models\Payment::STATUS_FAILED) {
        $stateStatus = \App\Models\Payment::STATUS_PENDING;
    }
    if ($booking->booking_status === 'expired'
        && $payment->failure_reason === 'vnpay_terminal_expired') {
        $stateStatus = \App\Models\Payment::STATUS_EXPIRED;
    }
    $states = [
        \App\Models\Payment::STATUS_PENDING => [
            'title' => 'Đang xác minh kết quả thanh toán',
            'message' => "MovieMate chưa nhận được kết quả cuối cùng từ {$providerLabel}. Hệ thống sẽ cập nhật đơn khi có kết quả chính thức.",
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_SUCCESS => [
            'title' => 'Đặt vé thành công',
            'message' => 'Thanh toán đã được xác minh. Đơn đặt vé và QR đơn đặt vé đã sẵn sàng để xuất trình tại quầy.',
            'icon' => 'ph-check-circle',
            'colour' => 'text-success',
        ],
        \App\Models\Payment::STATUS_FAILED => [
            'title' => 'Thanh toán không thành công',
            'message' => "{$providerLabel} đã trả về trạng thái không thành công. Đơn đặt vé này chưa đủ điều kiện nhận vé.",
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ],
        \App\Models\Payment::STATUS_REVIEW => [
            'title' => 'Giao dịch cần được hỗ trợ',
            'message' => 'Dữ liệu giao dịch cần bộ phận hỗ trợ kiểm tra thêm trước khi có kết luận chính thức.',
            'icon' => 'ph-magnifying-glass',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_EXPIRED => [
            'title' => 'Lần thanh toán đã hết hạn',
            'message' => 'Thời hạn thanh toán đã kết thúc. Đơn chưa đủ điều kiện phát hành QR đơn đặt vé.',
            'icon' => 'ph-clock-countdown',
            'colour' => 'text-slate-500',
        ],
    ];
    $states[\App\Models\Payment::STATUS_UNRESOLVED] = $states[\App\Models\Payment::STATUS_PENDING];
    $states[\App\Models\Payment::STATUS_PROCESSING] = $states[\App\Models\Payment::STATUS_PENDING];
    $isConfirmedCancellation = $booking->booking_status === 'cancelled'
        && $payment->status === \App\Models\Payment::STATUS_FAILED;
    if ($isConfirmedCancellation) {
        $states[\App\Models\Payment::STATUS_FAILED] = [
            'title' => 'Thanh toán đã được hủy',
            'message' => $payment->provider === 'vnpay' && $payment->failure_reason === 'vnpay_customer_cancelled'
                ? 'Bạn đã hủy giao dịch VNPAY. Các ghế đã giữ cho đơn này đã được giải phóng.'
                : 'Các ghế đã giữ cho đơn này đã được giải phóng.',
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ];
    }
    if (($cancelRequested ?? false) && ! $isConfirmedCancellation
        && in_array($payment->status, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true)) {
        $states[$stateStatus] = [
            'title' => 'Đang xác minh việc hủy thanh toán',
            'message' => 'Ghế của bạn vẫn được giữ tạm thời để tránh mất vé trong khi hệ thống xác minh trạng thái giao dịch.',
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ];
    }
    if ($isCinemaCancelled) {
        $stateStatus = 'showtime_cancelled';
        $states[$stateStatus] = [
            'title' => 'Suất chiếu đã bị rạp hủy',
            'message' => match($booking->refundCase?->status) {
                \App\Models\RefundCase::STATUS_REQUIRED => 'Thanh toán đã được xác minh. Cần xử lý hoàn tiền; đơn vẫn bị hủy và không phát hành QR hoặc vé sử dụng.',
                \App\Models\RefundCase::STATUS_RESOLVED => 'Thanh toán đã được giữ trong lịch sử và rạp đã ghi nhận hoàn tiền.',
                default => 'Bạn chưa có khoản thanh toán cần hoàn.',
            },
            'icon' => 'ph-calendar-x',
            'colour' => 'text-error',
        ];
    }
    $state = $states[$stateStatus] ?? [
        'title' => 'Đang xử lý trạng thái',
        'message' => 'MovieMate đang kiểm tra dữ liệu giao dịch hiện tại.',
        'icon' => 'ph-spinner-gap',
        'colour' => 'text-slate-500',
    ];
    $holdExpiresAt = $booking->expires_at ?? $payment->expires_at;
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

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true) && $holdExpiresAt)
                <div class="mx-auto mt-6 max-w-md rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4" data-countdown-wrapper>
                    <p class="text-xs font-bold uppercase tracking-wide text-warning">Thời gian lần thanh toán còn lại</p>
                    <p class="mt-1 text-3xl font-extrabold app-text" data-countdown="{{ $holdExpiresAt->toIso8601String() }}" data-expired-label="Đã hết thời gian">--:--</p>
                </div>
            @endif

            <dl class="mx-auto mt-7 max-w-xl rounded-2xl app-secondary p-5 text-left text-sm">
                @if($canViewBooking)
                    <div class="flex justify-between gap-4"><dt class="app-muted">Mã đặt vé</dt><dd class="break-all text-right font-mono font-bold text-brand-start">{{ $booking->booking_code }}</dd></div>
                @else
                    <div class="flex justify-between gap-4"><dt class="app-muted">Lần thanh toán</dt><dd class="text-right font-mono font-bold text-brand-start">#{{ $payment->id }}</dd></div>
                @endif
                <div class="mt-3 flex justify-between gap-4"><dt class="app-muted">Kênh thanh toán</dt><dd class="font-bold app-text">{{ $providerLabel }}</dd></div>
                <div class="mt-3 flex justify-between gap-4 border-t pt-3 app-border"><dt class="font-bold app-text">Số tiền</dt><dd class="font-extrabold text-brand-start">{{ number_format((int) $payment->amount, 0, ',', '.') }} {{ ($payment->currency ?: 'VND') === 'VND' ? 'VNĐ' : $payment->currency }}</dd></div>
            </dl>

            <p class="mx-auto mt-5 max-w-xl text-xs leading-relaxed app-muted">
                Trạng thái trên lấy từ cơ sở dữ liệu MovieMate. Dữ liệu trình duyệt
                {{ $integrityVerified ? 'có checksum hợp lệ' : 'không được xác thực' }}
                và không thể tự đánh dấu giao dịch là đã thanh toán.
            </p>

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING, \App\Models\Payment::STATUS_REVIEW], true))
                <div class="mx-auto mt-5 max-w-xl rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4 text-left text-sm leading-relaxed text-warning" role="note">
                    <strong>Không tạo lại thanh toán khi chưa rõ kết quả.</strong> Nếu {{ $providerLabel }} đã trừ tiền, hãy giữ mã đặt vé và chờ MovieMate xác minh hoặc đối soát giao dịch hiện tại.
                </div>
            @endif

            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                @if($isVerifiedPaid && $canViewTicket)
                    <a href="{{ route('user.bookings.ticket', $booking) }}" data-paid-ticket-link class="btn-primary">
                        <i class="ph-fill ph-ticket" aria-hidden="true"></i>
                        Mở đơn đặt vé
                    </a>
                @elseif($isVerifiedPaid)
                    <p class="app-muted">Liên kết mở đơn đặt vé an toàn được gửi riêng qua email và không được cấp bởi trang quay lại thanh toán.</p>
                @endif
                @if($canViewBooking)
                    <a href="{{ route('user.bookings.success', $booking) }}" class="btn-secondary">Xem chi tiết đơn đặt vé</a>
                @endif
            </div>
        </section>
    </div>
</main>
@endsection
@extends('layouts.user')

@section('title', 'Trạng thái thanh toán - MovieMate')

@section('content')
@php
    $providerLabel = match ($payment->provider) {
        'vnpay' => 'VNPAY',
        'payos' => 'payOS',
        default => 'ZaloPay',
    };
    $isVerifiedPaid = $payment->status === \App\Models\Payment::STATUS_SUCCESS
        && $payment->verified_at !== null
        && $booking->payment_status === 'paid'
        && $booking->booking_status === 'paid';
    $isCinemaCancelled = $booking->booking_status === 'cancelled' && $booking->showtimeCancellationImpact !== null;
    $stateStatus = $payment->status === \App\Models\Payment::STATUS_SUCCESS && ! $isVerifiedPaid
        ? \App\Models\Payment::STATUS_REVIEW
        : $payment->status;
    if ($booking->booking_status === 'pending_payment'
        && $stateStatus === \App\Models\Payment::STATUS_FAILED) {
        $stateStatus = \App\Models\Payment::STATUS_PENDING;
    }
    if ($booking->booking_status === 'expired'
        && $payment->failure_reason === 'vnpay_terminal_expired') {
        $stateStatus = \App\Models\Payment::STATUS_EXPIRED;
    }
    $states = [
        \App\Models\Payment::STATUS_PENDING => [
            'title' => 'Đang xác minh kết quả thanh toán',
            'message' => "MovieMate chưa nhận được kết quả cuối cùng từ {$providerLabel}. Hệ thống sẽ cập nhật đơn khi có kết quả chính thức.",
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_SUCCESS => [
            'title' => 'Đặt vé thành công',
            'message' => 'Thanh toán đã được xác minh. Đơn đặt vé và QR đơn đặt vé đã sẵn sàng để xuất trình tại quầy.',
            'icon' => 'ph-check-circle',
            'colour' => 'text-success',
        ],
        \App\Models\Payment::STATUS_FAILED => [
            'title' => 'Thanh toán không thành công',
            'message' => "{$providerLabel} đã trả về trạng thái không thành công. Đơn đặt vé này chưa đủ điều kiện nhận vé.",
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ],
        \App\Models\Payment::STATUS_REVIEW => [
            'title' => 'Giao dịch cần được hỗ trợ',
            'message' => 'Dữ liệu giao dịch cần bộ phận hỗ trợ kiểm tra thêm trước khi có kết luận chính thức.',
            'icon' => 'ph-magnifying-glass',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_EXPIRED => [
            'title' => 'Lần thanh toán đã hết hạn',
            'message' => 'Thời hạn thanh toán đã kết thúc. Đơn chưa đủ điều kiện phát hành QR đơn đặt vé.',
            'icon' => 'ph-clock-countdown',
            'colour' => 'text-slate-500',
        ],
    ];
    $states[\App\Models\Payment::STATUS_UNRESOLVED] = $states[\App\Models\Payment::STATUS_PENDING];
    $states[\App\Models\Payment::STATUS_PROCESSING] = $states[\App\Models\Payment::STATUS_PENDING];
    $isConfirmedCancellation = $booking->booking_status === 'cancelled'
        && $payment->status === \App\Models\Payment::STATUS_FAILED;
    if ($isConfirmedCancellation) {
        $states[\App\Models\Payment::STATUS_FAILED] = [
            'title' => 'Thanh toán đã được hủy',
            'message' => $payment->provider === 'vnpay' && $payment->failure_reason === 'vnpay_customer_cancelled'
                ? 'Bạn đã hủy giao dịch VNPAY. Các ghế đã giữ cho đơn này đã được giải phóng.'
                : 'Các ghế đã giữ cho đơn này đã được giải phóng.',
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ];
    }
    if (($cancelRequested ?? false) && ! $isConfirmedCancellation
        && in_array($payment->status, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true)) {
        $states[$stateStatus] = [
            'title' => 'Đang xác minh việc hủy thanh toán',
            'message' => 'Ghế của bạn vẫn được giữ tạm thời để tránh mất vé trong khi hệ thống xác minh trạng thái giao dịch.',
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ];
    }
    if ($isCinemaCancelled) {
        $stateStatus = 'showtime_cancelled';
        $states[$stateStatus] = [
            'title' => 'Suất chiếu đã bị rạp hủy',
            'message' => match($booking->refundCase?->status) {
                \App\Models\RefundCase::STATUS_REQUIRED => 'Thanh toán đã được xác minh. Cần xử lý hoàn tiền; đơn vẫn bị hủy và không phát hành QR hoặc vé sử dụng.',
                \App\Models\RefundCase::STATUS_RESOLVED => 'Thanh toán đã được giữ trong lịch sử và rạp đã ghi nhận hoàn tiền.',
                default => 'Bạn chưa có khoản thanh toán cần hoàn.',
            },
            'icon' => 'ph-calendar-x',
            'colour' => 'text-error',
        ];
    }
    $state = $states[$stateStatus] ?? [
        'title' => 'Đang xử lý trạng thái',
        'message' => 'MovieMate đang kiểm tra dữ liệu giao dịch hiện tại.',
        'icon' => 'ph-spinner-gap',
        'colour' => 'text-slate-500',
    ];
    $holdExpiresAt = $booking->expires_at ?? $payment->expires_at;
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

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true) && $holdExpiresAt)
                <div class="mx-auto mt-6 max-w-md rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4" data-countdown-wrapper>
                    <p class="text-xs font-bold uppercase tracking-wide text-warning">Thời gian lần thanh toán còn lại</p>
                    <p class="mt-1 text-3xl font-extrabold app-text" data-countdown="{{ $holdExpiresAt->toIso8601String() }}" data-expired-label="Đã hết thời gian">--:--</p>
                </div>
            @endif

            <dl class="mx-auto mt-7 max-w-xl rounded-2xl app-secondary p-5 text-left text-sm">
                @if($canViewBooking)
                    <div class="flex justify-between gap-4"><dt class="app-muted">Mã đặt vé</dt><dd class="break-all text-right font-mono font-bold text-brand-start">{{ $booking->booking_code }}</dd></div>
                @else
                    <div class="flex justify-between gap-4"><dt class="app-muted">Lần thanh toán</dt><dd class="text-right font-mono font-bold text-brand-start">#{{ $payment->id }}</dd></div>
                @endif
                <div class="mt-3 flex justify-between gap-4"><dt class="app-muted">Kênh thanh toán</dt><dd class="font-bold app-text">{{ $providerLabel }}</dd></div>
                <div class="mt-3 flex justify-between gap-4 border-t pt-3 app-border"><dt class="font-bold app-text">Số tiền</dt><dd class="font-extrabold text-brand-start">{{ number_format((int) $payment->amount, 0, ',', '.') }} {{ ($payment->currency ?: 'VND') === 'VND' ? 'VNĐ' : $payment->currency }}</dd></div>
            </dl>

            <p class="mx-auto mt-5 max-w-xl text-xs leading-relaxed app-muted">
                Trạng thái trên lấy từ cơ sở dữ liệu MovieMate. Dữ liệu trình duyệt
                {{ $integrityVerified ? 'có checksum hợp lệ' : 'không được xác thực' }}
                và không thể tự đánh dấu giao dịch là đã thanh toán.
            </p>

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING, \App\Models\Payment::STATUS_REVIEW], true))
                <div class="mx-auto mt-5 max-w-xl rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4 text-left text-sm leading-relaxed text-warning" role="note">
                    <strong>Không tạo lại thanh toán khi chưa rõ kết quả.</strong> Nếu {{ $providerLabel }} đã trừ tiền, hãy giữ mã đặt vé và chờ MovieMate xác minh hoặc đối soát giao dịch hiện tại.
                </div>
            @endif

            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                @if($isVerifiedPaid && $canViewTicket)
                    <a href="{{ route('user.bookings.ticket', $booking) }}" data-paid-ticket-link class="btn-primary">
                        <i class="ph-fill ph-ticket" aria-hidden="true"></i>
                        Mở đơn đặt vé
                    </a>
                @elseif($isVerifiedPaid)
                    <p class="app-muted">Liên kết mở đơn đặt vé an toàn được gửi riêng qua email và không được cấp bởi trang quay lại thanh toán.</p>
                @endif
                @if($canViewBooking)
                    <a href="{{ route('user.bookings.success', $booking) }}" class="btn-secondary">Xem chi tiết đơn đặt vé</a>
                @endif
            </div>
        </section>
    </div>
</main>
@endsection
@extends('layouts.user')

@section('title', 'Trạng thái thanh toán - MovieMate')

@section('content')
@php
    $providerLabel = match ($payment->provider) {
        'vnpay' => 'VNPAY',
        'payos' => 'payOS',
        default => 'ZaloPay',
    };
    $isVerifiedPaid = $payment->status === \App\Models\Payment::STATUS_SUCCESS
        && $payment->verified_at !== null
        && $booking->payment_status === 'paid'
        && $booking->booking_status === 'paid';
    $isCinemaCancelled = $booking->booking_status === 'cancelled' && $booking->showtimeCancellationImpact !== null;
    $stateStatus = $payment->status === \App\Models\Payment::STATUS_SUCCESS && ! $isVerifiedPaid
        ? \App\Models\Payment::STATUS_REVIEW
        : $payment->status;
    if ($booking->booking_status === 'pending_payment'
        && $stateStatus === \App\Models\Payment::STATUS_FAILED) {
        $stateStatus = \App\Models\Payment::STATUS_PENDING;
    }
    if ($booking->booking_status === 'expired'
        && $payment->failure_reason === 'vnpay_terminal_expired') {
        $stateStatus = \App\Models\Payment::STATUS_EXPIRED;
    }
    $states = [
        \App\Models\Payment::STATUS_PENDING => [
            'title' => 'Đang xác minh kết quả thanh toán',
            'message' => "MovieMate chưa nhận được kết quả cuối cùng từ {$providerLabel}. Hệ thống sẽ cập nhật đơn khi có kết quả chính thức.",
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_SUCCESS => [
            'title' => 'Đặt vé thành công',
            'message' => 'Thanh toán đã được xác minh. Đơn đặt vé và QR đơn đặt vé đã sẵn sàng để xuất trình tại quầy.',
            'icon' => 'ph-check-circle',
            'colour' => 'text-success',
        ],
        \App\Models\Payment::STATUS_FAILED => [
            'title' => 'Thanh toán không thành công',
            'message' => "{$providerLabel} đã trả về trạng thái không thành công. Đơn đặt vé này chưa đủ điều kiện nhận vé.",
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ],
        \App\Models\Payment::STATUS_REVIEW => [
            'title' => 'Giao dịch cần được hỗ trợ',
            'message' => 'Dữ liệu giao dịch cần bộ phận hỗ trợ kiểm tra thêm trước khi có kết luận chính thức.',
            'icon' => 'ph-magnifying-glass',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_EXPIRED => [
            'title' => 'Lần thanh toán đã hết hạn',
            'message' => 'Thời hạn thanh toán đã kết thúc. Đơn chưa đủ điều kiện phát hành QR đơn đặt vé.',
            'icon' => 'ph-clock-countdown',
            'colour' => 'text-slate-500',
        ],
    ];
    $states[\App\Models\Payment::STATUS_UNRESOLVED] = $states[\App\Models\Payment::STATUS_PENDING];
    $states[\App\Models\Payment::STATUS_PROCESSING] = $states[\App\Models\Payment::STATUS_PENDING];
    $isConfirmedCancellation = $booking->booking_status === 'cancelled'
        && $payment->status === \App\Models\Payment::STATUS_FAILED;
    if ($isConfirmedCancellation) {
        $states[\App\Models\Payment::STATUS_FAILED] = [
            'title' => 'Thanh toán đã được hủy',
            'message' => $payment->provider === 'vnpay' && $payment->failure_reason === 'vnpay_customer_cancelled'
                ? 'Bạn đã hủy giao dịch VNPAY. Các ghế đã giữ cho đơn này đã được giải phóng.'
                : 'Các ghế đã giữ cho đơn này đã được giải phóng.',
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ];
    }
    if (($cancelRequested ?? false) && ! $isConfirmedCancellation
        && in_array($payment->status, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true)) {
        $states[$stateStatus] = [
            'title' => 'Đang xác minh việc hủy thanh toán',
            'message' => 'Ghế của bạn vẫn được giữ tạm thời để tránh mất vé trong khi hệ thống xác minh trạng thái giao dịch.',
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ];
    }
    if ($isCinemaCancelled) {
        $stateStatus = 'showtime_cancelled';
        $states[$stateStatus] = [
            'title' => 'Suất chiếu đã bị rạp hủy',
            'message' => match($booking->refundCase?->status) {
                \App\Models\RefundCase::STATUS_REQUIRED => 'Thanh toán đã được xác minh. Cần xử lý hoàn tiền; đơn vẫn bị hủy và không phát hành QR hoặc vé sử dụng.',
                \App\Models\RefundCase::STATUS_RESOLVED => 'Thanh toán đã được giữ trong lịch sử và rạp đã ghi nhận hoàn tiền.',
                default => 'Bạn chưa có khoản thanh toán cần hoàn.',
            },
            'icon' => 'ph-calendar-x',
            'colour' => 'text-error',
        ];
    }
    $state = $states[$stateStatus] ?? [
        'title' => 'Đang xử lý trạng thái',
        'message' => 'MovieMate đang kiểm tra dữ liệu giao dịch hiện tại.',
        'icon' => 'ph-spinner-gap',
        'colour' => 'text-slate-500',
    ];
    $holdExpiresAt = $booking->expires_at ?? $payment->expires_at;
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

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true) && $holdExpiresAt)
                <div class="mx-auto mt-6 max-w-md rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4" data-countdown-wrapper>
                    <p class="text-xs font-bold uppercase tracking-wide text-warning">Thời gian lần thanh toán còn lại</p>
                    <p class="mt-1 text-3xl font-extrabold app-text" data-countdown="{{ $holdExpiresAt->toIso8601String() }}" data-expired-label="Đã hết thời gian">--:--</p>
                </div>
            @endif

            <dl class="mx-auto mt-7 max-w-xl rounded-2xl app-secondary p-5 text-left text-sm">
                @if($canViewBooking)
                    <div class="flex justify-between gap-4"><dt class="app-muted">Mã đặt vé</dt><dd class="break-all text-right font-mono font-bold text-brand-start">{{ $booking->booking_code }}</dd></div>
                @else
                    <div class="flex justify-between gap-4"><dt class="app-muted">Lần thanh toán</dt><dd class="text-right font-mono font-bold text-brand-start">#{{ $payment->id }}</dd></div>
                @endif
                <div class="mt-3 flex justify-between gap-4"><dt class="app-muted">Kênh thanh toán</dt><dd class="font-bold app-text">{{ $providerLabel }}</dd></div>
                <div class="mt-3 flex justify-between gap-4 border-t pt-3 app-border"><dt class="font-bold app-text">Số tiền</dt><dd class="font-extrabold text-brand-start">{{ number_format((int) $payment->amount, 0, ',', '.') }} {{ ($payment->currency ?: 'VND') === 'VND' ? 'VNĐ' : $payment->currency }}</dd></div>
            </dl>

            <p class="mx-auto mt-5 max-w-xl text-xs leading-relaxed app-muted">
                Trạng thái trên lấy từ cơ sở dữ liệu MovieMate. Dữ liệu trình duyệt
                {{ $integrityVerified ? 'có checksum hợp lệ' : 'không được xác thực' }}
                và không thể tự đánh dấu giao dịch là đã thanh toán.
            </p>

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING, \App\Models\Payment::STATUS_REVIEW], true))
                <div class="mx-auto mt-5 max-w-xl rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4 text-left text-sm leading-relaxed text-warning" role="note">
                    <strong>Không tạo lại thanh toán khi chưa rõ kết quả.</strong> Nếu {{ $providerLabel }} đã trừ tiền, hãy giữ mã đặt vé và chờ MovieMate xác minh hoặc đối soát giao dịch hiện tại.
                </div>
            @endif

            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                @if($isVerifiedPaid && $canViewTicket)
                    <a href="{{ route('user.bookings.ticket', $booking) }}" data-paid-ticket-link class="btn-primary">
                        <i class="ph-fill ph-ticket" aria-hidden="true"></i>
                        Mở đơn đặt vé
                    </a>
                @elseif($isVerifiedPaid)
                    <p class="app-muted">Liên kết mở đơn đặt vé an toàn được gửi riêng qua email và không được cấp bởi trang quay lại thanh toán.</p>
                @endif
                @if($canViewBooking)
                    <a href="{{ route('user.bookings.success', $booking) }}" class="btn-secondary">Xem chi tiết đơn đặt vé</a>
                @endif
            </div>
        </section>
    </div>
</main>
@endsection
@extends('layouts.user')

@section('title', 'Trạng thái thanh toán - MovieMate')

@section('content')
@php
    $providerLabel = match ($payment->provider) {
        'vnpay' => 'VNPAY',
        'payos' => 'payOS',
        default => 'ZaloPay',
    };
    $isVerifiedPaid = $payment->status === \App\Models\Payment::STATUS_SUCCESS
        && $payment->verified_at !== null
        && $booking->payment_status === 'paid'
        && $booking->booking_status === 'paid';
    $isCinemaCancelled = $booking->booking_status === 'cancelled' && $booking->showtimeCancellationImpact !== null;
    $stateStatus = $payment->status === \App\Models\Payment::STATUS_SUCCESS && ! $isVerifiedPaid
        ? \App\Models\Payment::STATUS_REVIEW
        : $payment->status;
    if ($booking->booking_status === 'pending_payment'
        && $stateStatus === \App\Models\Payment::STATUS_FAILED) {
        $stateStatus = \App\Models\Payment::STATUS_PENDING;
    }
    if ($booking->booking_status === 'expired'
        && $payment->failure_reason === 'vnpay_terminal_expired') {
        $stateStatus = \App\Models\Payment::STATUS_EXPIRED;
    }
    $states = [
        \App\Models\Payment::STATUS_PENDING => [
            'title' => 'Đang xác minh kết quả thanh toán',
            'message' => "MovieMate chưa nhận được kết quả cuối cùng từ {$providerLabel}. Hệ thống sẽ cập nhật đơn khi có kết quả chính thức.",
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_SUCCESS => [
            'title' => 'Đặt vé thành công',
            'message' => 'Thanh toán đã được xác minh. Đơn đặt vé và QR đơn đặt vé đã sẵn sàng để xuất trình tại quầy.',
            'icon' => 'ph-check-circle',
            'colour' => 'text-success',
        ],
        \App\Models\Payment::STATUS_FAILED => [
            'title' => 'Thanh toán không thành công',
            'message' => "{$providerLabel} đã trả về trạng thái không thành công. Đơn đặt vé này chưa đủ điều kiện nhận vé.",
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ],
        \App\Models\Payment::STATUS_REVIEW => [
            'title' => 'Giao dịch cần được hỗ trợ',
            'message' => 'Dữ liệu giao dịch cần bộ phận hỗ trợ kiểm tra thêm trước khi có kết luận chính thức.',
            'icon' => 'ph-magnifying-glass',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_EXPIRED => [
            'title' => 'Lần thanh toán đã hết hạn',
            'message' => 'Thời hạn thanh toán đã kết thúc. Đơn chưa đủ điều kiện phát hành QR đơn đặt vé.',
            'icon' => 'ph-clock-countdown',
            'colour' => 'text-slate-500',
        ],
    ];
    $states[\App\Models\Payment::STATUS_UNRESOLVED] = $states[\App\Models\Payment::STATUS_PENDING];
    $states[\App\Models\Payment::STATUS_PROCESSING] = $states[\App\Models\Payment::STATUS_PENDING];
    $isConfirmedCancellation = $booking->booking_status === 'cancelled'
        && $payment->status === \App\Models\Payment::STATUS_FAILED;
    if ($isConfirmedCancellation) {
        $states[\App\Models\Payment::STATUS_FAILED] = [
            'title' => 'Thanh toán đã được hủy',
            'message' => $payment->provider === 'vnpay' && $payment->failure_reason === 'vnpay_customer_cancelled'
                ? 'Bạn đã hủy giao dịch VNPAY. Các ghế đã giữ cho đơn này đã được giải phóng.'
                : 'Các ghế đã giữ cho đơn này đã được giải phóng.',
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ];
    }
    if (($cancelRequested ?? false) && ! $isConfirmedCancellation
        && in_array($payment->status, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true)) {
        $states[$stateStatus] = [
            'title' => 'Đang xác minh việc hủy thanh toán',
            'message' => 'Ghế của bạn vẫn được giữ tạm thời để tránh mất vé trong khi hệ thống xác minh trạng thái giao dịch.',
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ];
    }
    if ($isCinemaCancelled) {
        $stateStatus = 'showtime_cancelled';
        $states[$stateStatus] = [
            'title' => 'Suất chiếu đã bị rạp hủy',
            'message' => match($booking->refundCase?->status) {
                \App\Models\RefundCase::STATUS_REQUIRED => 'Thanh toán đã được xác minh. Cần xử lý hoàn tiền; đơn vẫn bị hủy và không phát hành QR hoặc vé sử dụng.',
                \App\Models\RefundCase::STATUS_RESOLVED => 'Thanh toán đã được giữ trong lịch sử và rạp đã ghi nhận hoàn tiền.',
                default => 'Bạn chưa có khoản thanh toán cần hoàn.',
            },
            'icon' => 'ph-calendar-x',
            'colour' => 'text-error',
        ];
    }
    $state = $states[$stateStatus] ?? [
        'title' => 'Đang xử lý trạng thái',
        'message' => 'MovieMate đang kiểm tra dữ liệu giao dịch hiện tại.',
        'icon' => 'ph-spinner-gap',
        'colour' => 'text-slate-500',
    ];
    $holdExpiresAt = $booking->expires_at ?? $payment->expires_at;
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

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true) && $holdExpiresAt)
                <div class="mx-auto mt-6 max-w-md rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4" data-countdown-wrapper>
                    <p class="text-xs font-bold uppercase tracking-wide text-warning">Thời gian lần thanh toán còn lại</p>
                    <p class="mt-1 text-3xl font-extrabold app-text" data-countdown="{{ $holdExpiresAt->toIso8601String() }}" data-expired-label="Đã hết thời gian">--:--</p>
                </div>
            @endif

            <dl class="mx-auto mt-7 max-w-xl rounded-2xl app-secondary p-5 text-left text-sm">
                @if($canViewBooking)
                    <div class="flex justify-between gap-4"><dt class="app-muted">Mã đặt vé</dt><dd class="break-all text-right font-mono font-bold text-brand-start">{{ $booking->booking_code }}</dd></div>
                @else
                    <div class="flex justify-between gap-4"><dt class="app-muted">Lần thanh toán</dt><dd class="text-right font-mono font-bold text-brand-start">#{{ $payment->id }}</dd></div>
                @endif
                <div class="mt-3 flex justify-between gap-4"><dt class="app-muted">Kênh thanh toán</dt><dd class="font-bold app-text">{{ $providerLabel }}</dd></div>
                <div class="mt-3 flex justify-between gap-4 border-t pt-3 app-border"><dt class="font-bold app-text">Số tiền</dt><dd class="font-extrabold text-brand-start">{{ number_format((int) $payment->amount, 0, ',', '.') }} {{ ($payment->currency ?: 'VND') === 'VND' ? 'VNĐ' : $payment->currency }}</dd></div>
            </dl>

            <p class="mx-auto mt-5 max-w-xl text-xs leading-relaxed app-muted">
                Trạng thái trên lấy từ cơ sở dữ liệu MovieMate. Dữ liệu trình duyệt
                {{ $integrityVerified ? 'có checksum hợp lệ' : 'không được xác thực' }}
                và không thể tự đánh dấu giao dịch là đã thanh toán.
            </p>

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING, \App\Models\Payment::STATUS_REVIEW], true))
                <div class="mx-auto mt-5 max-w-xl rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4 text-left text-sm leading-relaxed text-warning" role="note">
                    <strong>Không tạo lại thanh toán khi chưa rõ kết quả.</strong> Nếu {{ $providerLabel }} đã trừ tiền, hãy giữ mã đặt vé và chờ MovieMate xác minh hoặc đối soát giao dịch hiện tại.
                </div>
            @endif

            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                @if($isVerifiedPaid && $canViewTicket)
                    <a href="{{ route('user.bookings.ticket', $booking) }}" data-paid-ticket-link class="btn-primary">
                        <i class="ph-fill ph-ticket" aria-hidden="true"></i>
                        Mở đơn đặt vé
                    </a>
                @elseif($isVerifiedPaid)
                    <p class="app-muted">Liên kết mở đơn đặt vé an toàn được gửi riêng qua email và không được cấp bởi trang quay lại thanh toán.</p>
                @endif
                @if($canViewBooking)
                    <a href="{{ route('user.bookings.success', $booking) }}" class="btn-secondary">Xem chi tiết đơn đặt vé</a>
                @endif
            </div>
        </section>
    </div>
</main>
@endsection
@extends('layouts.user')

@section('title', 'Trạng thái thanh toán - MovieMate')

@section('content')
@php
    $providerLabel = match ($payment->provider) {
        'vnpay' => 'VNPAY',
        'payos' => 'payOS',
        default => 'ZaloPay',
    };
    $isVerifiedPaid = $payment->status === \App\Models\Payment::STATUS_SUCCESS
        && $payment->verified_at !== null
        && $booking->payment_status === 'paid'
        && $booking->booking_status === 'paid';
    $isCinemaCancelled = $booking->booking_status === 'cancelled' && $booking->showtimeCancellationImpact !== null;
    $stateStatus = $payment->status === \App\Models\Payment::STATUS_SUCCESS && ! $isVerifiedPaid
        ? \App\Models\Payment::STATUS_REVIEW
        : $payment->status;
    if ($booking->booking_status === 'pending_payment'
        && $stateStatus === \App\Models\Payment::STATUS_FAILED) {
        $stateStatus = \App\Models\Payment::STATUS_PENDING;
    }
    if ($booking->booking_status === 'expired'
        && $payment->failure_reason === 'vnpay_terminal_expired') {
        $stateStatus = \App\Models\Payment::STATUS_EXPIRED;
    }
    $states = [
        \App\Models\Payment::STATUS_PENDING => [
            'title' => 'Đang xác minh kết quả thanh toán',
            'message' => "MovieMate chưa nhận được kết quả cuối cùng từ {$providerLabel}. Hệ thống sẽ cập nhật đơn khi có kết quả chính thức.",
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_SUCCESS => [
            'title' => 'Đặt vé thành công',
            'message' => 'Thanh toán đã được xác minh. Đơn đặt vé và QR đơn đặt vé đã sẵn sàng để xuất trình tại quầy.',
            'icon' => 'ph-check-circle',
            'colour' => 'text-success',
        ],
        \App\Models\Payment::STATUS_FAILED => [
            'title' => 'Thanh toán không thành công',
            'message' => "{$providerLabel} đã trả về trạng thái không thành công. Đơn đặt vé này chưa đủ điều kiện nhận vé.",
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ],
        \App\Models\Payment::STATUS_REVIEW => [
            'title' => 'Giao dịch cần được hỗ trợ',
            'message' => 'Dữ liệu giao dịch cần bộ phận hỗ trợ kiểm tra thêm trước khi có kết luận chính thức.',
            'icon' => 'ph-magnifying-glass',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_EXPIRED => [
            'title' => 'Lần thanh toán đã hết hạn',
            'message' => 'Thời hạn thanh toán đã kết thúc. Đơn chưa đủ điều kiện phát hành QR đơn đặt vé.',
            'icon' => 'ph-clock-countdown',
            'colour' => 'text-slate-500',
        ],
    ];
    $states[\App\Models\Payment::STATUS_UNRESOLVED] = $states[\App\Models\Payment::STATUS_PENDING];
    $states[\App\Models\Payment::STATUS_PROCESSING] = $states[\App\Models\Payment::STATUS_PENDING];
    $isConfirmedCancellation = $booking->booking_status === 'cancelled'
        && $payment->status === \App\Models\Payment::STATUS_FAILED;
    if ($isConfirmedCancellation) {
        $states[\App\Models\Payment::STATUS_FAILED] = [
            'title' => 'Thanh toán đã được hủy',
            'message' => $payment->provider === 'vnpay' && $payment->failure_reason === 'vnpay_customer_cancelled'
                ? 'Bạn đã hủy giao dịch VNPAY. Các ghế đã giữ cho đơn này đã được giải phóng.'
                : 'Các ghế đã giữ cho đơn này đã được giải phóng.',
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ];
    }
    if (($cancelRequested ?? false) && ! $isConfirmedCancellation
        && in_array($payment->status, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true)) {
        $states[$stateStatus] = [
            'title' => 'Đang xác minh việc hủy thanh toán',
            'message' => 'Ghế của bạn vẫn được giữ tạm thời để tránh mất vé trong khi hệ thống xác minh trạng thái giao dịch.',
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ];
    }
    if ($isCinemaCancelled) {
        $stateStatus = 'showtime_cancelled';
        $states[$stateStatus] = [
            'title' => 'Suất chiếu đã bị rạp hủy',
            'message' => match($booking->refundCase?->status) {
                \App\Models\RefundCase::STATUS_REQUIRED => 'Thanh toán đã được xác minh. Cần xử lý hoàn tiền; đơn vẫn bị hủy và không phát hành QR hoặc vé sử dụng.',
                \App\Models\RefundCase::STATUS_RESOLVED => 'Thanh toán đã được giữ trong lịch sử và rạp đã ghi nhận hoàn tiền.',
                default => 'Bạn chưa có khoản thanh toán cần hoàn.',
            },
            'icon' => 'ph-calendar-x',
            'colour' => 'text-error',
        ];
    }
    $state = $states[$stateStatus] ?? [
        'title' => 'Đang xử lý trạng thái',
        'message' => 'MovieMate đang kiểm tra dữ liệu giao dịch hiện tại.',
        'icon' => 'ph-spinner-gap',
        'colour' => 'text-slate-500',
    ];
    $holdExpiresAt = $booking->expires_at ?? $payment->expires_at;
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

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true) && $holdExpiresAt)
                <div class="mx-auto mt-6 max-w-md rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4" data-countdown-wrapper>
                    <p class="text-xs font-bold uppercase tracking-wide text-warning">Thời gian lần thanh toán còn lại</p>
                    <p class="mt-1 text-3xl font-extrabold app-text" data-countdown="{{ $holdExpiresAt->toIso8601String() }}" data-expired-label="Đã hết thời gian">--:--</p>
                </div>
            @endif

            <dl class="mx-auto mt-7 max-w-xl rounded-2xl app-secondary p-5 text-left text-sm">
                @if($canViewBooking)
                    <div class="flex justify-between gap-4"><dt class="app-muted">Mã đặt vé</dt><dd class="break-all text-right font-mono font-bold text-brand-start">{{ $booking->booking_code }}</dd></div>
                @else
                    <div class="flex justify-between gap-4"><dt class="app-muted">Lần thanh toán</dt><dd class="text-right font-mono font-bold text-brand-start">#{{ $payment->id }}</dd></div>
                @endif
                <div class="mt-3 flex justify-between gap-4"><dt class="app-muted">Kênh thanh toán</dt><dd class="font-bold app-text">{{ $providerLabel }}</dd></div>
                <div class="mt-3 flex justify-between gap-4 border-t pt-3 app-border"><dt class="font-bold app-text">Số tiền</dt><dd class="font-extrabold text-brand-start">{{ number_format((int) $payment->amount, 0, ',', '.') }} {{ ($payment->currency ?: 'VND') === 'VND' ? 'VNĐ' : $payment->currency }}</dd></div>
            </dl>

            <p class="mx-auto mt-5 max-w-xl text-xs leading-relaxed app-muted">
                Trạng thái trên lấy từ cơ sở dữ liệu MovieMate. Dữ liệu trình duyệt
                {{ $integrityVerified ? 'có checksum hợp lệ' : 'không được xác thực' }}
                và không thể tự đánh dấu giao dịch là đã thanh toán.
            </p>

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING, \App\Models\Payment::STATUS_REVIEW], true))
                <div class="mx-auto mt-5 max-w-xl rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4 text-left text-sm leading-relaxed text-warning" role="note">
                    <strong>Không tạo lại thanh toán khi chưa rõ kết quả.</strong> Nếu {{ $providerLabel }} đã trừ tiền, hãy giữ mã đặt vé và chờ MovieMate xác minh hoặc đối soát giao dịch hiện tại.
                </div>
            @endif

            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                @if($isVerifiedPaid && $canViewTicket)
                    <a href="{{ route('user.bookings.ticket', $booking) }}" data-paid-ticket-link class="btn-primary">
                        <i class="ph-fill ph-ticket" aria-hidden="true"></i>
                        Mở đơn đặt vé
                    </a>
                @elseif($isVerifiedPaid)
                    <p class="app-muted">Liên kết mở đơn đặt vé an toàn được gửi riêng qua email và không được cấp bởi trang quay lại thanh toán.</p>
                @endif
                @if($canViewBooking)
                    <a href="{{ route('user.bookings.success', $booking) }}" class="btn-secondary">Xem chi tiết đơn đặt vé</a>
                @endif
            </div>
        </section>
    </div>
</main>
@endsection
@extends('layouts.user')

@section('title', 'Trạng thái thanh toán - MovieMate')

@section('content')
@php
    $providerLabel = match ($payment->provider) {
        'vnpay' => 'VNPAY',
        'payos' => 'payOS',
        default => 'ZaloPay',
    };
    $isVerifiedPaid = $payment->status === \App\Models\Payment::STATUS_SUCCESS
        && $payment->verified_at !== null
        && $booking->payment_status === 'paid'
        && $booking->booking_status === 'paid';
    $isCinemaCancelled = $booking->booking_status === 'cancelled' && $booking->showtimeCancellationImpact !== null;
    $stateStatus = $payment->status === \App\Models\Payment::STATUS_SUCCESS && ! $isVerifiedPaid
        ? \App\Models\Payment::STATUS_REVIEW
        : $payment->status;
    if ($booking->booking_status === 'pending_payment'
        && $stateStatus === \App\Models\Payment::STATUS_FAILED) {
        $stateStatus = \App\Models\Payment::STATUS_PENDING;
    }
    if ($booking->booking_status === 'expired'
        && $payment->failure_reason === 'vnpay_terminal_expired') {
        $stateStatus = \App\Models\Payment::STATUS_EXPIRED;
    }
    $states = [
        \App\Models\Payment::STATUS_PENDING => [
            'title' => 'Đang xác minh kết quả thanh toán',
            'message' => "MovieMate chưa nhận được kết quả cuối cùng từ {$providerLabel}. Hệ thống sẽ cập nhật đơn khi có kết quả chính thức.",
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_SUCCESS => [
            'title' => 'Đặt vé thành công',
            'message' => 'Thanh toán đã được xác minh. Đơn đặt vé và QR đơn đặt vé đã sẵn sàng để xuất trình tại quầy.',
            'icon' => 'ph-check-circle',
            'colour' => 'text-success',
        ],
        \App\Models\Payment::STATUS_FAILED => [
            'title' => 'Thanh toán không thành công',
            'message' => "{$providerLabel} đã trả về trạng thái không thành công. Đơn đặt vé này chưa đủ điều kiện nhận vé.",
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ],
        \App\Models\Payment::STATUS_REVIEW => [
            'title' => 'Giao dịch cần được hỗ trợ',
            'message' => 'Dữ liệu giao dịch cần bộ phận hỗ trợ kiểm tra thêm trước khi có kết luận chính thức.',
            'icon' => 'ph-magnifying-glass',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_EXPIRED => [
            'title' => 'Lần thanh toán đã hết hạn',
            'message' => 'Thời hạn thanh toán đã kết thúc. Đơn chưa đủ điều kiện phát hành QR đơn đặt vé.',
            'icon' => 'ph-clock-countdown',
            'colour' => 'text-slate-500',
        ],
    ];
    $states[\App\Models\Payment::STATUS_UNRESOLVED] = $states[\App\Models\Payment::STATUS_PENDING];
    $states[\App\Models\Payment::STATUS_PROCESSING] = $states[\App\Models\Payment::STATUS_PENDING];
    $isConfirmedCancellation = $booking->booking_status === 'cancelled'
        && $payment->status === \App\Models\Payment::STATUS_FAILED;
    if ($isConfirmedCancellation) {
        $states[\App\Models\Payment::STATUS_FAILED] = [
            'title' => 'Thanh toán đã được hủy',
            'message' => $payment->provider === 'vnpay' && $payment->failure_reason === 'vnpay_customer_cancelled'
                ? 'Bạn đã hủy giao dịch VNPAY. Các ghế đã giữ cho đơn này đã được giải phóng.'
                : 'Các ghế đã giữ cho đơn này đã được giải phóng.',
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ];
    }
    if (($cancelRequested ?? false) && ! $isConfirmedCancellation
        && in_array($payment->status, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true)) {
        $states[$stateStatus] = [
            'title' => 'Đang xác minh việc hủy thanh toán',
            'message' => 'Ghế của bạn vẫn được giữ tạm thời để tránh mất vé trong khi hệ thống xác minh trạng thái giao dịch.',
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ];
    }
    if ($isCinemaCancelled) {
        $stateStatus = 'showtime_cancelled';
        $states[$stateStatus] = [
            'title' => 'Suất chiếu đã bị rạp hủy',
            'message' => match($booking->refundCase?->status) {
                \App\Models\RefundCase::STATUS_REQUIRED => 'Thanh toán đã được xác minh. Cần xử lý hoàn tiền; đơn vẫn bị hủy và không phát hành QR hoặc vé sử dụng.',
                \App\Models\RefundCase::STATUS_RESOLVED => 'Thanh toán đã được giữ trong lịch sử và rạp đã ghi nhận hoàn tiền.',
                default => 'Bạn chưa có khoản thanh toán cần hoàn.',
            },
            'icon' => 'ph-calendar-x',
            'colour' => 'text-error',
        ];
    }
    $state = $states[$stateStatus] ?? [
        'title' => 'Đang xử lý trạng thái',
        'message' => 'MovieMate đang kiểm tra dữ liệu giao dịch hiện tại.',
        'icon' => 'ph-spinner-gap',
        'colour' => 'text-slate-500',
    ];
    $holdExpiresAt = $booking->expires_at ?? $payment->expires_at;
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

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true) && $holdExpiresAt)
                <div class="mx-auto mt-6 max-w-md rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4" data-countdown-wrapper>
                    <p class="text-xs font-bold uppercase tracking-wide text-warning">Thời gian lần thanh toán còn lại</p>
                    <p class="mt-1 text-3xl font-extrabold app-text" data-countdown="{{ $holdExpiresAt->toIso8601String() }}" data-expired-label="Đã hết thời gian">--:--</p>
                </div>
            @endif

            <dl class="mx-auto mt-7 max-w-xl rounded-2xl app-secondary p-5 text-left text-sm">
                @if($canViewBooking)
                    <div class="flex justify-between gap-4"><dt class="app-muted">Mã đặt vé</dt><dd class="break-all text-right font-mono font-bold text-brand-start">{{ $booking->booking_code }}</dd></div>
                @else
                    <div class="flex justify-between gap-4"><dt class="app-muted">Lần thanh toán</dt><dd class="text-right font-mono font-bold text-brand-start">#{{ $payment->id }}</dd></div>
                @endif
                <div class="mt-3 flex justify-between gap-4"><dt class="app-muted">Kênh thanh toán</dt><dd class="font-bold app-text">{{ $providerLabel }}</dd></div>
                <div class="mt-3 flex justify-between gap-4 border-t pt-3 app-border"><dt class="font-bold app-text">Số tiền</dt><dd class="font-extrabold text-brand-start">{{ number_format((int) $payment->amount, 0, ',', '.') }} {{ ($payment->currency ?: 'VND') === 'VND' ? 'VNĐ' : $payment->currency }}</dd></div>
            </dl>

            <p class="mx-auto mt-5 max-w-xl text-xs leading-relaxed app-muted">
                Trạng thái trên lấy từ cơ sở dữ liệu MovieMate. Dữ liệu trình duyệt
                {{ $integrityVerified ? 'có checksum hợp lệ' : 'không được xác thực' }}
                và không thể tự đánh dấu giao dịch là đã thanh toán.
            </p>

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING, \App\Models\Payment::STATUS_REVIEW], true))
                <div class="mx-auto mt-5 max-w-xl rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4 text-left text-sm leading-relaxed text-warning" role="note">
                    <strong>Không tạo lại thanh toán khi chưa rõ kết quả.</strong> Nếu {{ $providerLabel }} đã trừ tiền, hãy giữ mã đặt vé và chờ MovieMate xác minh hoặc đối soát giao dịch hiện tại.
                </div>
            @endif

            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                @if($isVerifiedPaid && $canViewTicket)
                    <a href="{{ route('user.bookings.ticket', $booking) }}" data-paid-ticket-link class="btn-primary">
                        <i class="ph-fill ph-ticket" aria-hidden="true"></i>
                        Mở đơn đặt vé
                    </a>
                @elseif($isVerifiedPaid)
                    <p class="app-muted">Liên kết mở đơn đặt vé an toàn được gửi riêng qua email và không được cấp bởi trang quay lại thanh toán.</p>
                @endif
                @if($canViewBooking)
                    <a href="{{ route('user.bookings.success', $booking) }}" class="btn-secondary">Xem chi tiết đơn đặt vé</a>
                @endif
            </div>
        </section>
    </div>
</main>
@endsection
@extends('layouts.user')

@section('title', 'Trạng thái thanh toán - MovieMate')

@section('content')
@php
    $providerLabel = match ($payment->provider) {
        'vnpay' => 'VNPAY',
        'payos' => 'payOS',
        default => 'ZaloPay',
    };
    $isVerifiedPaid = $payment->status === \App\Models\Payment::STATUS_SUCCESS
        && $payment->verified_at !== null
        && $booking->payment_status === 'paid'
        && $booking->booking_status === 'paid';
    $isCinemaCancelled = $booking->booking_status === 'cancelled' && $booking->showtimeCancellationImpact !== null;
    $stateStatus = $payment->status === \App\Models\Payment::STATUS_SUCCESS && ! $isVerifiedPaid
        ? \App\Models\Payment::STATUS_REVIEW
        : $payment->status;
    if ($booking->booking_status === 'pending_payment'
        && $stateStatus === \App\Models\Payment::STATUS_FAILED) {
        $stateStatus = \App\Models\Payment::STATUS_PENDING;
    }
    if ($booking->booking_status === 'expired'
        && $payment->failure_reason === 'vnpay_terminal_expired') {
        $stateStatus = \App\Models\Payment::STATUS_EXPIRED;
    }
    $states = [
        \App\Models\Payment::STATUS_PENDING => [
            'title' => 'Đang xác minh kết quả thanh toán',
            'message' => "MovieMate chưa nhận được kết quả cuối cùng từ {$providerLabel}. Hệ thống sẽ cập nhật đơn khi có kết quả chính thức.",
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_SUCCESS => [
            'title' => 'Đặt vé thành công',
            'message' => 'Thanh toán đã được xác minh. Đơn đặt vé và QR đơn đặt vé đã sẵn sàng để xuất trình tại quầy.',
            'icon' => 'ph-check-circle',
            'colour' => 'text-success',
        ],
        \App\Models\Payment::STATUS_FAILED => [
            'title' => 'Thanh toán không thành công',
            'message' => "{$providerLabel} đã trả về trạng thái không thành công. Đơn đặt vé này chưa đủ điều kiện nhận vé.",
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ],
        \App\Models\Payment::STATUS_REVIEW => [
            'title' => 'Giao dịch cần được hỗ trợ',
            'message' => 'Dữ liệu giao dịch cần bộ phận hỗ trợ kiểm tra thêm trước khi có kết luận chính thức.',
            'icon' => 'ph-magnifying-glass',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_EXPIRED => [
            'title' => 'Lần thanh toán đã hết hạn',
            'message' => 'Thời hạn thanh toán đã kết thúc. Đơn chưa đủ điều kiện phát hành QR đơn đặt vé.',
            'icon' => 'ph-clock-countdown',
            'colour' => 'text-slate-500',
        ],
    ];
    $states[\App\Models\Payment::STATUS_UNRESOLVED] = $states[\App\Models\Payment::STATUS_PENDING];
    $states[\App\Models\Payment::STATUS_PROCESSING] = $states[\App\Models\Payment::STATUS_PENDING];
    $isConfirmedCancellation = $booking->booking_status === 'cancelled'
        && $payment->status === \App\Models\Payment::STATUS_FAILED;
    if ($isConfirmedCancellation) {
        $states[\App\Models\Payment::STATUS_FAILED] = [
            'title' => 'Thanh toán đã được hủy',
            'message' => $payment->provider === 'vnpay' && $payment->failure_reason === 'vnpay_customer_cancelled'
                ? 'Bạn đã hủy giao dịch VNPAY. Các ghế đã giữ cho đơn này đã được giải phóng.'
                : 'Các ghế đã giữ cho đơn này đã được giải phóng.',
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ];
    }
    if (($cancelRequested ?? false) && ! $isConfirmedCancellation
        && in_array($payment->status, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true)) {
        $states[$stateStatus] = [
            'title' => 'Đang xác minh việc hủy thanh toán',
            'message' => 'Ghế của bạn vẫn được giữ tạm thời để tránh mất vé trong khi hệ thống xác minh trạng thái giao dịch.',
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ];
    }
    if ($isCinemaCancelled) {
        $stateStatus = 'showtime_cancelled';
        $states[$stateStatus] = [
            'title' => 'Suất chiếu đã bị rạp hủy',
            'message' => match($booking->refundCase?->status) {
                \App\Models\RefundCase::STATUS_REQUIRED => 'Thanh toán đã được xác minh. Cần xử lý hoàn tiền; đơn vẫn bị hủy và không phát hành QR hoặc vé sử dụng.',
                \App\Models\RefundCase::STATUS_RESOLVED => 'Thanh toán đã được giữ trong lịch sử và rạp đã ghi nhận hoàn tiền.',
                default => 'Bạn chưa có khoản thanh toán cần hoàn.',
            },
            'icon' => 'ph-calendar-x',
            'colour' => 'text-error',
        ];
    }
    $state = $states[$stateStatus] ?? [
        'title' => 'Đang xử lý trạng thái',
        'message' => 'MovieMate đang kiểm tra dữ liệu giao dịch hiện tại.',
        'icon' => 'ph-spinner-gap',
        'colour' => 'text-slate-500',
    ];
    $holdExpiresAt = $booking->expires_at ?? $payment->expires_at;
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

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true) && $holdExpiresAt)
                <div class="mx-auto mt-6 max-w-md rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4" data-countdown-wrapper>
                    <p class="text-xs font-bold uppercase tracking-wide text-warning">Thời gian lần thanh toán còn lại</p>
                    <p class="mt-1 text-3xl font-extrabold app-text" data-countdown="{{ $holdExpiresAt->toIso8601String() }}" data-expired-label="Đã hết thời gian">--:--</p>
                </div>
            @endif

            <dl class="mx-auto mt-7 max-w-xl rounded-2xl app-secondary p-5 text-left text-sm">
                @if($canViewBooking)
                    <div class="flex justify-between gap-4"><dt class="app-muted">Mã đặt vé</dt><dd class="break-all text-right font-mono font-bold text-brand-start">{{ $booking->booking_code }}</dd></div>
                @else
                    <div class="flex justify-between gap-4"><dt class="app-muted">Lần thanh toán</dt><dd class="text-right font-mono font-bold text-brand-start">#{{ $payment->id }}</dd></div>
                @endif
                <div class="mt-3 flex justify-between gap-4"><dt class="app-muted">Kênh thanh toán</dt><dd class="font-bold app-text">{{ $providerLabel }}</dd></div>
                <div class="mt-3 flex justify-between gap-4 border-t pt-3 app-border"><dt class="font-bold app-text">Số tiền</dt><dd class="font-extrabold text-brand-start">{{ number_format((int) $payment->amount, 0, ',', '.') }} {{ ($payment->currency ?: 'VND') === 'VND' ? 'VNĐ' : $payment->currency }}</dd></div>
            </dl>

            <p class="mx-auto mt-5 max-w-xl text-xs leading-relaxed app-muted">
                Trạng thái trên lấy từ cơ sở dữ liệu MovieMate. Dữ liệu trình duyệt
                {{ $integrityVerified ? 'có checksum hợp lệ' : 'không được xác thực' }}
                và không thể tự đánh dấu giao dịch là đã thanh toán.
            </p>

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING, \App\Models\Payment::STATUS_REVIEW], true))
                <div class="mx-auto mt-5 max-w-xl rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4 text-left text-sm leading-relaxed text-warning" role="note">
                    <strong>Không tạo lại thanh toán khi chưa rõ kết quả.</strong> Nếu {{ $providerLabel }} đã trừ tiền, hãy giữ mã đặt vé và chờ MovieMate xác minh hoặc đối soát giao dịch hiện tại.
                </div>
            @endif

            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                @if($isVerifiedPaid && $canViewTicket)
                    <a href="{{ route('user.bookings.ticket', $booking) }}" data-paid-ticket-link class="btn-primary">
                        <i class="ph-fill ph-ticket" aria-hidden="true"></i>
                        Mở đơn đặt vé
                    </a>
                @elseif($isVerifiedPaid)
                    <p class="app-muted">Liên kết mở đơn đặt vé an toàn được gửi riêng qua email và không được cấp bởi trang quay lại thanh toán.</p>
                @endif
                @if($canViewBooking)
                    <a href="{{ route('user.bookings.success', $booking) }}" class="btn-secondary">Xem chi tiết đơn đặt vé</a>
                @endif
            </div>
        </section>
    </div>
</main>
@endsection
@extends('layouts.user')

@section('title', 'Trạng thái thanh toán - MovieMate')

@section('content')
@php
    $providerLabel = match ($payment->provider) {
        'vnpay' => 'VNPAY',
        'payos' => 'payOS',
        default => 'ZaloPay',
    };
    $isVerifiedPaid = $payment->status === \App\Models\Payment::STATUS_SUCCESS
        && $payment->verified_at !== null
        && $booking->payment_status === 'paid'
        && $booking->booking_status === 'paid';
    $isCinemaCancelled = $booking->booking_status === 'cancelled' && $booking->showtimeCancellationImpact !== null;
    $stateStatus = $payment->status === \App\Models\Payment::STATUS_SUCCESS && ! $isVerifiedPaid
        ? \App\Models\Payment::STATUS_REVIEW
        : $payment->status;
    if ($booking->booking_status === 'pending_payment'
        && $stateStatus === \App\Models\Payment::STATUS_FAILED) {
        $stateStatus = \App\Models\Payment::STATUS_PENDING;
    }
    if ($booking->booking_status === 'expired'
        && $payment->failure_reason === 'vnpay_terminal_expired') {
        $stateStatus = \App\Models\Payment::STATUS_EXPIRED;
    }
    $states = [
        \App\Models\Payment::STATUS_PENDING => [
            'title' => 'Đang xác minh kết quả thanh toán',
            'message' => "MovieMate chưa nhận được kết quả cuối cùng từ {$providerLabel}. Hệ thống sẽ cập nhật đơn khi có kết quả chính thức.",
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_SUCCESS => [
            'title' => 'Đặt vé thành công',
            'message' => 'Thanh toán đã được xác minh. Đơn đặt vé và QR đơn đặt vé đã sẵn sàng để xuất trình tại quầy.',
            'icon' => 'ph-check-circle',
            'colour' => 'text-success',
        ],
        \App\Models\Payment::STATUS_FAILED => [
            'title' => 'Thanh toán không thành công',
            'message' => "{$providerLabel} đã trả về trạng thái không thành công. Đơn đặt vé này chưa đủ điều kiện nhận vé.",
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ],
        \App\Models\Payment::STATUS_REVIEW => [
            'title' => 'Giao dịch cần được hỗ trợ',
            'message' => 'Dữ liệu giao dịch cần bộ phận hỗ trợ kiểm tra thêm trước khi có kết luận chính thức.',
            'icon' => 'ph-magnifying-glass',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_EXPIRED => [
            'title' => 'Lần thanh toán đã hết hạn',
            'message' => 'Thời hạn thanh toán đã kết thúc. Đơn chưa đủ điều kiện phát hành QR đơn đặt vé.',
            'icon' => 'ph-clock-countdown',
            'colour' => 'text-slate-500',
        ],
    ];
    $states[\App\Models\Payment::STATUS_UNRESOLVED] = $states[\App\Models\Payment::STATUS_PENDING];
    $states[\App\Models\Payment::STATUS_PROCESSING] = $states[\App\Models\Payment::STATUS_PENDING];
    $isConfirmedCancellation = $booking->booking_status === 'cancelled'
        && $payment->status === \App\Models\Payment::STATUS_FAILED;
    if ($isConfirmedCancellation) {
        $states[\App\Models\Payment::STATUS_FAILED] = [
            'title' => 'Thanh toán đã được hủy',
            'message' => $payment->provider === 'vnpay' && $payment->failure_reason === 'vnpay_customer_cancelled'
                ? 'Bạn đã hủy giao dịch VNPAY. Các ghế đã giữ cho đơn này đã được giải phóng.'
                : 'Các ghế đã giữ cho đơn này đã được giải phóng.',
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ];
    }
    if (($cancelRequested ?? false) && ! $isConfirmedCancellation
        && in_array($payment->status, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true)) {
        $states[$stateStatus] = [
            'title' => 'Đang xác minh việc hủy thanh toán',
            'message' => 'Ghế của bạn vẫn được giữ tạm thời để tránh mất vé trong khi hệ thống xác minh trạng thái giao dịch.',
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ];
    }
    if ($isCinemaCancelled) {
        $stateStatus = 'showtime_cancelled';
        $states[$stateStatus] = [
            'title' => 'Suất chiếu đã bị rạp hủy',
            'message' => match($booking->refundCase?->status) {
                \App\Models\RefundCase::STATUS_REQUIRED => 'Thanh toán đã được xác minh. Cần xử lý hoàn tiền; đơn vẫn bị hủy và không phát hành QR hoặc vé sử dụng.',
                \App\Models\RefundCase::STATUS_RESOLVED => 'Thanh toán đã được giữ trong lịch sử và rạp đã ghi nhận hoàn tiền.',
                default => 'Bạn chưa có khoản thanh toán cần hoàn.',
            },
            'icon' => 'ph-calendar-x',
            'colour' => 'text-error',
        ];
    }
    $state = $states[$stateStatus] ?? [
        'title' => 'Đang xử lý trạng thái',
        'message' => 'MovieMate đang kiểm tra dữ liệu giao dịch hiện tại.',
        'icon' => 'ph-spinner-gap',
        'colour' => 'text-slate-500',
    ];
    $holdExpiresAt = $booking->expires_at ?? $payment->expires_at;
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

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true) && $holdExpiresAt)
                <div class="mx-auto mt-6 max-w-md rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4" data-countdown-wrapper>
                    <p class="text-xs font-bold uppercase tracking-wide text-warning">Thời gian lần thanh toán còn lại</p>
                    <p class="mt-1 text-3xl font-extrabold app-text" data-countdown="{{ $holdExpiresAt->toIso8601String() }}" data-expired-label="Đã hết thời gian">--:--</p>
                </div>
            @endif

            <dl class="mx-auto mt-7 max-w-xl rounded-2xl app-secondary p-5 text-left text-sm">
                @if($canViewBooking)
                    <div class="flex justify-between gap-4"><dt class="app-muted">Mã đặt vé</dt><dd class="break-all text-right font-mono font-bold text-brand-start">{{ $booking->booking_code }}</dd></div>
                @else
                    <div class="flex justify-between gap-4"><dt class="app-muted">Lần thanh toán</dt><dd class="text-right font-mono font-bold text-brand-start">#{{ $payment->id }}</dd></div>
                @endif
                <div class="mt-3 flex justify-between gap-4"><dt class="app-muted">Kênh thanh toán</dt><dd class="font-bold app-text">{{ $providerLabel }}</dd></div>
                <div class="mt-3 flex justify-between gap-4 border-t pt-3 app-border"><dt class="font-bold app-text">Số tiền</dt><dd class="font-extrabold text-brand-start">{{ number_format((int) $payment->amount, 0, ',', '.') }} {{ ($payment->currency ?: 'VND') === 'VND' ? 'VNĐ' : $payment->currency }}</dd></div>
            </dl>

            <p class="mx-auto mt-5 max-w-xl text-xs leading-relaxed app-muted">
                Trạng thái trên lấy từ cơ sở dữ liệu MovieMate. Dữ liệu trình duyệt
                {{ $integrityVerified ? 'có checksum hợp lệ' : 'không được xác thực' }}
                và không thể tự đánh dấu giao dịch là đã thanh toán.
            </p>

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING, \App\Models\Payment::STATUS_REVIEW], true))
                <div class="mx-auto mt-5 max-w-xl rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4 text-left text-sm leading-relaxed text-warning" role="note">
                    <strong>Không tạo lại thanh toán khi chưa rõ kết quả.</strong> Nếu {{ $providerLabel }} đã trừ tiền, hãy giữ mã đặt vé và chờ MovieMate xác minh hoặc đối soát giao dịch hiện tại.
                </div>
            @endif

            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                @if($isVerifiedPaid && $canViewTicket)
                    <a href="{{ route('user.bookings.ticket', $booking) }}" data-paid-ticket-link class="btn-primary">
                        <i class="ph-fill ph-ticket" aria-hidden="true"></i>
                        Mở đơn đặt vé
                    </a>
                @elseif($isVerifiedPaid)
                    <p class="app-muted">Liên kết mở đơn đặt vé an toàn được gửi riêng qua email và không được cấp bởi trang quay lại thanh toán.</p>
                @endif
                @if($canViewBooking)
                    <a href="{{ route('user.bookings.success', $booking) }}" class="btn-secondary">Xem chi tiết đơn đặt vé</a>
                @endif
            </div>
        </section>
    </div>
</main>
@endsection
@extends('layouts.user')

@section('title', 'Trạng thái thanh toán - MovieMate')

@section('content')
@php
    $providerLabel = match ($payment->provider) {
        'vnpay' => 'VNPAY',
        'payos' => 'payOS',
        default => 'ZaloPay',
    };
    $isVerifiedPaid = $payment->status === \App\Models\Payment::STATUS_SUCCESS
        && $payment->verified_at !== null
        && $booking->payment_status === 'paid'
        && $booking->booking_status === 'paid';
    $isCinemaCancelled = $booking->booking_status === 'cancelled' && $booking->showtimeCancellationImpact !== null;
    $stateStatus = $payment->status === \App\Models\Payment::STATUS_SUCCESS && ! $isVerifiedPaid
        ? \App\Models\Payment::STATUS_REVIEW
        : $payment->status;
    if ($booking->booking_status === 'pending_payment'
        && $stateStatus === \App\Models\Payment::STATUS_FAILED) {
        $stateStatus = \App\Models\Payment::STATUS_PENDING;
    }
    if ($booking->booking_status === 'expired'
        && $payment->failure_reason === 'vnpay_terminal_expired') {
        $stateStatus = \App\Models\Payment::STATUS_EXPIRED;
    }
    $states = [
        \App\Models\Payment::STATUS_PENDING => [
            'title' => 'Đang xác minh kết quả thanh toán',
            'message' => "MovieMate chưa nhận được kết quả cuối cùng từ {$providerLabel}. Hệ thống sẽ cập nhật đơn khi có kết quả chính thức.",
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_SUCCESS => [
            'title' => 'Đặt vé thành công',
            'message' => 'Thanh toán đã được xác minh. Đơn đặt vé và QR đơn đặt vé đã sẵn sàng để xuất trình tại quầy.',
            'icon' => 'ph-check-circle',
            'colour' => 'text-success',
        ],
        \App\Models\Payment::STATUS_FAILED => [
            'title' => 'Thanh toán không thành công',
            'message' => "{$providerLabel} đã trả về trạng thái không thành công. Đơn đặt vé này chưa đủ điều kiện nhận vé.",
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ],
        \App\Models\Payment::STATUS_REVIEW => [
            'title' => 'Giao dịch cần được hỗ trợ',
            'message' => 'Dữ liệu giao dịch cần bộ phận hỗ trợ kiểm tra thêm trước khi có kết luận chính thức.',
            'icon' => 'ph-magnifying-glass',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_EXPIRED => [
            'title' => 'Lần thanh toán đã hết hạn',
            'message' => 'Thời hạn thanh toán đã kết thúc. Đơn chưa đủ điều kiện phát hành QR đơn đặt vé.',
            'icon' => 'ph-clock-countdown',
            'colour' => 'text-slate-500',
        ],
    ];
    $states[\App\Models\Payment::STATUS_UNRESOLVED] = $states[\App\Models\Payment::STATUS_PENDING];
    $states[\App\Models\Payment::STATUS_PROCESSING] = $states[\App\Models\Payment::STATUS_PENDING];
    $isConfirmedCancellation = $booking->booking_status === 'cancelled'
        && $payment->status === \App\Models\Payment::STATUS_FAILED;
    if ($isConfirmedCancellation) {
        $states[\App\Models\Payment::STATUS_FAILED] = [
            'title' => 'Thanh toán đã được hủy',
            'message' => $payment->provider === 'vnpay' && $payment->failure_reason === 'vnpay_customer_cancelled'
                ? 'Bạn đã hủy giao dịch VNPAY. Các ghế đã giữ cho đơn này đã được giải phóng.'
                : 'Các ghế đã giữ cho đơn này đã được giải phóng.',
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ];
    }
    if (($cancelRequested ?? false) && ! $isConfirmedCancellation
        && in_array($payment->status, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true)) {
        $states[$stateStatus] = [
            'title' => 'Đang xác minh việc hủy thanh toán',
            'message' => 'Ghế của bạn vẫn được giữ tạm thời để tránh mất vé trong khi hệ thống xác minh trạng thái giao dịch.',
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ];
    }
    if ($isCinemaCancelled) {
        $stateStatus = 'showtime_cancelled';
        $states[$stateStatus] = [
            'title' => 'Suất chiếu đã bị rạp hủy',
            'message' => match($booking->refundCase?->status) {
                \App\Models\RefundCase::STATUS_REQUIRED => 'Thanh toán đã được xác minh. Cần xử lý hoàn tiền; đơn vẫn bị hủy và không phát hành QR hoặc vé sử dụng.',
                \App\Models\RefundCase::STATUS_RESOLVED => 'Thanh toán đã được giữ trong lịch sử và rạp đã ghi nhận hoàn tiền.',
                default => 'Bạn chưa có khoản thanh toán cần hoàn.',
            },
            'icon' => 'ph-calendar-x',
            'colour' => 'text-error',
        ];
    }
    $state = $states[$stateStatus] ?? [
        'title' => 'Đang xử lý trạng thái',
        'message' => 'MovieMate đang kiểm tra dữ liệu giao dịch hiện tại.',
        'icon' => 'ph-spinner-gap',
        'colour' => 'text-slate-500',
    ];
    $holdExpiresAt = $booking->expires_at ?? $payment->expires_at;
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

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true) && $holdExpiresAt)
                <div class="mx-auto mt-6 max-w-md rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4" data-countdown-wrapper>
                    <p class="text-xs font-bold uppercase tracking-wide text-warning">Thời gian lần thanh toán còn lại</p>
                    <p class="mt-1 text-3xl font-extrabold app-text" data-countdown="{{ $holdExpiresAt->toIso8601String() }}" data-expired-label="Đã hết thời gian">--:--</p>
                </div>
            @endif

            <dl class="mx-auto mt-7 max-w-xl rounded-2xl app-secondary p-5 text-left text-sm">
                @if($canViewBooking)
                    <div class="flex justify-between gap-4"><dt class="app-muted">Mã đặt vé</dt><dd class="break-all text-right font-mono font-bold text-brand-start">{{ $booking->booking_code }}</dd></div>
                @else
                    <div class="flex justify-between gap-4"><dt class="app-muted">Lần thanh toán</dt><dd class="text-right font-mono font-bold text-brand-start">#{{ $payment->id }}</dd></div>
                @endif
                <div class="mt-3 flex justify-between gap-4"><dt class="app-muted">Kênh thanh toán</dt><dd class="font-bold app-text">{{ $providerLabel }}</dd></div>
                <div class="mt-3 flex justify-between gap-4 border-t pt-3 app-border"><dt class="font-bold app-text">Số tiền</dt><dd class="font-extrabold text-brand-start">{{ number_format((int) $payment->amount, 0, ',', '.') }} {{ ($payment->currency ?: 'VND') === 'VND' ? 'VNĐ' : $payment->currency }}</dd></div>
            </dl>

            <p class="mx-auto mt-5 max-w-xl text-xs leading-relaxed app-muted">
                Trạng thái trên lấy từ cơ sở dữ liệu MovieMate. Dữ liệu trình duyệt
                {{ $integrityVerified ? 'có checksum hợp lệ' : 'không được xác thực' }}
                và không thể tự đánh dấu giao dịch là đã thanh toán.
            </p>

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING, \App\Models\Payment::STATUS_REVIEW], true))
                <div class="mx-auto mt-5 max-w-xl rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4 text-left text-sm leading-relaxed text-warning" role="note">
                    <strong>Không tạo lại thanh toán khi chưa rõ kết quả.</strong> Nếu {{ $providerLabel }} đã trừ tiền, hãy giữ mã đặt vé và chờ MovieMate xác minh hoặc đối soát giao dịch hiện tại.
                </div>
            @endif

            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                @if($isVerifiedPaid && $canViewTicket)
                    <a href="{{ route('user.bookings.ticket', $booking) }}" data-paid-ticket-link class="btn-primary">
                        <i class="ph-fill ph-ticket" aria-hidden="true"></i>
                        Mở đơn đặt vé
                    </a>
                @elseif($isVerifiedPaid)
                    <p class="app-muted">Liên kết mở đơn đặt vé an toàn được gửi riêng qua email và không được cấp bởi trang quay lại thanh toán.</p>
                @endif
                @if($canViewBooking)
                    <a href="{{ route('user.bookings.success', $booking) }}" class="btn-secondary">Xem chi tiết đơn đặt vé</a>
                @endif
            </div>
        </section>
    </div>
</main>
@endsection
@extends('layouts.user')

@section('title', 'Trạng thái thanh toán - MovieMate')

@section('content')
@php
    $providerLabel = match ($payment->provider) {
        'vnpay' => 'VNPAY',
        'payos' => 'payOS',
        default => 'ZaloPay',
    };
    $isVerifiedPaid = $payment->status === \App\Models\Payment::STATUS_SUCCESS
        && $payment->verified_at !== null
        && $booking->payment_status === 'paid'
        && $booking->booking_status === 'paid';
    $isCinemaCancelled = $booking->booking_status === 'cancelled' && $booking->showtimeCancellationImpact !== null;
    $stateStatus = $payment->status === \App\Models\Payment::STATUS_SUCCESS && ! $isVerifiedPaid
        ? \App\Models\Payment::STATUS_REVIEW
        : $payment->status;
    if ($booking->booking_status === 'pending_payment'
        && $stateStatus === \App\Models\Payment::STATUS_FAILED) {
        $stateStatus = \App\Models\Payment::STATUS_PENDING;
    }
    if ($booking->booking_status === 'expired'
        && $payment->failure_reason === 'vnpay_terminal_expired') {
        $stateStatus = \App\Models\Payment::STATUS_EXPIRED;
    }
    $states = [
        \App\Models\Payment::STATUS_PENDING => [
            'title' => 'Đang xác minh kết quả thanh toán',
            'message' => "MovieMate chưa nhận được kết quả cuối cùng từ {$providerLabel}. Hệ thống sẽ cập nhật đơn khi có kết quả chính thức.",
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_SUCCESS => [
            'title' => 'Đặt vé thành công',
            'message' => 'Thanh toán đã được xác minh. Đơn đặt vé và QR đơn đặt vé đã sẵn sàng để xuất trình tại quầy.',
            'icon' => 'ph-check-circle',
            'colour' => 'text-success',
        ],
        \App\Models\Payment::STATUS_FAILED => [
            'title' => 'Thanh toán không thành công',
            'message' => "{$providerLabel} đã trả về trạng thái không thành công. Đơn đặt vé này chưa đủ điều kiện nhận vé.",
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ],
        \App\Models\Payment::STATUS_REVIEW => [
            'title' => 'Giao dịch cần được hỗ trợ',
            'message' => 'Dữ liệu giao dịch cần bộ phận hỗ trợ kiểm tra thêm trước khi có kết luận chính thức.',
            'icon' => 'ph-magnifying-glass',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_EXPIRED => [
            'title' => 'Lần thanh toán đã hết hạn',
            'message' => 'Thời hạn thanh toán đã kết thúc. Đơn chưa đủ điều kiện phát hành QR đơn đặt vé.',
            'icon' => 'ph-clock-countdown',
            'colour' => 'text-slate-500',
        ],
    ];
    $states[\App\Models\Payment::STATUS_UNRESOLVED] = $states[\App\Models\Payment::STATUS_PENDING];
    $states[\App\Models\Payment::STATUS_PROCESSING] = $states[\App\Models\Payment::STATUS_PENDING];
    $isConfirmedCancellation = $booking->booking_status === 'cancelled'
        && $payment->status === \App\Models\Payment::STATUS_FAILED;
    if ($isConfirmedCancellation) {
        $states[\App\Models\Payment::STATUS_FAILED] = [
            'title' => 'Thanh toán đã được hủy',
            'message' => $payment->provider === 'vnpay' && $payment->failure_reason === 'vnpay_customer_cancelled'
                ? 'Bạn đã hủy giao dịch VNPAY. Các ghế đã giữ cho đơn này đã được giải phóng.'
                : 'Các ghế đã giữ cho đơn này đã được giải phóng.',
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ];
    }
    if (($cancelRequested ?? false) && ! $isConfirmedCancellation
        && in_array($payment->status, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true)) {
        $states[$stateStatus] = [
            'title' => 'Đang xác minh việc hủy thanh toán',
            'message' => 'Ghế của bạn vẫn được giữ tạm thời để tránh mất vé trong khi hệ thống xác minh trạng thái giao dịch.',
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ];
    }
    if ($isCinemaCancelled) {
        $stateStatus = 'showtime_cancelled';
        $states[$stateStatus] = [
            'title' => 'Suất chiếu đã bị rạp hủy',
            'message' => match($booking->refundCase?->status) {
                \App\Models\RefundCase::STATUS_REQUIRED => 'Thanh toán đã được xác minh. Cần xử lý hoàn tiền; đơn vẫn bị hủy và không phát hành QR hoặc vé sử dụng.',
                \App\Models\RefundCase::STATUS_RESOLVED => 'Thanh toán đã được giữ trong lịch sử và rạp đã ghi nhận hoàn tiền.',
                default => 'Bạn chưa có khoản thanh toán cần hoàn.',
            },
            'icon' => 'ph-calendar-x',
            'colour' => 'text-error',
        ];
    }
    $state = $states[$stateStatus] ?? [
        'title' => 'Đang xử lý trạng thái',
        'message' => 'MovieMate đang kiểm tra dữ liệu giao dịch hiện tại.',
        'icon' => 'ph-spinner-gap',
        'colour' => 'text-slate-500',
    ];
    $holdExpiresAt = $booking->expires_at ?? $payment->expires_at;
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

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true) && $holdExpiresAt)
                <div class="mx-auto mt-6 max-w-md rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4" data-countdown-wrapper>
                    <p class="text-xs font-bold uppercase tracking-wide text-warning">Thời gian lần thanh toán còn lại</p>
                    <p class="mt-1 text-3xl font-extrabold app-text" data-countdown="{{ $holdExpiresAt->toIso8601String() }}" data-expired-label="Đã hết thời gian">--:--</p>
                </div>
            @endif

            <dl class="mx-auto mt-7 max-w-xl rounded-2xl app-secondary p-5 text-left text-sm">
                @if($canViewBooking)
                    <div class="flex justify-between gap-4"><dt class="app-muted">Mã đặt vé</dt><dd class="break-all text-right font-mono font-bold text-brand-start">{{ $booking->booking_code }}</dd></div>
                @else
                    <div class="flex justify-between gap-4"><dt class="app-muted">Lần thanh toán</dt><dd class="text-right font-mono font-bold text-brand-start">#{{ $payment->id }}</dd></div>
                @endif
                <div class="mt-3 flex justify-between gap-4"><dt class="app-muted">Kênh thanh toán</dt><dd class="font-bold app-text">{{ $providerLabel }}</dd></div>
                <div class="mt-3 flex justify-between gap-4 border-t pt-3 app-border"><dt class="font-bold app-text">Số tiền</dt><dd class="font-extrabold text-brand-start">{{ number_format((int) $payment->amount, 0, ',', '.') }} {{ ($payment->currency ?: 'VND') === 'VND' ? 'VNĐ' : $payment->currency }}</dd></div>
            </dl>

            <p class="mx-auto mt-5 max-w-xl text-xs leading-relaxed app-muted">
                Trạng thái trên lấy từ cơ sở dữ liệu MovieMate. Dữ liệu trình duyệt
                {{ $integrityVerified ? 'có checksum hợp lệ' : 'không được xác thực' }}
                và không thể tự đánh dấu giao dịch là đã thanh toán.
            </p>

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING, \App\Models\Payment::STATUS_REVIEW], true))
                <div class="mx-auto mt-5 max-w-xl rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4 text-left text-sm leading-relaxed text-warning" role="note">
                    <strong>Không tạo lại thanh toán khi chưa rõ kết quả.</strong> Nếu {{ $providerLabel }} đã trừ tiền, hãy giữ mã đặt vé và chờ MovieMate xác minh hoặc đối soát giao dịch hiện tại.
                </div>
            @endif

            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                @if($isVerifiedPaid && $canViewTicket)
                    <a href="{{ route('user.bookings.ticket', $booking) }}" data-paid-ticket-link class="btn-primary">
                        <i class="ph-fill ph-ticket" aria-hidden="true"></i>
                        Mở đơn đặt vé
                    </a>
                @elseif($isVerifiedPaid)
                    <p class="app-muted">Liên kết mở đơn đặt vé an toàn được gửi riêng qua email và không được cấp bởi trang quay lại thanh toán.</p>
                @endif
                @if($canViewBooking)
                    <a href="{{ route('user.bookings.success', $booking) }}" class="btn-secondary">Xem chi tiết đơn đặt vé</a>
                @endif
            </div>
        </section>
    </div>
</main>
@endsection
@extends('layouts.user')

@section('title', 'Trạng thái thanh toán - MovieMate')

@section('content')
@php
    $providerLabel = match ($payment->provider) {
        'vnpay' => 'VNPAY',
        'payos' => 'payOS',
        default => 'ZaloPay',
    };
    $isVerifiedPaid = $payment->status === \App\Models\Payment::STATUS_SUCCESS
        && $payment->verified_at !== null
        && $booking->payment_status === 'paid'
        && $booking->booking_status === 'paid';
    $isCinemaCancelled = $booking->booking_status === 'cancelled' && $booking->showtimeCancellationImpact !== null;
    $stateStatus = $payment->status === \App\Models\Payment::STATUS_SUCCESS && ! $isVerifiedPaid
        ? \App\Models\Payment::STATUS_REVIEW
        : $payment->status;
    if ($booking->booking_status === 'pending_payment'
        && $stateStatus === \App\Models\Payment::STATUS_FAILED) {
        $stateStatus = \App\Models\Payment::STATUS_PENDING;
    }
    if ($booking->booking_status === 'expired'
        && $payment->failure_reason === 'vnpay_terminal_expired') {
        $stateStatus = \App\Models\Payment::STATUS_EXPIRED;
    }
    $states = [
        \App\Models\Payment::STATUS_PENDING => [
            'title' => 'Đang xác minh kết quả thanh toán',
            'message' => "MovieMate chưa nhận được kết quả cuối cùng từ {$providerLabel}. Hệ thống sẽ cập nhật đơn khi có kết quả chính thức.",
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_SUCCESS => [
            'title' => 'Đặt vé thành công',
            'message' => 'Thanh toán đã được xác minh. Đơn đặt vé và QR đơn đặt vé đã sẵn sàng để xuất trình tại quầy.',
            'icon' => 'ph-check-circle',
            'colour' => 'text-success',
        ],
        \App\Models\Payment::STATUS_FAILED => [
            'title' => 'Thanh toán không thành công',
            'message' => "{$providerLabel} đã trả về trạng thái không thành công. Đơn đặt vé này chưa đủ điều kiện nhận vé.",
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ],
        \App\Models\Payment::STATUS_REVIEW => [
            'title' => 'Giao dịch cần được hỗ trợ',
            'message' => 'Dữ liệu giao dịch cần bộ phận hỗ trợ kiểm tra thêm trước khi có kết luận chính thức.',
            'icon' => 'ph-magnifying-glass',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_EXPIRED => [
            'title' => 'Lần thanh toán đã hết hạn',
            'message' => 'Thời hạn thanh toán đã kết thúc. Đơn chưa đủ điều kiện phát hành QR đơn đặt vé.',
            'icon' => 'ph-clock-countdown',
            'colour' => 'text-slate-500',
        ],
    ];
    $states[\App\Models\Payment::STATUS_UNRESOLVED] = $states[\App\Models\Payment::STATUS_PENDING];
    $states[\App\Models\Payment::STATUS_PROCESSING] = $states[\App\Models\Payment::STATUS_PENDING];
    $isConfirmedCancellation = $booking->booking_status === 'cancelled'
        && $payment->status === \App\Models\Payment::STATUS_FAILED;
    if ($isConfirmedCancellation) {
        $states[\App\Models\Payment::STATUS_FAILED] = [
            'title' => 'Thanh toán đã được hủy',
            'message' => $payment->provider === 'vnpay' && $payment->failure_reason === 'vnpay_customer_cancelled'
                ? 'Bạn đã hủy giao dịch VNPAY. Các ghế đã giữ cho đơn này đã được giải phóng.'
                : 'Các ghế đã giữ cho đơn này đã được giải phóng.',
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ];
    }
    if (($cancelRequested ?? false) && ! $isConfirmedCancellation
        && in_array($payment->status, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true)) {
        $states[$stateStatus] = [
            'title' => 'Đang xác minh việc hủy thanh toán',
            'message' => 'Ghế của bạn vẫn được giữ tạm thời để tránh mất vé trong khi hệ thống xác minh trạng thái giao dịch.',
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ];
    }
    if ($isCinemaCancelled) {
        $stateStatus = 'showtime_cancelled';
        $states[$stateStatus] = [
            'title' => 'Suất chiếu đã bị rạp hủy',
            'message' => match($booking->refundCase?->status) {
                \App\Models\RefundCase::STATUS_REQUIRED => 'Thanh toán đã được xác minh. Cần xử lý hoàn tiền; đơn vẫn bị hủy và không phát hành QR hoặc vé sử dụng.',
                \App\Models\RefundCase::STATUS_RESOLVED => 'Thanh toán đã được giữ trong lịch sử và rạp đã ghi nhận hoàn tiền.',
                default => 'Bạn chưa có khoản thanh toán cần hoàn.',
            },
            'icon' => 'ph-calendar-x',
            'colour' => 'text-error',
        ];
    }
    $state = $states[$stateStatus] ?? [
        'title' => 'Đang xử lý trạng thái',
        'message' => 'MovieMate đang kiểm tra dữ liệu giao dịch hiện tại.',
        'icon' => 'ph-spinner-gap',
        'colour' => 'text-slate-500',
    ];
    $holdExpiresAt = $booking->expires_at ?? $payment->expires_at;
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

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true) && $holdExpiresAt)
                <div class="mx-auto mt-6 max-w-md rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4" data-countdown-wrapper>
                    <p class="text-xs font-bold uppercase tracking-wide text-warning">Thời gian lần thanh toán còn lại</p>
                    <p class="mt-1 text-3xl font-extrabold app-text" data-countdown="{{ $holdExpiresAt->toIso8601String() }}" data-expired-label="Đã hết thời gian">--:--</p>
                </div>
            @endif

            <dl class="mx-auto mt-7 max-w-xl rounded-2xl app-secondary p-5 text-left text-sm">
                @if($canViewBooking)
                    <div class="flex justify-between gap-4"><dt class="app-muted">Mã đặt vé</dt><dd class="break-all text-right font-mono font-bold text-brand-start">{{ $booking->booking_code }}</dd></div>
                @else
                    <div class="flex justify-between gap-4"><dt class="app-muted">Lần thanh toán</dt><dd class="text-right font-mono font-bold text-brand-start">#{{ $payment->id }}</dd></div>
                @endif
                <div class="mt-3 flex justify-between gap-4"><dt class="app-muted">Kênh thanh toán</dt><dd class="font-bold app-text">{{ $providerLabel }}</dd></div>
                <div class="mt-3 flex justify-between gap-4 border-t pt-3 app-border"><dt class="font-bold app-text">Số tiền</dt><dd class="font-extrabold text-brand-start">{{ number_format((int) $payment->amount, 0, ',', '.') }} {{ ($payment->currency ?: 'VND') === 'VND' ? 'VNĐ' : $payment->currency }}</dd></div>
            </dl>

            <p class="mx-auto mt-5 max-w-xl text-xs leading-relaxed app-muted">
                Trạng thái trên lấy từ cơ sở dữ liệu MovieMate. Dữ liệu trình duyệt
                {{ $integrityVerified ? 'có checksum hợp lệ' : 'không được xác thực' }}
                và không thể tự đánh dấu giao dịch là đã thanh toán.
            </p>

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING, \App\Models\Payment::STATUS_REVIEW], true))
                <div class="mx-auto mt-5 max-w-xl rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4 text-left text-sm leading-relaxed text-warning" role="note">
                    <strong>Không tạo lại thanh toán khi chưa rõ kết quả.</strong> Nếu {{ $providerLabel }} đã trừ tiền, hãy giữ mã đặt vé và chờ MovieMate xác minh hoặc đối soát giao dịch hiện tại.
                </div>
            @endif

            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                @if($isVerifiedPaid && $canViewTicket)
                    <a href="{{ route('user.bookings.ticket', $booking) }}" data-paid-ticket-link class="btn-primary">
                        <i class="ph-fill ph-ticket" aria-hidden="true"></i>
                        Mở đơn đặt vé
                    </a>
                @elseif($isVerifiedPaid)
                    <p class="app-muted">Liên kết mở đơn đặt vé an toàn được gửi riêng qua email và không được cấp bởi trang quay lại thanh toán.</p>
                @endif
                @if($canViewBooking)
                    <a href="{{ route('user.bookings.success', $booking) }}" class="btn-secondary">Xem chi tiết đơn đặt vé</a>
                @endif
            </div>
        </section>
    </div>
</main>
@endsection
@extends('layouts.user')

@section('title', 'Trạng thái thanh toán - MovieMate')

@section('content')
@php
    $providerLabel = match ($payment->provider) {
        'vnpay' => 'VNPAY',
        'payos' => 'payOS',
        default => 'ZaloPay',
    };
    $isVerifiedPaid = $payment->status === \App\Models\Payment::STATUS_SUCCESS
        && $payment->verified_at !== null
        && $booking->payment_status === 'paid'
        && $booking->booking_status === 'paid';
    $isCinemaCancelled = $booking->booking_status === 'cancelled' && $booking->showtimeCancellationImpact !== null;
    $stateStatus = $payment->status === \App\Models\Payment::STATUS_SUCCESS && ! $isVerifiedPaid
        ? \App\Models\Payment::STATUS_REVIEW
        : $payment->status;
    if ($booking->booking_status === 'pending_payment'
        && $stateStatus === \App\Models\Payment::STATUS_FAILED) {
        $stateStatus = \App\Models\Payment::STATUS_PENDING;
    }
    if ($booking->booking_status === 'expired'
        && $payment->failure_reason === 'vnpay_terminal_expired') {
        $stateStatus = \App\Models\Payment::STATUS_EXPIRED;
    }
    $states = [
        \App\Models\Payment::STATUS_PENDING => [
            'title' => 'Đang xác minh kết quả thanh toán',
            'message' => "MovieMate chưa nhận được kết quả cuối cùng từ {$providerLabel}. Hệ thống sẽ cập nhật đơn khi có kết quả chính thức.",
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_SUCCESS => [
            'title' => 'Đặt vé thành công',
            'message' => 'Thanh toán đã được xác minh. Đơn đặt vé và QR đơn đặt vé đã sẵn sàng để xuất trình tại quầy.',
            'icon' => 'ph-check-circle',
            'colour' => 'text-success',
        ],
        \App\Models\Payment::STATUS_FAILED => [
            'title' => 'Thanh toán không thành công',
            'message' => "{$providerLabel} đã trả về trạng thái không thành công. Đơn đặt vé này chưa đủ điều kiện nhận vé.",
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ],
        \App\Models\Payment::STATUS_REVIEW => [
            'title' => 'Giao dịch cần được hỗ trợ',
            'message' => 'Dữ liệu giao dịch cần bộ phận hỗ trợ kiểm tra thêm trước khi có kết luận chính thức.',
            'icon' => 'ph-magnifying-glass',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_EXPIRED => [
            'title' => 'Lần thanh toán đã hết hạn',
            'message' => 'Thời hạn thanh toán đã kết thúc. Đơn chưa đủ điều kiện phát hành QR đơn đặt vé.',
            'icon' => 'ph-clock-countdown',
            'colour' => 'text-slate-500',
        ],
    ];
    $states[\App\Models\Payment::STATUS_UNRESOLVED] = $states[\App\Models\Payment::STATUS_PENDING];
    $states[\App\Models\Payment::STATUS_PROCESSING] = $states[\App\Models\Payment::STATUS_PENDING];
    $isConfirmedCancellation = $booking->booking_status === 'cancelled'
        && $payment->status === \App\Models\Payment::STATUS_FAILED;
    if ($isConfirmedCancellation) {
        $states[\App\Models\Payment::STATUS_FAILED] = [
            'title' => 'Thanh toán đã được hủy',
            'message' => $payment->provider === 'vnpay' && $payment->failure_reason === 'vnpay_customer_cancelled'
                ? 'Bạn đã hủy giao dịch VNPAY. Các ghế đã giữ cho đơn này đã được giải phóng.'
                : 'Các ghế đã giữ cho đơn này đã được giải phóng.',
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ];
    }
    if (($cancelRequested ?? false) && ! $isConfirmedCancellation
        && in_array($payment->status, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true)) {
        $states[$stateStatus] = [
            'title' => 'Đang xác minh việc hủy thanh toán',
            'message' => 'Ghế của bạn vẫn được giữ tạm thời để tránh mất vé trong khi hệ thống xác minh trạng thái giao dịch.',
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ];
    }
    if ($isCinemaCancelled) {
        $stateStatus = 'showtime_cancelled';
        $states[$stateStatus] = [
            'title' => 'Suất chiếu đã bị rạp hủy',
            'message' => match($booking->refundCase?->status) {
                \App\Models\RefundCase::STATUS_REQUIRED => 'Thanh toán đã được xác minh. Cần xử lý hoàn tiền; đơn vẫn bị hủy và không phát hành QR hoặc vé sử dụng.',
                \App\Models\RefundCase::STATUS_RESOLVED => 'Thanh toán đã được giữ trong lịch sử và rạp đã ghi nhận hoàn tiền.',
                default => 'Bạn chưa có khoản thanh toán cần hoàn.',
            },
            'icon' => 'ph-calendar-x',
            'colour' => 'text-error',
        ];
    }
    $state = $states[$stateStatus] ?? [
        'title' => 'Đang xử lý trạng thái',
        'message' => 'MovieMate đang kiểm tra dữ liệu giao dịch hiện tại.',
        'icon' => 'ph-spinner-gap',
        'colour' => 'text-slate-500',
    ];
    $holdExpiresAt = $booking->expires_at ?? $payment->expires_at;
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

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true) && $holdExpiresAt)
                <div class="mx-auto mt-6 max-w-md rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4" data-countdown-wrapper>
                    <p class="text-xs font-bold uppercase tracking-wide text-warning">Thời gian lần thanh toán còn lại</p>
                    <p class="mt-1 text-3xl font-extrabold app-text" data-countdown="{{ $holdExpiresAt->toIso8601String() }}" data-expired-label="Đã hết thời gian">--:--</p>
                </div>
            @endif

            <dl class="mx-auto mt-7 max-w-xl rounded-2xl app-secondary p-5 text-left text-sm">
                @if($canViewBooking)
                    <div class="flex justify-between gap-4"><dt class="app-muted">Mã đặt vé</dt><dd class="break-all text-right font-mono font-bold text-brand-start">{{ $booking->booking_code }}</dd></div>
                @else
                    <div class="flex justify-between gap-4"><dt class="app-muted">Lần thanh toán</dt><dd class="text-right font-mono font-bold text-brand-start">#{{ $payment->id }}</dd></div>
                @endif
                <div class="mt-3 flex justify-between gap-4"><dt class="app-muted">Kênh thanh toán</dt><dd class="font-bold app-text">{{ $providerLabel }}</dd></div>
                <div class="mt-3 flex justify-between gap-4 border-t pt-3 app-border"><dt class="font-bold app-text">Số tiền</dt><dd class="font-extrabold text-brand-start">{{ number_format((int) $payment->amount, 0, ',', '.') }} {{ ($payment->currency ?: 'VND') === 'VND' ? 'VNĐ' : $payment->currency }}</dd></div>
            </dl>

            <p class="mx-auto mt-5 max-w-xl text-xs leading-relaxed app-muted">
                Trạng thái trên lấy từ cơ sở dữ liệu MovieMate. Dữ liệu trình duyệt
                {{ $integrityVerified ? 'có checksum hợp lệ' : 'không được xác thực' }}
                và không thể tự đánh dấu giao dịch là đã thanh toán.
            </p>

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING, \App\Models\Payment::STATUS_REVIEW], true))
                <div class="mx-auto mt-5 max-w-xl rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4 text-left text-sm leading-relaxed text-warning" role="note">
                    <strong>Không tạo lại thanh toán khi chưa rõ kết quả.</strong> Nếu {{ $providerLabel }} đã trừ tiền, hãy giữ mã đặt vé và chờ MovieMate xác minh hoặc đối soát giao dịch hiện tại.
                </div>
            @endif

            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                @if($isVerifiedPaid && $canViewTicket)
                    <a href="{{ route('user.bookings.ticket', $booking) }}" data-paid-ticket-link class="btn-primary">
                        <i class="ph-fill ph-ticket" aria-hidden="true"></i>
                        Mở đơn đặt vé
                    </a>
                @elseif($isVerifiedPaid)
                    <p class="app-muted">Liên kết mở đơn đặt vé an toàn được gửi riêng qua email và không được cấp bởi trang quay lại thanh toán.</p>
                @endif
                @if($canViewBooking)
                    <a href="{{ route('user.bookings.success', $booking) }}" class="btn-secondary">Xem chi tiết đơn đặt vé</a>
                @endif
            </div>
        </section>
    </div>
</main>
@endsection
@extends('layouts.user')

@section('title', 'Trạng thái thanh toán - MovieMate')

@section('content')
@php
    $providerLabel = match ($payment->provider) {
        'vnpay' => 'VNPAY',
        'payos' => 'payOS',
        default => 'ZaloPay',
    };
    $isVerifiedPaid = $payment->status === \App\Models\Payment::STATUS_SUCCESS
        && $payment->verified_at !== null
        && $booking->payment_status === 'paid'
        && $booking->booking_status === 'paid';
    $isCinemaCancelled = $booking->booking_status === 'cancelled' && $booking->showtimeCancellationImpact !== null;
    $stateStatus = $payment->status === \App\Models\Payment::STATUS_SUCCESS && ! $isVerifiedPaid
        ? \App\Models\Payment::STATUS_REVIEW
        : $payment->status;
    if ($booking->booking_status === 'pending_payment'
        && $stateStatus === \App\Models\Payment::STATUS_FAILED) {
        $stateStatus = \App\Models\Payment::STATUS_PENDING;
    }
    if ($booking->booking_status === 'expired'
        && $payment->failure_reason === 'vnpay_terminal_expired') {
        $stateStatus = \App\Models\Payment::STATUS_EXPIRED;
    }
    $states = [
        \App\Models\Payment::STATUS_PENDING => [
            'title' => 'Đang xác minh kết quả thanh toán',
            'message' => "MovieMate chưa nhận được kết quả cuối cùng từ {$providerLabel}. Hệ thống sẽ cập nhật đơn khi có kết quả chính thức.",
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_SUCCESS => [
            'title' => 'Đặt vé thành công',
            'message' => 'Thanh toán đã được xác minh. Đơn đặt vé và QR đơn đặt vé đã sẵn sàng để xuất trình tại quầy.',
            'icon' => 'ph-check-circle',
            'colour' => 'text-success',
        ],
        \App\Models\Payment::STATUS_FAILED => [
            'title' => 'Thanh toán không thành công',
            'message' => "{$providerLabel} đã trả về trạng thái không thành công. Đơn đặt vé này chưa đủ điều kiện nhận vé.",
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ],
        \App\Models\Payment::STATUS_REVIEW => [
            'title' => 'Giao dịch cần được hỗ trợ',
            'message' => 'Dữ liệu giao dịch cần bộ phận hỗ trợ kiểm tra thêm trước khi có kết luận chính thức.',
            'icon' => 'ph-magnifying-glass',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_EXPIRED => [
            'title' => 'Lần thanh toán đã hết hạn',
            'message' => 'Thời hạn thanh toán đã kết thúc. Đơn chưa đủ điều kiện phát hành QR đơn đặt vé.',
            'icon' => 'ph-clock-countdown',
            'colour' => 'text-slate-500',
        ],
    ];
    $states[\App\Models\Payment::STATUS_UNRESOLVED] = $states[\App\Models\Payment::STATUS_PENDING];
    $states[\App\Models\Payment::STATUS_PROCESSING] = $states[\App\Models\Payment::STATUS_PENDING];
    $isConfirmedCancellation = $booking->booking_status === 'cancelled'
        && $payment->status === \App\Models\Payment::STATUS_FAILED;
    if ($isConfirmedCancellation) {
        $states[\App\Models\Payment::STATUS_FAILED] = [
            'title' => 'Thanh toán đã được hủy',
            'message' => $payment->provider === 'vnpay' && $payment->failure_reason === 'vnpay_customer_cancelled'
                ? 'Bạn đã hủy giao dịch VNPAY. Các ghế đã giữ cho đơn này đã được giải phóng.'
                : 'Các ghế đã giữ cho đơn này đã được giải phóng.',
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ];
    }
    if (($cancelRequested ?? false) && ! $isConfirmedCancellation
        && in_array($payment->status, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true)) {
        $states[$stateStatus] = [
            'title' => 'Đang xác minh việc hủy thanh toán',
            'message' => 'Ghế của bạn vẫn được giữ tạm thời để tránh mất vé trong khi hệ thống xác minh trạng thái giao dịch.',
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ];
    }
    if ($isCinemaCancelled) {
        $stateStatus = 'showtime_cancelled';
        $states[$stateStatus] = [
            'title' => 'Suất chiếu đã bị rạp hủy',
            'message' => match($booking->refundCase?->status) {
                \App\Models\RefundCase::STATUS_REQUIRED => 'Thanh toán đã được xác minh. Cần xử lý hoàn tiền; đơn vẫn bị hủy và không phát hành QR hoặc vé sử dụng.',
                \App\Models\RefundCase::STATUS_RESOLVED => 'Thanh toán đã được giữ trong lịch sử và rạp đã ghi nhận hoàn tiền.',
                default => 'Bạn chưa có khoản thanh toán cần hoàn.',
            },
            'icon' => 'ph-calendar-x',
            'colour' => 'text-error',
        ];
    }
    $state = $states[$stateStatus] ?? [
        'title' => 'Đang xử lý trạng thái',
        'message' => 'MovieMate đang kiểm tra dữ liệu giao dịch hiện tại.',
        'icon' => 'ph-spinner-gap',
        'colour' => 'text-slate-500',
    ];
    $holdExpiresAt = $booking->expires_at ?? $payment->expires_at;
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

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true) && $holdExpiresAt)
                <div class="mx-auto mt-6 max-w-md rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4" data-countdown-wrapper>
                    <p class="text-xs font-bold uppercase tracking-wide text-warning">Thời gian lần thanh toán còn lại</p>
                    <p class="mt-1 text-3xl font-extrabold app-text" data-countdown="{{ $holdExpiresAt->toIso8601String() }}" data-expired-label="Đã hết thời gian">--:--</p>
                </div>
            @endif

            <dl class="mx-auto mt-7 max-w-xl rounded-2xl app-secondary p-5 text-left text-sm">
                @if($canViewBooking)
                    <div class="flex justify-between gap-4"><dt class="app-muted">Mã đặt vé</dt><dd class="break-all text-right font-mono font-bold text-brand-start">{{ $booking->booking_code }}</dd></div>
                @else
                    <div class="flex justify-between gap-4"><dt class="app-muted">Lần thanh toán</dt><dd class="text-right font-mono font-bold text-brand-start">#{{ $payment->id }}</dd></div>
                @endif
                <div class="mt-3 flex justify-between gap-4"><dt class="app-muted">Kênh thanh toán</dt><dd class="font-bold app-text">{{ $providerLabel }}</dd></div>
                <div class="mt-3 flex justify-between gap-4 border-t pt-3 app-border"><dt class="font-bold app-text">Số tiền</dt><dd class="font-extrabold text-brand-start">{{ number_format((int) $payment->amount, 0, ',', '.') }} {{ ($payment->currency ?: 'VND') === 'VND' ? 'VNĐ' : $payment->currency }}</dd></div>
            </dl>

            <p class="mx-auto mt-5 max-w-xl text-xs leading-relaxed app-muted">
                Trạng thái trên lấy từ cơ sở dữ liệu MovieMate. Dữ liệu trình duyệt
                {{ $integrityVerified ? 'có checksum hợp lệ' : 'không được xác thực' }}
                và không thể tự đánh dấu giao dịch là đã thanh toán.
            </p>

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING, \App\Models\Payment::STATUS_REVIEW], true))
                <div class="mx-auto mt-5 max-w-xl rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4 text-left text-sm leading-relaxed text-warning" role="note">
                    <strong>Không tạo lại thanh toán khi chưa rõ kết quả.</strong> Nếu {{ $providerLabel }} đã trừ tiền, hãy giữ mã đặt vé và chờ MovieMate xác minh hoặc đối soát giao dịch hiện tại.
                </div>
            @endif

            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                @if($isVerifiedPaid && $canViewTicket)
                    <a href="{{ route('user.bookings.ticket', $booking) }}" data-paid-ticket-link class="btn-primary">
                        <i class="ph-fill ph-ticket" aria-hidden="true"></i>
                        Mở đơn đặt vé
                    </a>
                @elseif($isVerifiedPaid)
                    <p class="app-muted">Liên kết mở đơn đặt vé an toàn được gửi riêng qua email và không được cấp bởi trang quay lại thanh toán.</p>
                @endif
                @if($canViewBooking)
                    <a href="{{ route('user.bookings.success', $booking) }}" class="btn-secondary">Xem chi tiết đơn đặt vé</a>
                @endif
            </div>
        </section>
    </div>
</main>
@endsection
@extends('layouts.user')

@section('title', 'Trạng thái thanh toán - MovieMate')

@section('content')
@php
    $providerLabel = match ($payment->provider) {
        'vnpay' => 'VNPAY',
        'payos' => 'payOS',
        default => 'ZaloPay',
    };
    $isVerifiedPaid = $payment->status === \App\Models\Payment::STATUS_SUCCESS
        && $payment->verified_at !== null
        && $booking->payment_status === 'paid'
        && $booking->booking_status === 'paid';
    $isCinemaCancelled = $booking->booking_status === 'cancelled' && $booking->showtimeCancellationImpact !== null;
    $stateStatus = $payment->status === \App\Models\Payment::STATUS_SUCCESS && ! $isVerifiedPaid
        ? \App\Models\Payment::STATUS_REVIEW
        : $payment->status;
    if ($booking->booking_status === 'pending_payment'
        && $stateStatus === \App\Models\Payment::STATUS_FAILED) {
        $stateStatus = \App\Models\Payment::STATUS_PENDING;
    }
    if ($booking->booking_status === 'expired'
        && $payment->failure_reason === 'vnpay_terminal_expired') {
        $stateStatus = \App\Models\Payment::STATUS_EXPIRED;
    }
    $states = [
        \App\Models\Payment::STATUS_PENDING => [
            'title' => 'Đang xác minh kết quả thanh toán',
            'message' => "MovieMate chưa nhận được kết quả cuối cùng từ {$providerLabel}. Hệ thống sẽ cập nhật đơn khi có kết quả chính thức.",
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_SUCCESS => [
            'title' => 'Đặt vé thành công',
            'message' => 'Thanh toán đã được xác minh. Đơn đặt vé và QR đơn đặt vé đã sẵn sàng để xuất trình tại quầy.',
            'icon' => 'ph-check-circle',
            'colour' => 'text-success',
        ],
        \App\Models\Payment::STATUS_FAILED => [
            'title' => 'Thanh toán không thành công',
            'message' => "{$providerLabel} đã trả về trạng thái không thành công. Đơn đặt vé này chưa đủ điều kiện nhận vé.",
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ],
        \App\Models\Payment::STATUS_REVIEW => [
            'title' => 'Giao dịch cần được hỗ trợ',
            'message' => 'Dữ liệu giao dịch cần bộ phận hỗ trợ kiểm tra thêm trước khi có kết luận chính thức.',
            'icon' => 'ph-magnifying-glass',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_EXPIRED => [
            'title' => 'Lần thanh toán đã hết hạn',
            'message' => 'Thời hạn thanh toán đã kết thúc. Đơn chưa đủ điều kiện phát hành QR đơn đặt vé.',
            'icon' => 'ph-clock-countdown',
            'colour' => 'text-slate-500',
        ],
    ];
    $states[\App\Models\Payment::STATUS_UNRESOLVED] = $states[\App\Models\Payment::STATUS_PENDING];
    $states[\App\Models\Payment::STATUS_PROCESSING] = $states[\App\Models\Payment::STATUS_PENDING];
    $isConfirmedCancellation = $booking->booking_status === 'cancelled'
        && $payment->status === \App\Models\Payment::STATUS_FAILED;
    if ($isConfirmedCancellation) {
        $states[\App\Models\Payment::STATUS_FAILED] = [
            'title' => 'Thanh toán đã được hủy',
            'message' => $payment->provider === 'vnpay' && $payment->failure_reason === 'vnpay_customer_cancelled'
                ? 'Bạn đã hủy giao dịch VNPAY. Các ghế đã giữ cho đơn này đã được giải phóng.'
                : 'Các ghế đã giữ cho đơn này đã được giải phóng.',
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ];
    }
    if (($cancelRequested ?? false) && ! $isConfirmedCancellation
        && in_array($payment->status, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true)) {
        $states[$stateStatus] = [
            'title' => 'Đang xác minh việc hủy thanh toán',
            'message' => 'Ghế của bạn vẫn được giữ tạm thời để tránh mất vé trong khi hệ thống xác minh trạng thái giao dịch.',
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ];
    }
    if ($isCinemaCancelled) {
        $stateStatus = 'showtime_cancelled';
        $states[$stateStatus] = [
            'title' => 'Suất chiếu đã bị rạp hủy',
            'message' => match($booking->refundCase?->status) {
                \App\Models\RefundCase::STATUS_REQUIRED => 'Thanh toán đã được xác minh. Cần xử lý hoàn tiền; đơn vẫn bị hủy và không phát hành QR hoặc vé sử dụng.',
                \App\Models\RefundCase::STATUS_RESOLVED => 'Thanh toán đã được giữ trong lịch sử và rạp đã ghi nhận hoàn tiền.',
                default => 'Bạn chưa có khoản thanh toán cần hoàn.',
            },
            'icon' => 'ph-calendar-x',
            'colour' => 'text-error',
        ];
    }
    $state = $states[$stateStatus] ?? [
        'title' => 'Đang xử lý trạng thái',
        'message' => 'MovieMate đang kiểm tra dữ liệu giao dịch hiện tại.',
        'icon' => 'ph-spinner-gap',
        'colour' => 'text-slate-500',
    ];
    $holdExpiresAt = $booking->expires_at ?? $payment->expires_at;
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

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true) && $holdExpiresAt)
                <div class="mx-auto mt-6 max-w-md rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4" data-countdown-wrapper>
                    <p class="text-xs font-bold uppercase tracking-wide text-warning">Thời gian lần thanh toán còn lại</p>
                    <p class="mt-1 text-3xl font-extrabold app-text" data-countdown="{{ $holdExpiresAt->toIso8601String() }}" data-expired-label="Đã hết thời gian">--:--</p>
                </div>
            @endif

            <dl class="mx-auto mt-7 max-w-xl rounded-2xl app-secondary p-5 text-left text-sm">
                @if($canViewBooking)
                    <div class="flex justify-between gap-4"><dt class="app-muted">Mã đặt vé</dt><dd class="break-all text-right font-mono font-bold text-brand-start">{{ $booking->booking_code }}</dd></div>
                @else
                    <div class="flex justify-between gap-4"><dt class="app-muted">Lần thanh toán</dt><dd class="text-right font-mono font-bold text-brand-start">#{{ $payment->id }}</dd></div>
                @endif
                <div class="mt-3 flex justify-between gap-4"><dt class="app-muted">Kênh thanh toán</dt><dd class="font-bold app-text">{{ $providerLabel }}</dd></div>
                <div class="mt-3 flex justify-between gap-4 border-t pt-3 app-border"><dt class="font-bold app-text">Số tiền</dt><dd class="font-extrabold text-brand-start">{{ number_format((int) $payment->amount, 0, ',', '.') }} {{ ($payment->currency ?: 'VND') === 'VND' ? 'VNĐ' : $payment->currency }}</dd></div>
            </dl>

            <p class="mx-auto mt-5 max-w-xl text-xs leading-relaxed app-muted">
                Trạng thái trên lấy từ cơ sở dữ liệu MovieMate. Dữ liệu trình duyệt
                {{ $integrityVerified ? 'có checksum hợp lệ' : 'không được xác thực' }}
                và không thể tự đánh dấu giao dịch là đã thanh toán.
            </p>

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING, \App\Models\Payment::STATUS_REVIEW], true))
                <div class="mx-auto mt-5 max-w-xl rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4 text-left text-sm leading-relaxed text-warning" role="note">
                    <strong>Không tạo lại thanh toán khi chưa rõ kết quả.</strong> Nếu {{ $providerLabel }} đã trừ tiền, hãy giữ mã đặt vé và chờ MovieMate xác minh hoặc đối soát giao dịch hiện tại.
                </div>
            @endif

            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                @if($isVerifiedPaid && $canViewTicket)
                    <a href="{{ route('user.bookings.ticket', $booking) }}" data-paid-ticket-link class="btn-primary">
                        <i class="ph-fill ph-ticket" aria-hidden="true"></i>
                        Mở đơn đặt vé
                    </a>
                @elseif($isVerifiedPaid)
                    <p class="app-muted">Liên kết mở đơn đặt vé an toàn được gửi riêng qua email và không được cấp bởi trang quay lại thanh toán.</p>
                @endif
                @if($canViewBooking)
                    <a href="{{ route('user.bookings.success', $booking) }}" class="btn-secondary">Xem chi tiết đơn đặt vé</a>
                @endif
            </div>
        </section>
    </div>
</main>
@endsection
@extends('layouts.user')

@section('title', 'Trạng thái thanh toán - MovieMate')

@section('content')
@php
    $providerLabel = match ($payment->provider) {
        'vnpay' => 'VNPAY',
        'payos' => 'payOS',
        default => 'ZaloPay',
    };
    $isVerifiedPaid = $payment->status === \App\Models\Payment::STATUS_SUCCESS
        && $payment->verified_at !== null
        && $booking->payment_status === 'paid'
        && $booking->booking_status === 'paid';
    $isCinemaCancelled = $booking->booking_status === 'cancelled' && $booking->showtimeCancellationImpact !== null;
    $stateStatus = $payment->status === \App\Models\Payment::STATUS_SUCCESS && ! $isVerifiedPaid
        ? \App\Models\Payment::STATUS_REVIEW
        : $payment->status;
    if ($booking->booking_status === 'pending_payment'
        && $stateStatus === \App\Models\Payment::STATUS_FAILED) {
        $stateStatus = \App\Models\Payment::STATUS_PENDING;
    }
    if ($booking->booking_status === 'expired'
        && $payment->failure_reason === 'vnpay_terminal_expired') {
        $stateStatus = \App\Models\Payment::STATUS_EXPIRED;
    }
    $states = [
        \App\Models\Payment::STATUS_PENDING => [
            'title' => 'Đang xác minh kết quả thanh toán',
            'message' => "MovieMate chưa nhận được kết quả cuối cùng từ {$providerLabel}. Hệ thống sẽ cập nhật đơn khi có kết quả chính thức.",
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_SUCCESS => [
            'title' => 'Đặt vé thành công',
            'message' => 'Thanh toán đã được xác minh. Đơn đặt vé và QR đơn đặt vé đã sẵn sàng để xuất trình tại quầy.',
            'icon' => 'ph-check-circle',
            'colour' => 'text-success',
        ],
        \App\Models\Payment::STATUS_FAILED => [
            'title' => 'Thanh toán không thành công',
            'message' => "{$providerLabel} đã trả về trạng thái không thành công. Đơn đặt vé này chưa đủ điều kiện nhận vé.",
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ],
        \App\Models\Payment::STATUS_REVIEW => [
            'title' => 'Giao dịch cần được hỗ trợ',
            'message' => 'Dữ liệu giao dịch cần bộ phận hỗ trợ kiểm tra thêm trước khi có kết luận chính thức.',
            'icon' => 'ph-magnifying-glass',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_EXPIRED => [
            'title' => 'Lần thanh toán đã hết hạn',
            'message' => 'Thời hạn thanh toán đã kết thúc. Đơn chưa đủ điều kiện phát hành QR đơn đặt vé.',
            'icon' => 'ph-clock-countdown',
            'colour' => 'text-slate-500',
        ],
    ];
    $states[\App\Models\Payment::STATUS_UNRESOLVED] = $states[\App\Models\Payment::STATUS_PENDING];
    $states[\App\Models\Payment::STATUS_PROCESSING] = $states[\App\Models\Payment::STATUS_PENDING];
    $isConfirmedCancellation = $booking->booking_status === 'cancelled'
        && $payment->status === \App\Models\Payment::STATUS_FAILED;
    if ($isConfirmedCancellation) {
        $states[\App\Models\Payment::STATUS_FAILED] = [
            'title' => 'Thanh toán đã được hủy',
            'message' => $payment->provider === 'vnpay' && $payment->failure_reason === 'vnpay_customer_cancelled'
                ? 'Bạn đã hủy giao dịch VNPAY. Các ghế đã giữ cho đơn này đã được giải phóng.'
                : 'Các ghế đã giữ cho đơn này đã được giải phóng.',
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ];
    }
    if (($cancelRequested ?? false) && ! $isConfirmedCancellation
        && in_array($payment->status, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true)) {
        $states[$stateStatus] = [
            'title' => 'Đang xác minh việc hủy thanh toán',
            'message' => 'Ghế của bạn vẫn được giữ tạm thời để tránh mất vé trong khi hệ thống xác minh trạng thái giao dịch.',
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ];
    }
    if ($isCinemaCancelled) {
        $stateStatus = 'showtime_cancelled';
        $states[$stateStatus] = [
            'title' => 'Suất chiếu đã bị rạp hủy',
            'message' => match($booking->refundCase?->status) {
                \App\Models\RefundCase::STATUS_REQUIRED => 'Thanh toán đã được xác minh. Cần xử lý hoàn tiền; đơn vẫn bị hủy và không phát hành QR hoặc vé sử dụng.',
                \App\Models\RefundCase::STATUS_RESOLVED => 'Thanh toán đã được giữ trong lịch sử và rạp đã ghi nhận hoàn tiền.',
                default => 'Bạn chưa có khoản thanh toán cần hoàn.',
            },
            'icon' => 'ph-calendar-x',
            'colour' => 'text-error',
        ];
    }
    $state = $states[$stateStatus] ?? [
        'title' => 'Đang xử lý trạng thái',
        'message' => 'MovieMate đang kiểm tra dữ liệu giao dịch hiện tại.',
        'icon' => 'ph-spinner-gap',
        'colour' => 'text-slate-500',
    ];
    $holdExpiresAt = $booking->expires_at ?? $payment->expires_at;
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

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true) && $holdExpiresAt)
                <div class="mx-auto mt-6 max-w-md rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4" data-countdown-wrapper>
                    <p class="text-xs font-bold uppercase tracking-wide text-warning">Thời gian lần thanh toán còn lại</p>
                    <p class="mt-1 text-3xl font-extrabold app-text" data-countdown="{{ $holdExpiresAt->toIso8601String() }}" data-expired-label="Đã hết thời gian">--:--</p>
                </div>
            @endif

            <dl class="mx-auto mt-7 max-w-xl rounded-2xl app-secondary p-5 text-left text-sm">
                @if($canViewBooking)
                    <div class="flex justify-between gap-4"><dt class="app-muted">Mã đặt vé</dt><dd class="break-all text-right font-mono font-bold text-brand-start">{{ $booking->booking_code }}</dd></div>
                @else
                    <div class="flex justify-between gap-4"><dt class="app-muted">Lần thanh toán</dt><dd class="text-right font-mono font-bold text-brand-start">#{{ $payment->id }}</dd></div>
                @endif
                <div class="mt-3 flex justify-between gap-4"><dt class="app-muted">Kênh thanh toán</dt><dd class="font-bold app-text">{{ $providerLabel }}</dd></div>
                <div class="mt-3 flex justify-between gap-4 border-t pt-3 app-border"><dt class="font-bold app-text">Số tiền</dt><dd class="font-extrabold text-brand-start">{{ number_format((int) $payment->amount, 0, ',', '.') }} {{ ($payment->currency ?: 'VND') === 'VND' ? 'VNĐ' : $payment->currency }}</dd></div>
            </dl>

            <p class="mx-auto mt-5 max-w-xl text-xs leading-relaxed app-muted">
                Trạng thái trên lấy từ cơ sở dữ liệu MovieMate. Dữ liệu trình duyệt
                {{ $integrityVerified ? 'có checksum hợp lệ' : 'không được xác thực' }}
                và không thể tự đánh dấu giao dịch là đã thanh toán.
            </p>

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING, \App\Models\Payment::STATUS_REVIEW], true))
                <div class="mx-auto mt-5 max-w-xl rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4 text-left text-sm leading-relaxed text-warning" role="note">
                    <strong>Không tạo lại thanh toán khi chưa rõ kết quả.</strong> Nếu {{ $providerLabel }} đã trừ tiền, hãy giữ mã đặt vé và chờ MovieMate xác minh hoặc đối soát giao dịch hiện tại.
                </div>
            @endif

            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                @if($isVerifiedPaid && $canViewTicket)
                    <a href="{{ route('user.bookings.ticket', $booking) }}" data-paid-ticket-link class="btn-primary">
                        <i class="ph-fill ph-ticket" aria-hidden="true"></i>
                        Mở đơn đặt vé
                    </a>
                @elseif($isVerifiedPaid)
                    <p class="app-muted">Liên kết mở đơn đặt vé an toàn được gửi riêng qua email và không được cấp bởi trang quay lại thanh toán.</p>
                @endif
                @if($canViewBooking)
                    <a href="{{ route('user.bookings.success', $booking) }}" class="btn-secondary">Xem chi tiết đơn đặt vé</a>
                @endif
            </div>
        </section>
    </div>
</main>
@endsection
@extends('layouts.user')

@section('title', 'Trạng thái thanh toán - MovieMate')

@section('content')
@php
    $providerLabel = match ($payment->provider) {
        'vnpay' => 'VNPAY',
        'payos' => 'payOS',
        default => 'ZaloPay',
    };
    $isVerifiedPaid = $payment->status === \App\Models\Payment::STATUS_SUCCESS
        && $payment->verified_at !== null
        && $booking->payment_status === 'paid'
        && $booking->booking_status === 'paid';
    $isCinemaCancelled = $booking->booking_status === 'cancelled' && $booking->showtimeCancellationImpact !== null;
    $stateStatus = $payment->status === \App\Models\Payment::STATUS_SUCCESS && ! $isVerifiedPaid
        ? \App\Models\Payment::STATUS_REVIEW
        : $payment->status;
    if ($booking->booking_status === 'pending_payment'
        && $stateStatus === \App\Models\Payment::STATUS_FAILED) {
        $stateStatus = \App\Models\Payment::STATUS_PENDING;
    }
    if ($booking->booking_status === 'expired'
        && $payment->failure_reason === 'vnpay_terminal_expired') {
        $stateStatus = \App\Models\Payment::STATUS_EXPIRED;
    }
    $states = [
        \App\Models\Payment::STATUS_PENDING => [
            'title' => 'Đang xác minh kết quả thanh toán',
            'message' => "MovieMate chưa nhận được kết quả cuối cùng từ {$providerLabel}. Hệ thống sẽ cập nhật đơn khi có kết quả chính thức.",
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_SUCCESS => [
            'title' => 'Đặt vé thành công',
            'message' => 'Thanh toán đã được xác minh. Đơn đặt vé và QR đơn đặt vé đã sẵn sàng để xuất trình tại quầy.',
            'icon' => 'ph-check-circle',
            'colour' => 'text-success',
        ],
        \App\Models\Payment::STATUS_FAILED => [
            'title' => 'Thanh toán không thành công',
            'message' => "{$providerLabel} đã trả về trạng thái không thành công. Đơn đặt vé này chưa đủ điều kiện nhận vé.",
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ],
        \App\Models\Payment::STATUS_REVIEW => [
            'title' => 'Giao dịch cần được hỗ trợ',
            'message' => 'Dữ liệu giao dịch cần bộ phận hỗ trợ kiểm tra thêm trước khi có kết luận chính thức.',
            'icon' => 'ph-magnifying-glass',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_EXPIRED => [
            'title' => 'Lần thanh toán đã hết hạn',
            'message' => 'Thời hạn thanh toán đã kết thúc. Đơn chưa đủ điều kiện phát hành QR đơn đặt vé.',
            'icon' => 'ph-clock-countdown',
            'colour' => 'text-slate-500',
        ],
    ];
    $states[\App\Models\Payment::STATUS_UNRESOLVED] = $states[\App\Models\Payment::STATUS_PENDING];
    $states[\App\Models\Payment::STATUS_PROCESSING] = $states[\App\Models\Payment::STATUS_PENDING];
    $isConfirmedCancellation = $booking->booking_status === 'cancelled'
        && $payment->status === \App\Models\Payment::STATUS_FAILED;
    if ($isConfirmedCancellation) {
        $states[\App\Models\Payment::STATUS_FAILED] = [
            'title' => 'Thanh toán đã được hủy',
            'message' => $payment->provider === 'vnpay' && $payment->failure_reason === 'vnpay_customer_cancelled'
                ? 'Bạn đã hủy giao dịch VNPAY. Các ghế đã giữ cho đơn này đã được giải phóng.'
                : 'Các ghế đã giữ cho đơn này đã được giải phóng.',
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ];
    }
    if (($cancelRequested ?? false) && ! $isConfirmedCancellation
        && in_array($payment->status, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true)) {
        $states[$stateStatus] = [
            'title' => 'Đang xác minh việc hủy thanh toán',
            'message' => 'Ghế của bạn vẫn được giữ tạm thời để tránh mất vé trong khi hệ thống xác minh trạng thái giao dịch.',
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ];
    }
    if ($isCinemaCancelled) {
        $stateStatus = 'showtime_cancelled';
        $states[$stateStatus] = [
            'title' => 'Suất chiếu đã bị rạp hủy',
            'message' => match($booking->refundCase?->status) {
                \App\Models\RefundCase::STATUS_REQUIRED => 'Thanh toán đã được xác minh. Cần xử lý hoàn tiền; đơn vẫn bị hủy và không phát hành QR hoặc vé sử dụng.',
                \App\Models\RefundCase::STATUS_RESOLVED => 'Thanh toán đã được giữ trong lịch sử và rạp đã ghi nhận hoàn tiền.',
                default => 'Bạn chưa có khoản thanh toán cần hoàn.',
            },
            'icon' => 'ph-calendar-x',
            'colour' => 'text-error',
        ];
    }
    $state = $states[$stateStatus] ?? [
        'title' => 'Đang xử lý trạng thái',
        'message' => 'MovieMate đang kiểm tra dữ liệu giao dịch hiện tại.',
        'icon' => 'ph-spinner-gap',
        'colour' => 'text-slate-500',
    ];
    $holdExpiresAt = $booking->expires_at ?? $payment->expires_at;
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

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true) && $holdExpiresAt)
                <div class="mx-auto mt-6 max-w-md rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4" data-countdown-wrapper>
                    <p class="text-xs font-bold uppercase tracking-wide text-warning">Thời gian lần thanh toán còn lại</p>
                    <p class="mt-1 text-3xl font-extrabold app-text" data-countdown="{{ $holdExpiresAt->toIso8601String() }}" data-expired-label="Đã hết thời gian">--:--</p>
                </div>
            @endif

            <dl class="mx-auto mt-7 max-w-xl rounded-2xl app-secondary p-5 text-left text-sm">
                @if($canViewBooking)
                    <div class="flex justify-between gap-4"><dt class="app-muted">Mã đặt vé</dt><dd class="break-all text-right font-mono font-bold text-brand-start">{{ $booking->booking_code }}</dd></div>
                @else
                    <div class="flex justify-between gap-4"><dt class="app-muted">Lần thanh toán</dt><dd class="text-right font-mono font-bold text-brand-start">#{{ $payment->id }}</dd></div>
                @endif
                <div class="mt-3 flex justify-between gap-4"><dt class="app-muted">Kênh thanh toán</dt><dd class="font-bold app-text">{{ $providerLabel }}</dd></div>
                <div class="mt-3 flex justify-between gap-4 border-t pt-3 app-border"><dt class="font-bold app-text">Số tiền</dt><dd class="font-extrabold text-brand-start">{{ number_format((int) $payment->amount, 0, ',', '.') }} {{ ($payment->currency ?: 'VND') === 'VND' ? 'VNĐ' : $payment->currency }}</dd></div>
            </dl>

            <p class="mx-auto mt-5 max-w-xl text-xs leading-relaxed app-muted">
                Trạng thái trên lấy từ cơ sở dữ liệu MovieMate. Dữ liệu trình duyệt
                {{ $integrityVerified ? 'có checksum hợp lệ' : 'không được xác thực' }}
                và không thể tự đánh dấu giao dịch là đã thanh toán.
            </p>

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING, \App\Models\Payment::STATUS_REVIEW], true))
                <div class="mx-auto mt-5 max-w-xl rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4 text-left text-sm leading-relaxed text-warning" role="note">
                    <strong>Không tạo lại thanh toán khi chưa rõ kết quả.</strong> Nếu {{ $providerLabel }} đã trừ tiền, hãy giữ mã đặt vé và chờ MovieMate xác minh hoặc đối soát giao dịch hiện tại.
                </div>
            @endif

            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                @if($isVerifiedPaid && $canViewTicket)
                    <a href="{{ route('user.bookings.ticket', $booking) }}" data-paid-ticket-link class="btn-primary">
                        <i class="ph-fill ph-ticket" aria-hidden="true"></i>
                        Mở đơn đặt vé
                    </a>
                @elseif($isVerifiedPaid)
                    <p class="app-muted">Liên kết mở đơn đặt vé an toàn được gửi riêng qua email và không được cấp bởi trang quay lại thanh toán.</p>
                @endif
                @if($canViewBooking)
                    <a href="{{ route('user.bookings.success', $booking) }}" class="btn-secondary">Xem chi tiết đơn đặt vé</a>
                @endif
            </div>
        </section>
    </div>
</main>
@endsection
@extends('layouts.user')

@section('title', 'Trạng thái thanh toán - MovieMate')

@section('content')
@php
    $providerLabel = match ($payment->provider) {
        'vnpay' => 'VNPAY',
        'payos' => 'payOS',
        default => 'ZaloPay',
    };
    $isVerifiedPaid = $payment->status === \App\Models\Payment::STATUS_SUCCESS
        && $payment->verified_at !== null
        && $booking->payment_status === 'paid'
        && $booking->booking_status === 'paid';
    $isCinemaCancelled = $booking->booking_status === 'cancelled' && $booking->showtimeCancellationImpact !== null;
    $stateStatus = $payment->status === \App\Models\Payment::STATUS_SUCCESS && ! $isVerifiedPaid
        ? \App\Models\Payment::STATUS_REVIEW
        : $payment->status;
    if ($booking->booking_status === 'pending_payment'
        && $stateStatus === \App\Models\Payment::STATUS_FAILED) {
        $stateStatus = \App\Models\Payment::STATUS_PENDING;
    }
    if ($booking->booking_status === 'expired'
        && $payment->failure_reason === 'vnpay_terminal_expired') {
        $stateStatus = \App\Models\Payment::STATUS_EXPIRED;
    }
    $states = [
        \App\Models\Payment::STATUS_PENDING => [
            'title' => 'Đang xác minh kết quả thanh toán',
            'message' => "MovieMate chưa nhận được kết quả cuối cùng từ {$providerLabel}. Hệ thống sẽ cập nhật đơn khi có kết quả chính thức.",
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_SUCCESS => [
            'title' => 'Đặt vé thành công',
            'message' => 'Thanh toán đã được xác minh. Đơn đặt vé và QR đơn đặt vé đã sẵn sàng để xuất trình tại quầy.',
            'icon' => 'ph-check-circle',
            'colour' => 'text-success',
        ],
        \App\Models\Payment::STATUS_FAILED => [
            'title' => 'Thanh toán không thành công',
            'message' => "{$providerLabel} đã trả về trạng thái không thành công. Đơn đặt vé này chưa đủ điều kiện nhận vé.",
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ],
        \App\Models\Payment::STATUS_REVIEW => [
            'title' => 'Giao dịch cần được hỗ trợ',
            'message' => 'Dữ liệu giao dịch cần bộ phận hỗ trợ kiểm tra thêm trước khi có kết luận chính thức.',
            'icon' => 'ph-magnifying-glass',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_EXPIRED => [
            'title' => 'Lần thanh toán đã hết hạn',
            'message' => 'Thời hạn thanh toán đã kết thúc. Đơn chưa đủ điều kiện phát hành QR đơn đặt vé.',
            'icon' => 'ph-clock-countdown',
            'colour' => 'text-slate-500',
        ],
    ];
    $states[\App\Models\Payment::STATUS_UNRESOLVED] = $states[\App\Models\Payment::STATUS_PENDING];
    $states[\App\Models\Payment::STATUS_PROCESSING] = $states[\App\Models\Payment::STATUS_PENDING];
    $isConfirmedCancellation = $booking->booking_status === 'cancelled'
        && $payment->status === \App\Models\Payment::STATUS_FAILED;
    if ($isConfirmedCancellation) {
        $states[\App\Models\Payment::STATUS_FAILED] = [
            'title' => 'Thanh toán đã được hủy',
            'message' => $payment->provider === 'vnpay' && $payment->failure_reason === 'vnpay_customer_cancelled'
                ? 'Bạn đã hủy giao dịch VNPAY. Các ghế đã giữ cho đơn này đã được giải phóng.'
                : 'Các ghế đã giữ cho đơn này đã được giải phóng.',
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ];
    }
    if (($cancelRequested ?? false) && ! $isConfirmedCancellation
        && in_array($payment->status, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true)) {
        $states[$stateStatus] = [
            'title' => 'Đang xác minh việc hủy thanh toán',
            'message' => 'Ghế của bạn vẫn được giữ tạm thời để tránh mất vé trong khi hệ thống xác minh trạng thái giao dịch.',
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ];
    }
    if ($isCinemaCancelled) {
        $stateStatus = 'showtime_cancelled';
        $states[$stateStatus] = [
            'title' => 'Suất chiếu đã bị rạp hủy',
            'message' => match($booking->refundCase?->status) {
                \App\Models\RefundCase::STATUS_REQUIRED => 'Thanh toán đã được xác minh. Cần xử lý hoàn tiền; đơn vẫn bị hủy và không phát hành QR hoặc vé sử dụng.',
                \App\Models\RefundCase::STATUS_RESOLVED => 'Thanh toán đã được giữ trong lịch sử và rạp đã ghi nhận hoàn tiền.',
                default => 'Bạn chưa có khoản thanh toán cần hoàn.',
            },
            'icon' => 'ph-calendar-x',
            'colour' => 'text-error',
        ];
    }
    $state = $states[$stateStatus] ?? [
        'title' => 'Đang xử lý trạng thái',
        'message' => 'MovieMate đang kiểm tra dữ liệu giao dịch hiện tại.',
        'icon' => 'ph-spinner-gap',
        'colour' => 'text-slate-500',
    ];
    $holdExpiresAt = $booking->expires_at ?? $payment->expires_at;
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

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true) && $holdExpiresAt)
                <div class="mx-auto mt-6 max-w-md rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4" data-countdown-wrapper>
                    <p class="text-xs font-bold uppercase tracking-wide text-warning">Thời gian lần thanh toán còn lại</p>
                    <p class="mt-1 text-3xl font-extrabold app-text" data-countdown="{{ $holdExpiresAt->toIso8601String() }}" data-expired-label="Đã hết thời gian">--:--</p>
                </div>
            @endif

            <dl class="mx-auto mt-7 max-w-xl rounded-2xl app-secondary p-5 text-left text-sm">
                @if($canViewBooking)
                    <div class="flex justify-between gap-4"><dt class="app-muted">Mã đặt vé</dt><dd class="break-all text-right font-mono font-bold text-brand-start">{{ $booking->booking_code }}</dd></div>
                @else
                    <div class="flex justify-between gap-4"><dt class="app-muted">Lần thanh toán</dt><dd class="text-right font-mono font-bold text-brand-start">#{{ $payment->id }}</dd></div>
                @endif
                <div class="mt-3 flex justify-between gap-4"><dt class="app-muted">Kênh thanh toán</dt><dd class="font-bold app-text">{{ $providerLabel }}</dd></div>
                <div class="mt-3 flex justify-between gap-4 border-t pt-3 app-border"><dt class="font-bold app-text">Số tiền</dt><dd class="font-extrabold text-brand-start">{{ number_format((int) $payment->amount, 0, ',', '.') }} {{ ($payment->currency ?: 'VND') === 'VND' ? 'VNĐ' : $payment->currency }}</dd></div>
            </dl>

            <p class="mx-auto mt-5 max-w-xl text-xs leading-relaxed app-muted">
                Trạng thái trên lấy từ cơ sở dữ liệu MovieMate. Dữ liệu trình duyệt
                {{ $integrityVerified ? 'có checksum hợp lệ' : 'không được xác thực' }}
                và không thể tự đánh dấu giao dịch là đã thanh toán.
            </p>

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING, \App\Models\Payment::STATUS_REVIEW], true))
                <div class="mx-auto mt-5 max-w-xl rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4 text-left text-sm leading-relaxed text-warning" role="note">
                    <strong>Không tạo lại thanh toán khi chưa rõ kết quả.</strong> Nếu {{ $providerLabel }} đã trừ tiền, hãy giữ mã đặt vé và chờ MovieMate xác minh hoặc đối soát giao dịch hiện tại.
                </div>
            @endif

            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                @if($isVerifiedPaid && $canViewTicket)
                    <a href="{{ route('user.bookings.ticket', $booking) }}" data-paid-ticket-link class="btn-primary">
                        <i class="ph-fill ph-ticket" aria-hidden="true"></i>
                        Mở đơn đặt vé
                    </a>
                @elseif($isVerifiedPaid)
                    <p class="app-muted">Liên kết mở đơn đặt vé an toàn được gửi riêng qua email và không được cấp bởi trang quay lại thanh toán.</p>
                @endif
                @if($canViewBooking)
                    <a href="{{ route('user.bookings.success', $booking) }}" class="btn-secondary">Xem chi tiết đơn đặt vé</a>
                @endif
            </div>
        </section>
    </div>
</main>
@endsection
@extends('layouts.user')

@section('title', 'Trạng thái thanh toán - MovieMate')

@section('content')
@php
    $providerLabel = match ($payment->provider) {
        'vnpay' => 'VNPAY',
        'payos' => 'payOS',
        default => 'ZaloPay',
    };
    $isVerifiedPaid = $payment->status === \App\Models\Payment::STATUS_SUCCESS
        && $payment->verified_at !== null
        && $booking->payment_status === 'paid'
        && $booking->booking_status === 'paid';
    $isCinemaCancelled = $booking->booking_status === 'cancelled' && $booking->showtimeCancellationImpact !== null;
    $stateStatus = $payment->status === \App\Models\Payment::STATUS_SUCCESS && ! $isVerifiedPaid
        ? \App\Models\Payment::STATUS_REVIEW
        : $payment->status;
    if ($booking->booking_status === 'pending_payment'
        && $stateStatus === \App\Models\Payment::STATUS_FAILED) {
        $stateStatus = \App\Models\Payment::STATUS_PENDING;
    }
    if ($booking->booking_status === 'expired'
        && $payment->failure_reason === 'vnpay_terminal_expired') {
        $stateStatus = \App\Models\Payment::STATUS_EXPIRED;
    }
    $states = [
        \App\Models\Payment::STATUS_PENDING => [
            'title' => 'Đang xác minh kết quả thanh toán',
            'message' => "MovieMate chưa nhận được kết quả cuối cùng từ {$providerLabel}. Hệ thống sẽ cập nhật đơn khi có kết quả chính thức.",
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_SUCCESS => [
            'title' => 'Đặt vé thành công',
            'message' => 'Thanh toán đã được xác minh. Đơn đặt vé và QR đơn đặt vé đã sẵn sàng để xuất trình tại quầy.',
            'icon' => 'ph-check-circle',
            'colour' => 'text-success',
        ],
        \App\Models\Payment::STATUS_FAILED => [
            'title' => 'Thanh toán không thành công',
            'message' => "{$providerLabel} đã trả về trạng thái không thành công. Đơn đặt vé này chưa đủ điều kiện nhận vé.",
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ],
        \App\Models\Payment::STATUS_REVIEW => [
            'title' => 'Giao dịch cần được hỗ trợ',
            'message' => 'Dữ liệu giao dịch cần bộ phận hỗ trợ kiểm tra thêm trước khi có kết luận chính thức.',
            'icon' => 'ph-magnifying-glass',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_EXPIRED => [
            'title' => 'Lần thanh toán đã hết hạn',
            'message' => 'Thời hạn thanh toán đã kết thúc. Đơn chưa đủ điều kiện phát hành QR đơn đặt vé.',
            'icon' => 'ph-clock-countdown',
            'colour' => 'text-slate-500',
        ],
    ];
    $states[\App\Models\Payment::STATUS_UNRESOLVED] = $states[\App\Models\Payment::STATUS_PENDING];
    $states[\App\Models\Payment::STATUS_PROCESSING] = $states[\App\Models\Payment::STATUS_PENDING];
    $isConfirmedCancellation = $booking->booking_status === 'cancelled'
        && $payment->status === \App\Models\Payment::STATUS_FAILED;
    if ($isConfirmedCancellation) {
        $states[\App\Models\Payment::STATUS_FAILED] = [
            'title' => 'Thanh toán đã được hủy',
            'message' => $payment->provider === 'vnpay' && $payment->failure_reason === 'vnpay_customer_cancelled'
                ? 'Bạn đã hủy giao dịch VNPAY. Các ghế đã giữ cho đơn này đã được giải phóng.'
                : 'Các ghế đã giữ cho đơn này đã được giải phóng.',
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ];
    }
    if (($cancelRequested ?? false) && ! $isConfirmedCancellation
        && in_array($payment->status, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true)) {
        $states[$stateStatus] = [
            'title' => 'Đang xác minh việc hủy thanh toán',
            'message' => 'Ghế của bạn vẫn được giữ tạm thời để tránh mất vé trong khi hệ thống xác minh trạng thái giao dịch.',
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ];
    }
    if ($isCinemaCancelled) {
        $stateStatus = 'showtime_cancelled';
        $states[$stateStatus] = [
            'title' => 'Suất chiếu đã bị rạp hủy',
            'message' => match($booking->refundCase?->status) {
                \App\Models\RefundCase::STATUS_REQUIRED => 'Thanh toán đã được xác minh. Cần xử lý hoàn tiền; đơn vẫn bị hủy và không phát hành QR hoặc vé sử dụng.',
                \App\Models\RefundCase::STATUS_RESOLVED => 'Thanh toán đã được giữ trong lịch sử và rạp đã ghi nhận hoàn tiền.',
                default => 'Bạn chưa có khoản thanh toán cần hoàn.',
            },
            'icon' => 'ph-calendar-x',
            'colour' => 'text-error',
        ];
    }
    $state = $states[$stateStatus] ?? [
        'title' => 'Đang xử lý trạng thái',
        'message' => 'MovieMate đang kiểm tra dữ liệu giao dịch hiện tại.',
        'icon' => 'ph-spinner-gap',
        'colour' => 'text-slate-500',
    ];
    $holdExpiresAt = $booking->expires_at ?? $payment->expires_at;
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

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true) && $holdExpiresAt)
                <div class="mx-auto mt-6 max-w-md rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4" data-countdown-wrapper>
                    <p class="text-xs font-bold uppercase tracking-wide text-warning">Thời gian lần thanh toán còn lại</p>
                    <p class="mt-1 text-3xl font-extrabold app-text" data-countdown="{{ $holdExpiresAt->toIso8601String() }}" data-expired-label="Đã hết thời gian">--:--</p>
                </div>
            @endif

            <dl class="mx-auto mt-7 max-w-xl rounded-2xl app-secondary p-5 text-left text-sm">
                @if($canViewBooking)
                    <div class="flex justify-between gap-4"><dt class="app-muted">Mã đặt vé</dt><dd class="break-all text-right font-mono font-bold text-brand-start">{{ $booking->booking_code }}</dd></div>
                @else
                    <div class="flex justify-between gap-4"><dt class="app-muted">Lần thanh toán</dt><dd class="text-right font-mono font-bold text-brand-start">#{{ $payment->id }}</dd></div>
                @endif
                <div class="mt-3 flex justify-between gap-4"><dt class="app-muted">Kênh thanh toán</dt><dd class="font-bold app-text">{{ $providerLabel }}</dd></div>
                <div class="mt-3 flex justify-between gap-4 border-t pt-3 app-border"><dt class="font-bold app-text">Số tiền</dt><dd class="font-extrabold text-brand-start">{{ number_format((int) $payment->amount, 0, ',', '.') }} {{ ($payment->currency ?: 'VND') === 'VND' ? 'VNĐ' : $payment->currency }}</dd></div>
            </dl>

            <p class="mx-auto mt-5 max-w-xl text-xs leading-relaxed app-muted">
                Trạng thái trên lấy từ cơ sở dữ liệu MovieMate. Dữ liệu trình duyệt
                {{ $integrityVerified ? 'có checksum hợp lệ' : 'không được xác thực' }}
                và không thể tự đánh dấu giao dịch là đã thanh toán.
            </p>

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING, \App\Models\Payment::STATUS_REVIEW], true))
                <div class="mx-auto mt-5 max-w-xl rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4 text-left text-sm leading-relaxed text-warning" role="note">
                    <strong>Không tạo lại thanh toán khi chưa rõ kết quả.</strong> Nếu {{ $providerLabel }} đã trừ tiền, hãy giữ mã đặt vé và chờ MovieMate xác minh hoặc đối soát giao dịch hiện tại.
                </div>
            @endif

            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                @if($isVerifiedPaid && $canViewTicket)
                    <a href="{{ route('user.bookings.ticket', $booking) }}" data-paid-ticket-link class="btn-primary">
                        <i class="ph-fill ph-ticket" aria-hidden="true"></i>
                        Mở đơn đặt vé
                    </a>
                @elseif($isVerifiedPaid)
                    <p class="app-muted">Liên kết mở đơn đặt vé an toàn được gửi riêng qua email và không được cấp bởi trang quay lại thanh toán.</p>
                @endif
                @if($canViewBooking)
                    <a href="{{ route('user.bookings.success', $booking) }}" class="btn-secondary">Xem chi tiết đơn đặt vé</a>
                @endif
            </div>
        </section>
    </div>
</main>
@endsection
@extends('layouts.user')

@section('title', 'Trạng thái thanh toán - MovieMate')

@section('content')
@php
    $providerLabel = match ($payment->provider) {
        'vnpay' => 'VNPAY',
        'payos' => 'payOS',
        default => 'ZaloPay',
    };
    $isVerifiedPaid = $payment->status === \App\Models\Payment::STATUS_SUCCESS
        && $payment->verified_at !== null
        && $booking->payment_status === 'paid'
        && $booking->booking_status === 'paid';
    $isCinemaCancelled = $booking->booking_status === 'cancelled' && $booking->showtimeCancellationImpact !== null;
    $stateStatus = $payment->status === \App\Models\Payment::STATUS_SUCCESS && ! $isVerifiedPaid
        ? \App\Models\Payment::STATUS_REVIEW
        : $payment->status;
    if ($booking->booking_status === 'pending_payment'
        && $stateStatus === \App\Models\Payment::STATUS_FAILED) {
        $stateStatus = \App\Models\Payment::STATUS_PENDING;
    }
    if ($booking->booking_status === 'expired'
        && $payment->failure_reason === 'vnpay_terminal_expired') {
        $stateStatus = \App\Models\Payment::STATUS_EXPIRED;
    }
    $states = [
        \App\Models\Payment::STATUS_PENDING => [
            'title' => 'Đang xác minh kết quả thanh toán',
            'message' => "MovieMate chưa nhận được kết quả cuối cùng từ {$providerLabel}. Hệ thống sẽ cập nhật đơn khi có kết quả chính thức.",
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_SUCCESS => [
            'title' => 'Đặt vé thành công',
            'message' => 'Thanh toán đã được xác minh. Đơn đặt vé và QR đơn đặt vé đã sẵn sàng để xuất trình tại quầy.',
            'icon' => 'ph-check-circle',
            'colour' => 'text-success',
        ],
        \App\Models\Payment::STATUS_FAILED => [
            'title' => 'Thanh toán không thành công',
            'message' => "{$providerLabel} đã trả về trạng thái không thành công. Đơn đặt vé này chưa đủ điều kiện nhận vé.",
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ],
        \App\Models\Payment::STATUS_REVIEW => [
            'title' => 'Giao dịch cần được hỗ trợ',
            'message' => 'Dữ liệu giao dịch cần bộ phận hỗ trợ kiểm tra thêm trước khi có kết luận chính thức.',
            'icon' => 'ph-magnifying-glass',
            'colour' => 'text-warning',
        ],
        \App\Models\Payment::STATUS_EXPIRED => [
            'title' => 'Lần thanh toán đã hết hạn',
            'message' => 'Thời hạn thanh toán đã kết thúc. Đơn chưa đủ điều kiện phát hành QR đơn đặt vé.',
            'icon' => 'ph-clock-countdown',
            'colour' => 'text-slate-500',
        ],
    ];
    $states[\App\Models\Payment::STATUS_UNRESOLVED] = $states[\App\Models\Payment::STATUS_PENDING];
    $states[\App\Models\Payment::STATUS_PROCESSING] = $states[\App\Models\Payment::STATUS_PENDING];
    $isConfirmedCancellation = $booking->booking_status === 'cancelled'
        && $payment->status === \App\Models\Payment::STATUS_FAILED;
    if ($isConfirmedCancellation) {
        $states[\App\Models\Payment::STATUS_FAILED] = [
            'title' => 'Thanh toán đã được hủy',
            'message' => $payment->provider === 'vnpay' && $payment->failure_reason === 'vnpay_customer_cancelled'
                ? 'Bạn đã hủy giao dịch VNPAY. Các ghế đã giữ cho đơn này đã được giải phóng.'
                : 'Các ghế đã giữ cho đơn này đã được giải phóng.',
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
        ];
    }
    if (($cancelRequested ?? false) && ! $isConfirmedCancellation
        && in_array($payment->status, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true)) {
        $states[$stateStatus] = [
            'title' => 'Đang xác minh việc hủy thanh toán',
            'message' => 'Ghế của bạn vẫn được giữ tạm thời để tránh mất vé trong khi hệ thống xác minh trạng thái giao dịch.',
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
        ];
    }
    if ($isCinemaCancelled) {
        $stateStatus = 'showtime_cancelled';
        $states[$stateStatus] = [
            'title' => 'Suất chiếu đã bị rạp hủy',
            'message' => match($booking->refundCase?->status) {
                \App\Models\RefundCase::STATUS_REQUIRED => 'Thanh toán đã được xác minh. Cần xử lý hoàn tiền; đơn vẫn bị hủy và không phát hành QR hoặc vé sử dụng.',
                \App\Models\RefundCase::STATUS_RESOLVED => 'Thanh toán đã được giữ trong lịch sử và rạp đã ghi nhận hoàn tiền.',
                default => 'Bạn chưa có khoản thanh toán cần hoàn.',
            },
            'icon' => 'ph-calendar-x',
            'colour' => 'text-error',
        ];
    }
    $state = $states[$stateStatus] ?? [
        'title' => 'Đang xử lý trạng thái',
        'message' => 'MovieMate đang kiểm tra dữ liệu giao dịch hiện tại.',
        'icon' => 'ph-spinner-gap',
        'colour' => 'text-slate-500',
    ];
    $holdExpiresAt = $booking->expires_at ?? $payment->expires_at;
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

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING], true) && $holdExpiresAt)
                <div class="mx-auto mt-6 max-w-md rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4" data-countdown-wrapper>
                    <p class="text-xs font-bold uppercase tracking-wide text-warning">Thời gian lần thanh toán còn lại</p>
                    <p class="mt-1 text-3xl font-extrabold app-text" data-countdown="{{ $holdExpiresAt->toIso8601String() }}" data-expired-label="Đã hết thời gian">--:--</p>
                </div>
            @endif

            <dl class="mx-auto mt-7 max-w-xl rounded-2xl app-secondary p-5 text-left text-sm">
                @if($canViewBooking)
                    <div class="flex justify-between gap-4"><dt class="app-muted">Mã đặt vé</dt><dd class="break-all text-right font-mono font-bold text-brand-start">{{ $booking->booking_code }}</dd></div>
                @else
                    <div class="flex justify-between gap-4"><dt class="app-muted">Lần thanh toán</dt><dd class="text-right font-mono font-bold text-brand-start">#{{ $payment->id }}</dd></div>
                @endif
                <div class="mt-3 flex justify-between gap-4"><dt class="app-muted">Kênh thanh toán</dt><dd class="font-bold app-text">{{ $providerLabel }}</dd></div>
                <div class="mt-3 flex justify-between gap-4 border-t pt-3 app-border"><dt class="font-bold app-text">Số tiền</dt><dd class="font-extrabold text-brand-start">{{ number_format((int) $payment->amount, 0, ',', '.') }} {{ ($payment->currency ?: 'VND') === 'VND' ? 'VNĐ' : $payment->currency }}</dd></div>
            </dl>

            <p class="mx-auto mt-5 max-w-xl text-xs leading-relaxed app-muted">
                Trạng thái trên lấy từ cơ sở dữ liệu MovieMate. Dữ liệu trình duyệt
                {{ $integrityVerified ? 'có checksum hợp lệ' : 'không được xác thực' }}
                và không thể tự đánh dấu giao dịch là đã thanh toán.
            </p>

            @if(in_array($stateStatus, [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_UNRESOLVED, \App\Models\Payment::STATUS_PROCESSING, \App\Models\Payment::STATUS_REVIEW], true))
                <div class="mx-auto mt-5 max-w-xl rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4 text-left text-sm leading-relaxed text-warning" role="note">
                    <strong>Không tạo lại thanh toán khi chưa rõ kết quả.</strong> Nếu {{ $providerLabel }} đã trừ tiền, hãy giữ mã đặt vé và chờ MovieMate xác minh hoặc đối soát giao dịch hiện tại.
                </div>
            @endif

            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                @if($isVerifiedPaid && $canViewTicket)
                    <a href="{{ route('user.bookings.ticket', $booking) }}" data-paid-ticket-link class="btn-primary">
                        <i class="ph-fill ph-ticket" aria-hidden="true"></i>
                        Mở đơn đặt vé
                    </a>
                @elseif($isVerifiedPaid)
                    <p class="app-muted">Liên kết mở đơn đặt vé an toàn được gửi riêng qua email và không được cấp bởi trang quay lại thanh toán.</p>
                @endif
                @if($canViewBooking)
                    <a href="{{ route('user.bookings.success', $booking) }}" class="btn-secondary">Xem chi tiết đơn đặt vé</a>
                @endif
            </div>
        </section>
    </div>
</main>
@endsection
