<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title>Phiếu nhận đồ ăn {{ $voucher->voucher_code }} - MovieMate</title>
    @vite(['resources/css/app.css'])
    <style>
        body{margin:0;background:#eef1f5;color:#111;font-family:Arial,sans-serif}.voucher{width:80mm;margin:16px auto;background:#fff;padding:5mm;font-size:11px}.brand{text-align:center}.brand h1{font-size:18px}.code{text-align:center;font-family:monospace;font-weight:800}.line{display:flex;justify-content:space-between;gap:4mm;padding:2mm 0;border-bottom:1px dashed #bbb}.meta{display:grid;grid-template-columns:25mm 1fr;gap:2mm;margin-top:2mm}.meta strong{text-align:right}.controls{max-width:80mm;margin:16px auto;text-align:center}@page{size:80mm auto;margin:0}@media print{body{background:#fff}.voucher{margin:0}.controls{display:none}}
    </style>
</head>
<body>
<article class="voucher">
    <header class="brand"><strong>MOVIEMATE</strong><h1>PHIẾU NHẬN ĐỒ ĂN</h1></header>
    <p class="code">{{ $voucher->voucher_code }}</p>
    <div class="meta"><span>Đơn đặt vé</span><strong>{{ $booking->booking_code }}</strong></div>
    <div class="meta"><span>Chi nhánh</span><strong>{{ $booking->showtime?->cinema?->name }}</strong></div>
    <hr>
    @foreach($booking->foodOrder->items as $item)
        <div class="line"><span>{{ $item->snapshot_name }}</span><strong>× {{ $item->quantity }}</strong></div>
    @endforeach
    <p>Phiếu này dùng để nhận toàn bộ đồ ăn của đơn. Đây không phải vé xem phim và không dùng để vào phòng chiếu.</p>
    <div class="meta"><span>Lần in</span><strong>#{{ $voucher->print_count }}</strong></div>
    <div class="meta"><span>Nhân viên</span><strong>{{ $voucher->lastPrintedBy?->name }}</strong></div>
</article>
<div class="controls"><button type="button" onclick="window.print()">In phiếu</button></div>
</body>
</html>
