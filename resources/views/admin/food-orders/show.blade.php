@extends('layouts.admin')

@section('title', 'Chi tiết đơn đồ ăn thành công - MovieMate')
@section('page-title', 'Đơn đồ ăn')

@section('content')
@php
    $booking = $order->booking;
    $voucher = $booking?->foodPickupVoucher;
    $customerName = $booking?->user?->name ?: $order->customer_name ?: $booking?->customer_name ?: 'Khách đặt vé';
    $customerPhone = $order->customer_phone ?: $booking?->customer_phone;
    $customerEmail = $order->customer_email ?: $booking?->customer_email;
@endphp
<div class="space-y-6">
    <header class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div>
            <a href="{{ route('admin.food-orders.index') }}" class="text-sm font-bold text-brand-start">← Tất cả đơn đồ ăn</a>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-extrabold app-heading sm:text-3xl">{{ $booking?->booking_code ?? 'Đơn #'.$order->id }}</h1>
                <span class="status-badge bg-success/10 text-success"><i class="ph-fill ph-check-circle" aria-hidden="true"></i>{{ $order->status === 'completed' ? 'Đã hoàn thành' : 'Đã thanh toán' }}</span>
            </div>
            <p class="mt-2 app-muted">Đơn đồ ăn #{{ $order->id }} · Thanh toán lúc {{ $order->successful_paid_at ? \Carbon\Carbon::parse($order->successful_paid_at)->format('d/m/Y H:i') : '—' }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('tickets.lookup')<a class="btn-primary" href="{{ route('staff.tickets.operations', $booking) }}"><i class="ph ph-printer" aria-hidden="true"></i>Mở quầy in</a>@endcan
            @can('bookings.view')<a class="btn-secondary" href="{{ route('admin.bookings.show', $booking) }}">Xem booking</a>@endcan
        </div>
    </header>

    <section class="rounded-2xl border border-success/30 bg-success/5 p-4 sm:p-5" aria-label="Xác nhận điều kiện phục vụ">
        <div class="flex gap-3"><i class="ph-fill ph-shield-check mt-0.5 text-xl text-success" aria-hidden="true"></i><div><p class="font-extrabold app-text">Đơn đủ điều kiện phục vụ</p><p class="mt-1 text-sm app-muted">Đơn đặt vé đã thanh toán, giao dịch có bằng chứng xác minh/thu tiền và rạp nhận khớp chi nhánh của đơn. Trang này không cho phép tự đánh dấu thanh toán hoặc hoàn thành.</p></div></div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Thông tin chính">
        <article class="cinema-card p-5"><p class="text-sm app-muted">Tiền đồ ăn</p><p class="mt-2 text-2xl font-black app-text">{{ number_format((int) $order->total_amount, 0, ',', '.') }} VNĐ</p><p class="mt-1 text-xs text-success">Đã thanh toán</p></article>
        <article class="cinema-card p-5"><p class="text-sm app-muted">Rạp nhận</p><p class="mt-2 font-extrabold app-text">{{ $order->pickupCinema?->name ?? 'Không xác định' }}</p><p class="mt-1 text-xs app-muted">{{ $order->pickupCinema?->address ?? '—' }}</p></article>
        <article class="cinema-card p-5"><p class="text-sm app-muted">Khách hàng</p><p class="mt-2 font-extrabold app-text">{{ $customerName }}</p><p class="mt-1 text-xs app-muted">{{ \App\Support\PrivacyMask::phone($customerPhone) }} · {{ \App\Support\PrivacyMask::email($customerEmail) }}</p></article>
        <article class="cinema-card p-5"><p class="text-sm app-muted">Phiếu nhận đồ</p>@if(!$voucher)<p class="mt-2 font-extrabold text-danger">Thiếu phiếu</p><p class="mt-1 text-xs app-muted">Dừng in và kiểm tra booking.</p>@elseif($voucher->print_count === 0)<p class="mt-2 font-extrabold text-warning">Chưa in</p><p class="mt-1 break-all text-xs app-muted">{{ $voucher->voucher_code }}</p>@else<p class="mt-2 font-extrabold text-success">Đã in {{ $voucher->print_count }} lần</p><p class="mt-1 text-xs app-muted">Lần cuối {{ $voucher->last_printed_at?->format('d/m/Y H:i') ?? '—' }}@if($voucher->lastPrintedBy) · {{ $voucher->lastPrintedBy->name }}@endif</p>@endif</article>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(20rem,0.75fr)]">
        <section class="cinema-card overflow-hidden" aria-labelledby="food-items-title">
            <div class="border-b app-border px-5 py-4"><h2 id="food-items-title" class="font-extrabold app-text">Món khách đã mua</h2><p class="mt-1 text-sm app-muted">Tên và giá được lấy từ snapshot lúc đặt hàng, không thay đổi theo menu hiện tại.</p></div>
            <div class="divide-y divide-[var(--border-color)]">
                @foreach($order->items as $item)
                    @php
                        $unitPrice = (int) ($item->unit_price ?? $item->price);
                        $lineTotal = (int) ($item->line_total ?? $item->total);
                    @endphp
                    <article class="grid gap-3 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                        <div><p class="font-extrabold app-text">{{ $item->snapshot_name ?: $item->food?->name ?: 'Món không còn trong menu' }}</p><p class="mt-1 text-sm app-muted">{{ $item->quantity }} phần × {{ number_format($unitPrice, 0, ',', '.') }} VNĐ</p></div>
                        <p class="font-extrabold app-text sm:text-right">{{ number_format($lineTotal, 0, ',', '.') }} VNĐ</p>
                    </article>
                @endforeach
            </div>
            <div class="flex items-center justify-between border-t app-border bg-brand-start/5 px-5 py-4"><span class="font-bold app-text">Tổng đồ ăn</span><span class="text-xl font-black text-brand-start">{{ number_format((int) $order->total_amount, 0, ',', '.') }} VNĐ</span></div>
        </section>

        <div class="space-y-6">
            <section class="cinema-card p-5" aria-labelledby="payment-evidence-title">
                <div class="flex items-center justify-between gap-3"><h2 id="payment-evidence-title" class="font-extrabold app-text">Bằng chứng thanh toán</h2><span class="status-badge bg-success/10 text-success">Hợp lệ</span></div>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="app-muted">Phương thức</dt><dd class="text-right font-bold app-text">{{ \App\Support\PaymentPresentation::providerLabel($order->successful_provider) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="app-muted">Tổng booking đã thu</dt><dd class="text-right font-bold app-text">{{ number_format((int) $order->successful_payment_amount, 0, ',', '.') }} {{ $order->successful_payment_currency }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="app-muted">Xác minh / thu tiền</dt><dd class="text-right font-bold app-text">{{ $order->successful_paid_at ? \Carbon\Carbon::parse($order->successful_paid_at)->format('d/m/Y H:i') : '—' }}</dd></div>
                </dl>
                @can('payments.view')<a class="btn-secondary mt-5 w-full justify-center" href="{{ route('admin.payments.show', $order->successful_payment_id) }}">Xem giao dịch</a>@endcan
            </section>

            <section class="cinema-card p-5" aria-labelledby="showtime-context-title">
                <h2 id="showtime-context-title" class="font-extrabold app-text">Ngữ cảnh booking</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="app-muted">Phim</dt><dd class="mt-1 font-bold app-text">{{ $booking?->showtime?->movie?->title ?? 'Không còn dữ liệu phim' }}</dd></div>
                    <div class="grid grid-cols-2 gap-3"><div><dt class="app-muted">Suất chiếu</dt><dd class="mt-1 font-bold app-text">{{ $booking?->showtime?->show_date?->format('d/m/Y') ?? '—' }} · {{ $booking?->showtime?->show_time ? \Carbon\Carbon::parse($booking->showtime->show_time)->format('H:i') : '—' }}</dd></div><div><dt class="app-muted">Phòng</dt><dd class="mt-1 font-bold app-text">{{ $booking?->showtime?->room?->name ?? '—' }}</dd></div></div>
                    <div><dt class="app-muted">Kênh bán</dt><dd class="mt-1 font-bold app-text">{{ $booking?->sales_channel === 'counter' ? 'Tại quầy' : 'Online' }}</dd></div>
                </dl>
            </section>
        </div>
    </div>
</div>
@endsection
