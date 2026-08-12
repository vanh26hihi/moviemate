<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title>In toàn bộ {{ $booking->booking_code }} - MovieMate</title>
    @vite(['resources/css/app.css'])
    <style>
        *{box-sizing:border-box}body{margin:0;background:#eef1f5;color:#111;font-family:Arial,sans-serif}.document{width:80mm;min-height:120mm;margin:16px auto;background:#fff;padding:5mm;font-size:11px;page-break-after:always}.document:last-child{page-break-after:auto}.brand{text-align:center;font-weight:900;font-size:20px}.title{text-align:center;font-size:13px;letter-spacing:1.5px}.code{text-align:center;font-family:monospace;font-weight:800;overflow-wrap:anywhere}.line{display:flex;justify-content:space-between;gap:3mm;padding:1.5mm 0;border-bottom:1px dashed #bbb}.seat{font-size:30px}.controls{max-width:80mm;margin:16px auto;text-align:center}@page{size:80mm auto;margin:0}@media print{body{background:#fff}.document{margin:0}.controls{display:none}}
    </style>
</head>
<body>
@foreach($booking->admissionTickets as $ticket)
    <article class="document" data-print-artifact="ticket" data-ticket-code="{{ $ticket->ticket_code }}">
        <header><div class="brand">MOVIEMATE</div><h1 class="title">VÉ VÀO PHÒNG CHIẾU PHIM</h1></header>
        <p class="code">{{ $ticket->ticket_code }}</p>
        <div class="line"><span>Mã đơn</span><strong>{{ $booking->booking_code }}</strong></div>
        <div class="line"><span>Phim</span><strong>{{ $booking->showtime?->movie?->title }}</strong></div>
        <div class="line"><span>Rạp</span><strong>{{ $booking->showtime?->cinema?->name }}</strong></div>
        <div class="line"><span>Suất</span><strong>{{ $booking->showtime_label }}</strong></div>
        <div class="line"><span>Phòng</span><strong>{{ $booking->showtime?->room?->name }}</strong></div>
        <div class="line"><span>Ghế</span><strong class="seat">{{ $ticket->seat_code }}</strong></div>
        <div class="line"><span>Ngày giờ in</span><strong>{{ $ticket->last_printed_at?->format('d/m/Y H:i') }}</strong></div>
    </article>
@endforeach
@if($booking->foodPickupVoucher)
    <article class="document" data-print-artifact="food-voucher">
        <header><div class="brand">MOVIEMATE</div><h1 class="title">PHIẾU NHẬN ĐỒ ĂN</h1></header>
        <p class="code">{{ $booking->foodPickupVoucher->voucher_code }}</p>
        <div class="line"><span>Mã đơn</span><strong>{{ $booking->booking_code }}</strong></div>
        <div class="line"><span>Rạp</span><strong>{{ $booking->showtime?->cinema?->name }}</strong></div>
        @foreach($booking->foodOrder->items as $item)<div class="line"><span>{{ $item->snapshot_name }}</span><strong>× {{ $item->quantity }}</strong></div>@endforeach
        <p>Vui lòng giao phiếu này tại quầy đồ ăn.</p>
        <div class="line"><span>Ngày giờ in</span><strong>{{ $booking->foodPickupVoucher->last_printed_at?->format('d/m/Y H:i') }}</strong></div>
    </article>
@endif
<div class="controls"><button type="button" onclick="window.print()">In toàn bộ</button></div>
<script>window.setTimeout(() => window.print(), 250);</script>
</body>
</html>
