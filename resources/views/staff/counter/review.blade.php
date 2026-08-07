@extends('layouts.staff')

@section('title', 'Chọn phương thức thanh toán - MovieMate')
@section('page-title', 'Xác nhận đơn tại quầy')

@section('content')
@php
    $authoritativePayment = $booking->payments->filter(fn($payment) => $payment->hasAuthoritativeSuccessEvidence())->sortByDesc('id')->first();
    $latestPayment = $booking->payments->sortByDesc('id')->first();
@endphp
<div class="mx-auto max-w-5xl space-y-6">
    <header>
        <p class="text-sm font-bold text-brand-start">{{ $booking->booking_code }}</p>
        <h1 class="mt-2 text-3xl font-extrabold app-heading">{{ $authoritativePayment ? 'Thanh toán tại quầy thành công' : 'Kiểm tra trước khi thanh toán' }}</h1>
    </header>

    <section class="cinema-card p-6">
        <h2 class="text-xl font-extrabold app-heading">Thông tin bán vé</h2>
        <dl class="mt-5 grid gap-4 sm:grid-cols-2">
            <div><dt class="text-sm app-muted">Chi nhánh</dt><dd class="font-bold">{{ $booking->showtime->cinema->name }}</dd></div>
            <div><dt class="text-sm app-muted">Kênh bán</dt><dd class="font-bold">Tại quầy</dd></div>
            <div><dt class="text-sm app-muted">Phim</dt><dd class="font-bold">{{ $booking->showtime->movie->title }}</dd></div>
            <div><dt class="text-sm app-muted">Suất chiếu</dt><dd class="font-bold">{{ $booking->showtime_label }}</dd></div>
            <div><dt class="text-sm app-muted">Phòng</dt><dd class="font-bold">{{ $booking->showtime->room->name }}</dd></div>
            <div><dt class="text-sm app-muted">Ghế</dt><dd class="font-bold">{{ $booking->seat_codes }}</dd></div>
            <div><dt class="text-sm app-muted">Nhân viên tạo đơn</dt><dd class="font-bold">{{ $booking->createdByStaff?->name ?? '—' }}</dd></div>
            <div><dt class="text-sm app-muted">Khách hàng</dt><dd class="font-bold">{{ $booking->customer_name ?: 'Khách vãng lai' }}</dd></div>
        </dl>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="cinema-card p-6">
            <h2 class="text-xl font-extrabold app-heading">Đồ ăn</h2>
            @forelse($booking->foodOrder?->items ?? [] as $item)
                <div class="mt-3 flex justify-between"><span>{{ $item->snapshot_name }} × {{ $item->quantity }}</span><strong>{{ number_format((int) $item->line_total, 0, ',', '.') }} VNĐ</strong></div>
            @empty
                <p class="mt-3 app-muted">Không kèm đồ ăn.</p>
            @endforelse
        </section>
        <section class="cinema-card p-6">
            <h2 class="text-xl font-extrabold app-heading">Tổng tiền trên hệ thống</h2>
            <dl class="mt-4 space-y-3">
                <div class="flex justify-between"><dt>Tiền vé</dt><dd class="font-bold">{{ number_format((int) $booking->seat_subtotal, 0, ',', '.') }} VNĐ</dd></div>
                <div class="flex justify-between"><dt>Đồ ăn</dt><dd class="font-bold">{{ number_format((int) $booking->food_subtotal, 0, ',', '.') }} VNĐ</dd></div>
                <div class="flex justify-between border-t app-border pt-3 text-xl"><dt class="font-extrabold">Tổng</dt><dd class="font-extrabold text-brand-start">{{ number_format((int) $booking->total_amount, 0, ',', '.') }} VNĐ</dd></div>
            </dl>
            <p class="mt-3 text-sm app-muted">Số tiền do máy chủ xác định và không thể sửa trên trình duyệt.</p>
        </section>
    </div>

    @if($authoritativePayment)
        <section class="cinema-card border border-success/40 p-6">
            <p class="font-extrabold text-success">Thanh toán thành công bằng {{ \App\Support\PaymentPresentation::providerLabel($authoritativePayment->provider) }}.</p>
            <a class="btn-primary mt-5" href="{{ route('staff.counter.payment-result', $booking) }}"><i class="ph ph-printer"></i>Tiếp tục in vé</a>
        </section>
    @elseif($latestPayment)
        <section class="cinema-card border border-warning/40 p-6">
            <p class="font-extrabold text-warning">Đơn đã có giao dịch {{ \App\Support\PaymentPresentation::providerLabel($latestPayment->provider) }} đang được bảo vệ.</p>
            <p class="mt-2 app-muted">Không tạo thêm lần thanh toán mới cho đến khi trạng thái hiện tại được xác minh.</p>
            <a class="btn-primary mt-5" href="{{ route('staff.counter.payment-result', $booking) }}">Xem hoặc tiếp tục giao dịch</a>
        </section>
    @else
        <section class="cinema-card p-6">
            <h2 class="text-xl font-extrabold app-heading">Phương thức thanh toán</h2>
            <p class="mt-2 app-muted">Chọn đúng phương thức khách sẽ sử dụng. VNPAY/payOS chỉ được ghi nhận sau khi provider xác minh.</p>
            <div class="mt-5 grid gap-4 md:grid-cols-3">
                @can('counter_sales.settle')
                    <form method="POST" action="{{ route('staff.counter.cash', $booking) }}" data-submit-once>@csrf
                        <button class="cinema-card h-full w-full border-2 border-transparent p-5 text-left hover:border-brand-start" type="submit">
                            <i class="ph ph-money text-3xl text-brand-start"></i><span class="mt-3 block text-lg font-extrabold">Tiền mặt</span><span class="mt-1 block text-sm app-muted">Nhân viên xác nhận đã thu đúng tổng tiền.</span>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('staff.counter.payments.initiate', [$booking, 'vnpay']) }}" data-submit-once>@csrf
                        <button class="cinema-card h-full w-full border-2 border-transparent p-5 text-left hover:border-brand-start disabled:cursor-not-allowed disabled:opacity-50" type="submit" @disabled(!$providerAvailability['vnpay'])>
                            <i class="ph ph-credit-card text-3xl text-brand-start"></i><span class="mt-3 block text-lg font-extrabold">VNPAY</span><span class="mt-1 block text-sm app-muted">Thanh toán trên cổng VNPAY.</span>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('staff.counter.payments.initiate', [$booking, 'payos']) }}" data-submit-once>@csrf
                        <button class="cinema-card h-full w-full border-2 border-transparent p-5 text-left hover:border-brand-start disabled:cursor-not-allowed disabled:opacity-50" type="submit" @disabled(!$providerAvailability['payos'])>
                            <i class="ph ph-qr-code text-3xl text-brand-start"></i><span class="mt-3 block text-lg font-extrabold">payOS / VietQR</span><span class="mt-1 block text-sm app-muted">Mở checkout/QR chính thức của payOS.</span>
                        </button>
                    </form>
                @endcan
            </div>
        </section>
        @can('counter_sales.cancel')
            <form method="POST" action="{{ route('staff.counter.cancel', $booking) }}" class="flex justify-end" data-submit-once>@csrf
                <button class="btn-secondary" type="submit">Hủy đơn và giải phóng ghế</button>
            </form>
        @endcan
    @endif
</div>
@endsection
