@extends('layouts.user')

@php
    $paymentState = $booking->payment?->status;
    $isUsed = $booking->booking_status === 'used';
    $isCancelled = $booking->booking_status === 'cancelled';
    $isExpired = $booking->booking_status === 'expired' || $paymentState === \App\Models\Payment::STATUS_EXPIRED;
    $isPaid = $isUsable;
    $isReview = ! $isPaid && ! $isUsed && ! $isCancelled && ! $isExpired
        && $paymentState === \App\Models\Payment::STATUS_REVIEW;
    $isFailed = ! $isPaid && ! $isUsed && ! $isCancelled && ! $isExpired && ! $isReview
        && $paymentState === \App\Models\Payment::STATUS_FAILED;
    $isPending = ! $isPaid && ! $isUsed && ! $isCancelled && ! $isExpired && ! $isReview && ! $isFailed
        && $booking->booking_status === 'pending_payment';

    $stateKey = match (true) {
        $isPaid => 'paid',
        $isUsed => 'used',
        $isCancelled => 'cancelled',
        $isExpired => 'expired',
        $isReview => 'review',
        $isFailed => 'failed',
        default => 'pending',
    };
    $states = [
        'paid' => [
            'title' => 'Đặt vé thành công',
            'message' => 'Thanh toán đã được xác minh và vé điện tử đã sẵn sàng. Bạn có thể mở vé và lưu mã QR để sử dụng tại rạp.',
            'icon' => 'ph-check-circle',
            'colour' => 'text-success',
            'badge' => 'Đã thanh toán',
        ],
        'pending' => [
            'title' => 'Đang xác minh kết quả thanh toán',
            'message' => 'Kết quả thanh toán đang được xác minh. Ghế sẽ được xử lý sau khi hệ thống nhận được kết quả chính thức.',
            'icon' => 'ph-hourglass-medium',
            'colour' => 'text-warning',
            'badge' => 'Đang xác minh',
        ],
        'review' => [
            'title' => 'Giao dịch cần được hỗ trợ',
            'message' => 'Giao dịch này cần bộ phận hỗ trợ kiểm tra thêm trước khi phát hành vé. Bạn không cần thao tác gì thêm; MovieMate sẽ cập nhật khi có kết quả chính thức.',
            'icon' => 'ph-magnifying-glass',
            'colour' => 'text-warning',
            'badge' => 'Cần hỗ trợ',
        ],
        'failed' => [
            'title' => 'Thanh toán không thành công',
            'message' => 'Giao dịch không thành công nên đơn đặt vé này không có vé điện tử. Bạn có thể chọn lại ghế và thanh toán lần khác.',
            'icon' => 'ph-x-circle',
            'colour' => 'text-error',
            'badge' => 'Không thành công',
        ],
        'expired' => [
            'title' => 'Đơn đặt vé đã hết hạn',
            'message' => 'Thời gian giữ ghế đã kết thúc và ghế đã được giải phóng. Nếu bạn vừa thanh toán, hệ thống sẽ kiểm tra và liên hệ hỗ trợ thay vì tự phát hành vé.',
            'icon' => 'ph-clock-countdown',
            'colour' => 'text-slate-400',
            'badge' => 'Hết hạn',
        ],
        'cancelled' => [
            'title' => 'Đơn đặt vé đã bị hủy',
            'message' => 'Đơn đặt vé này đã được hủy và không còn giữ ghế. Không có mã QR hoặc vé để tải xuống.',
            'icon' => 'ph-prohibit',
            'colour' => 'text-error',
            'badge' => 'Đã hủy',
        ],
        'used' => [
            'title' => 'Vé đã được sử dụng',
            'message' => 'Vé đã được soát tại rạp và chỉ còn giá trị tra cứu lịch sử. Mã QR không còn khả dụng.',
            'icon' => 'ph-checks',
            'colour' => 'text-ai-start',
            'badge' => 'Đã sử dụng',
        ],
    ];
    $state = $states[$stateKey];
    $currency = ($booking->currency ?: 'VND') === 'VND' ? 'VNĐ' : $booking->currency;
    $foodItems = $booking->foodOrder?->items ?? collect();
    $seatTypeLabels = ['normal' => 'Thường', 'vip' => 'VIP', 'couple' => 'Ghế đôi'];
    $delivery = $booking->ticketDelivery;
    $deliveryState = $delivery?->status ?? 'missing';
    $deliveryLabels = [
        'pending' => ['label' => 'Đang chờ gửi', 'message' => 'Yêu cầu đã nằm trong hàng đợi gửi vé.'],
        'processing' => ['label' => 'Đang gửi', 'message' => 'Hệ thống đang chuyển vé tới mail transport.'],
        'sent' => ['label' => 'Đã gửi', 'message' => 'Mail transport đã tiếp nhận thư. Vui lòng kiểm tra cả thư rác.'],
        'failed' => ['label' => 'Sẽ thử lại', 'message' => 'Lần gửi gần nhất chưa thành công và được giữ lại để thử lại an toàn.'],
        'missing' => ['label' => 'Chưa xếp hàng', 'message' => 'Chưa ghi nhận trạng thái gửi email vé.'],
    ];
    $deliveryCopy = $deliveryLabels[$deliveryState] ?? $deliveryLabels['missing'];
    if ($deliveryState === 'sent' && ! $mailDeliveryReady) {
        $deliveryCopy = [
            'label' => 'Cấu hình gửi thư chưa sẵn sàng',
            'message' => 'Hệ thống từng ghi nhận lần gửi, nhưng cấu hình hiện tại không thể giao thư thật. Hãy cấu hình mail rồi yêu cầu gửi lại.',
        ];
    }
    $canRequestEmail = ! auth()->check() || $booking->user_id === auth()->id();
    $myTicketsUrl = auth()->check()
        ? route('user.bookings.history')
        : route('user.bookings.ticket', $booking);
@endphp

@section('title', $state['title'].' - MovieMate')

@section('content')
<main class="user-page-shell relative min-h-[80vh] overflow-hidden bg-[radial-gradient(circle_at_20%_15%,rgba(255,61,87,0.2),transparent_35%),radial-gradient(circle_at_80%_85%,rgba(108,43,217,0.18),transparent_40%)] px-4 py-8 sm:px-6 lg:px-8">
    <div class="absolute inset-0 bg-dark-main/75 backdrop-blur-sm" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-4xl">
        <x-checkout-progress current="payment" class="mb-8" />

        <section class="cinema-card animate-[fade-in-up_0.5s_ease-out] rounded-3xl p-5 shadow-2xl shadow-brand-start/20 backdrop-blur-md sm:p-8" data-booking-state="{{ $stateKey }}" aria-labelledby="booking-state-title">
            <header class="text-center">
                <span class="mx-auto inline-flex h-20 w-20 items-center justify-center rounded-full bg-current/10 {{ $state['colour'] }}" aria-hidden="true">
                    <i class="ph-bold {{ $state['icon'] }} text-5xl"></i>
                </span>
                <p class="mt-5 text-xs font-extrabold uppercase tracking-[0.2em] {{ $state['colour'] }}">{{ $state['badge'] }}</p>
                <h1 id="booking-state-title" class="mt-2 text-2xl font-extrabold app-text sm:text-3xl">{{ $state['title'] }}</h1>
                <p class="mx-auto mt-3 max-w-2xl leading-relaxed app-muted">{{ $state['message'] }}</p>
            </header>

            @if($isPaid)
                <section class="mt-6 rounded-2xl border border-brand-start/25 bg-brand-start/5 p-4 sm:p-5" aria-labelledby="paid-ticket-actions-title">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 id="paid-ticket-actions-title" class="font-extrabold app-text">Vé của bạn đã sẵn sàng</h2>
                            <p class="mt-1 text-sm app-muted">Mở vé, in trực tiếp hoặc lưu PDF ngay từ trình duyệt.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('user.bookings.ticket', $booking) }}" class="btn-primary" data-paid-ticket-link>
                                <i class="ph-fill ph-ticket" aria-hidden="true"></i> Xem vé
                            </a>
                            <a href="{{ $myTicketsUrl }}" class="btn-secondary">
                                <i class="ph-bold ph-ticket" aria-hidden="true"></i> Về vé của tôi
                            </a>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 border-t pt-4 app-border sm:grid-cols-[1fr_auto] sm:items-center">
                        <div class="min-w-0 text-sm">
                            <p class="font-bold app-text">Email vé: {{ $deliveryCopy['label'] }}</p>
                            <p class="mt-1 break-all app-muted">{{ $booking->recipient_email }}</p>
                            <p class="mt-1 app-muted">{{ $deliveryCopy['message'] }}</p>
                            @if($delivery?->sent_at)
                                <p class="mt-1 text-xs app-muted">Gửi lúc {{ $delivery->sent_at->format('d/m/Y H:i') }}</p>
                            @endif
                        </div>
                        @if($canRequestEmail)
                            <form method="POST" action="{{ route('user.bookings.ticket-email.resend', $booking) }}" data-submit-once>
                                @csrf
                                <button type="submit" class="btn-secondary w-full" data-loading-label="Đang ghi nhận…">
                                    <i class="ph-bold ph-envelope-simple" aria-hidden="true"></i>
                                    Gửi lại email vé
                                </button>
                                <p class="mt-1 text-center text-xs app-muted" data-submit-status aria-live="polite"></p>
                            </form>
                        @endif
                    </div>
                </section>
            @endif

            @if(($isPending || $isReview) && $booking->expires_at)
                <div class="mx-auto mt-6 max-w-xl rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4 text-center" data-countdown-wrapper>
                    <p class="text-xs font-bold uppercase tracking-wide text-warning">Thời gian giữ ghế còn lại</p>
                    <p class="mt-1 text-3xl font-extrabold app-text" data-countdown="{{ $booking->expires_at->toIso8601String() }}" data-expired-label="Đã hết thời gian">--:--</p>
                    <p class="mt-2 text-xs leading-relaxed app-muted">Đừng tạo thêm đơn đặt vé hoặc yêu cầu thanh toán mới khi giao dịch hiện tại chưa có kết quả rõ ràng.</p>
                </div>
            @endif

            <div class="mt-8 grid gap-5 lg:grid-cols-[1.1fr_0.9fr]">
                <section class="rounded-2xl border app-border p-5 sm:p-6" aria-labelledby="booking-details-title">
                    <div class="flex flex-wrap items-start justify-between gap-4 border-b pb-4 app-border">
                        <div>
                            <p class="text-xs app-muted">Mã đặt vé</p>
                            <h2 id="booking-details-title" class="mt-1 break-all font-mono text-xl font-bold text-brand-start">{{ $booking->booking_code }}</h2>
                        </div>
                        <span class="rounded-full border app-border px-3 py-1 text-xs font-bold {{ $state['colour'] }}">{{ $state['badge'] }}</span>
                    </div>

                    <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                        <div><dt class="app-muted">Phim</dt><dd class="mt-1 font-semibold app-text">{{ $booking->showtime->movie->title }}</dd></div>
                        <div><dt class="app-muted">Ngày giờ</dt><dd class="mt-1 font-semibold app-text">{{ $booking->showtime_label }}</dd></div>
                        <div><dt class="app-muted">Rạp</dt><dd class="mt-1 font-semibold app-text">{{ $booking->showtime->cinema->name }}</dd></div>
                        <div><dt class="app-muted">Phòng</dt><dd class="mt-1 font-semibold app-text">{{ $booking->showtime->room->name }}</dd></div>
                    </dl>

                    <div class="mt-5 border-t pt-4 app-border">
                        <h3 class="text-sm font-bold app-text">Ghế</h3>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach($booking->seat_display_groups as $seatGroup)
                                <span class="rounded-xl app-secondary px-3 py-2 text-sm font-bold app-text">
                                    {{ $seatGroup['label'] }}
                                    @unless($seatGroup['is_couple'])
                                        <span class="ml-1 font-normal app-muted">· {{ $seatTypeLabels[$seatGroup['type']] ?? \App\Support\StatusLabel::for('seat_type', $seatGroup['type']) }}</span>
                                    @endunless
                                </span>
                            @endforeach
                        </div>
                    </div>
                </section>

                <aside class="rounded-2xl app-secondary p-5 sm:p-6" aria-labelledby="order-total-title">
                    <h2 id="order-total-title" class="font-bold app-text">Chi tiết đơn</h2>

                    <div class="mt-4 space-y-3 text-sm">
                        @forelse($foodItems as $item)
                            <div class="flex items-start justify-between gap-3">
                                <span class="app-text">{{ $item->snapshot_name }} × {{ $item->quantity }}</span>
                                <strong class="whitespace-nowrap app-text">{{ number_format((int) $item->line_total, 0, ',', '.') }} {{ $currency }}</strong>
                            </div>
                        @empty
                            <p class="app-muted">Không có đồ ăn trong đơn.</p>
                        @endforelse
                    </div>

                    <dl class="mt-5 space-y-3 border-t pt-4 text-sm app-border">
                        <div class="flex justify-between gap-3"><dt class="app-muted">Tiền ghế</dt><dd class="font-semibold app-text">{{ number_format((int) $booking->seat_subtotal, 0, ',', '.') }} {{ $currency }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="app-muted">Tiền đồ ăn</dt><dd class="font-semibold app-text">{{ number_format((int) $booking->food_subtotal, 0, ',', '.') }} {{ $currency }}</dd></div>
                        <div class="flex justify-between gap-3 border-t pt-3 app-border"><dt class="font-bold app-text">Tổng cộng</dt><dd class="text-xl font-extrabold text-brand-start">{{ number_format((int) $booking->total_amount, 0, ',', '.') }} {{ $currency }}</dd></div>
                    </dl>
                    <div class="mt-5 rounded-xl border app-border px-4 py-3">
                        <p class="text-xs app-muted">Email nhận vé</p>
                        <p class="mt-1 break-all text-sm font-bold app-text">{{ $booking->recipient_email }}</p>
                    </div>
                </aside>
            </div>

            @if($isPending)
                <div class="mt-6 rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4 text-sm leading-relaxed text-warning" role="note">
                    <strong>Vui lòng không thanh toán lại:</strong> nếu bạn vừa thanh toán hoặc đã bị trừ tiền, hãy chờ hệ thống xác minh. Nút bên dưới chỉ kiểm tra lại lần thanh toán hiện tại theo dữ liệu máy chủ, không tạo giao dịch mới.
                </div>
            @endif

            <div class="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                @if($isPaid)
                    <a href="{{ route('user.bookings.ticket', $booking) }}" class="btn-primary" data-paid-ticket-link>
                        <i class="ph-fill ph-ticket" aria-hidden="true"></i>
                        Xem vé
                    </a>
                @elseif($isPending)
                    <form method="POST" action="{{ route('payments.zalopay.initiate', $booking) }}" data-submit-once>
                        @csrf
                        <button type="submit" class="btn-primary w-full" data-loading-label="Đang kiểm tra lần thanh toán…">Kiểm tra / tiếp tục lần hiện tại</button>
                        <p class="mt-2 text-center text-sm app-muted" data-submit-status aria-live="polite"></p>
                    </form>
                @endif
                <a href="{{ route('home') }}" class="btn-secondary">Về trang chủ</a>
            </div>
        </section>
    </div>
</main>
@endsection
