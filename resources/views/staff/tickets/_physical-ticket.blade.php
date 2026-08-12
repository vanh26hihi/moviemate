@php
    $cinema = $booking->showtime?->cinema;
    $movie = $booking->showtime?->movie;
    $showtime = $booking->showtime;
    $seatType = $ticket->bookingSeat?->seat_type_snapshot ?: $ticket->bookingSeat?->seat?->type;
    $seatTypeLabel = \App\Support\StatusLabel::for('seat_type', (string) $seatType);
    $printTimestamp = $printedAt ?? now($cinema?->timezone ?: config('app.timezone'));
@endphp
<article class="paper paper--ticket" data-print-artifact="ticket" data-ticket-code="{{ $ticket->ticket_code }}" data-allocated-amount="{{ $allocatedAmount }}">
    <header>
        <div class="paper-brand">MOVIEMATE</div>
        <h1 class="paper-title">VÉ VÀO PHÒNG CHIẾU PHIM</h1>
        <p class="paper-subtitle">CINEMA TICKET</p>
    </header>
    <hr class="paper-rule">
    <h2 class="paper-movie">{{ $movie?->title }}</h2>
    @if(filled($movie?->age_rating))<p class="paper-code">PHÂN LOẠI: {{ $movie->age_rating }}</p>@endif
    <hr class="paper-rule">
    <dl class="paper-facts">
        <div class="paper-fact"><dt>Mã đơn</dt><dd>{{ $booking->booking_code }}</dd></div>
        <div class="paper-fact"><dt>Mã vé</dt><dd>{{ $ticket->ticket_code }}</dd></div>
        <div class="paper-fact"><dt>Rạp</dt><dd>{{ $cinema?->name }}</dd></div>
        @if(filled($cinema?->address))<div class="paper-fact"><dt>Địa chỉ</dt><dd>{{ $cinema->address }}</dd></div>@endif
        <div class="paper-fact"><dt>Ngày</dt><dd>{{ $showtime?->show_date?->format('d/m/Y') }}</dd></div>
        <div class="paper-fact"><dt>Giờ</dt><dd>{{ \Carbon\CarbonImmutable::parse($showtime?->show_time)->format('H:i') }}</dd></div>
        <div class="paper-fact"><dt>Phòng</dt><dd>{{ $showtime?->room?->name }}</dd></div>
    </dl>
    <hr class="paper-rule">
    <div class="paper-seat">
        <div class="paper-seat-box"><span>Ghế</span><strong>{{ $ticket->seat_code }}</strong></div>
        <div class="paper-seat-box"><span>Loại ghế</span><strong style="font-size:14px;line-height:1.35">{{ $seatTypeLabel }}</strong></div>
    </div>
    <hr class="paper-rule">
    <div class="paper-price"><span>Giá vé thực trả</span><strong>{{ number_format($allocatedAmount, 0, ',', '.') }} VNĐ</strong></div>
    <footer class="paper-footer">In lúc {{ $printTimestamp->format('d/m/Y H:i') }}<br>Vui lòng giữ vé và giao phần vé được yêu cầu tại cửa phòng chiếu.</footer>
</article>
