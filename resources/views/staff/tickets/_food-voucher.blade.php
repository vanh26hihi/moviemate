@php
    $cinema = $booking->showtime?->cinema;
    $showtime = $booking->showtime;
    $printTimestamp = $printedAt ?? now($cinema?->timezone ?: config('app.timezone'));
@endphp
<article class="paper paper--food" data-print-artifact="food-voucher" data-voucher-code="{{ $voucher->voucher_code }}" data-allocated-amount="{{ $allocatedAmount }}">
    <header>
        <div class="paper-brand">MOVIEMATE</div>
        <h1 class="paper-title">PHIẾU NHẬN ĐỒ ĂN</h1>
        <p class="paper-subtitle">FOOD PICKUP VOUCHER</p>
    </header>
    <hr class="paper-rule">
    <dl class="paper-facts">
        <div class="paper-fact"><dt>Mã đơn</dt><dd>{{ $booking->booking_code }}</dd></div>
        <div class="paper-fact"><dt>Mã phiếu</dt><dd>{{ $voucher->voucher_code }}</dd></div>
        <div class="paper-fact"><dt>Rạp</dt><dd>{{ $cinema?->name }}</dd></div>
        <div class="paper-fact"><dt>Phim</dt><dd>{{ $showtime?->movie?->title }}</dd></div>
        <div class="paper-fact"><dt>Suất chiếu</dt><dd>{{ $booking->showtime_label }}</dd></div>
    </dl>
    <hr class="paper-rule">
    <ul class="paper-items" aria-label="Đồ ăn trong đơn">
        @foreach($booking->foodOrder->items as $item)
            <li class="paper-item"><span>{{ $item->snapshot_name }}</span><strong>× {{ $item->quantity }}</strong></li>
        @endforeach
    </ul>
    <hr class="paper-rule">
    <div class="paper-price"><span>Giá phần đồ ăn thực trả</span><strong>{{ number_format($allocatedAmount, 0, ',', '.') }} VNĐ</strong></div>
    <p class="paper-note">Vui lòng giao phiếu này tại quầy đồ ăn. Phiếu áp dụng cho toàn bộ món trong đơn.</p>
    <footer class="paper-footer">In lúc {{ $printTimestamp->format('d/m/Y H:i') }}</footer>
</article>
