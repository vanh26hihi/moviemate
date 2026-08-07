<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="referrer" content="no-referrer">
    <title>In vé cứng {{ $booking->booking_code }} - MovieMate</title>
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/staff-ticket-print.js'])
    <style>
        body{background:#eef1f5;color:#111827;font-family:system-ui,sans-serif}.hard-ticket{max-width:760px;margin:24px auto;background:#fff;border:2px solid #111827;border-radius:18px;padding:28px}.hard-ticket dl{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.hard-ticket dt{font-size:12px;color:#6b7280;text-transform:uppercase}.hard-ticket dd{margin:3px 0 0;font-weight:800}.controls{max-width:760px;margin:20px auto;background:#fff;border-radius:18px;padding:20px}.qr{display:flex;align-items:center;gap:20px;margin-top:22px;border-top:1px dashed #9ca3af;padding-top:20px}@media print{body{background:#fff}.controls{display:none!important}.hard-ticket{margin:0;max-width:none;border-radius:0}.qr canvas{width:150px!important;height:150px!important}}
    </style>
</head>
<body>
<main>
    <article class="hard-ticket">
        <header><strong>MOVIEMATE CINEMA · VÉ CỨNG</strong><h1>{{ $booking->showtime?->movie?->title }}</h1><p>ĐÃ THANH TOÁN · Lần in {{ $printState->attempts_count }}</p></header>
        <dl>
            <div><dt>Mã đặt vé</dt><dd>{{ $booking->booking_code }}</dd></div>
            <div><dt>Chi nhánh</dt><dd>{{ $booking->showtime?->cinema?->name }}</dd></div>
            <div><dt>Địa chỉ</dt><dd>{{ $booking->showtime?->cinema?->address }}</dd></div>
            <div><dt>Suất chiếu</dt><dd>{{ $booking->showtime_label }}</dd></div>
            <div><dt>Phòng</dt><dd>{{ $booking->showtime?->room?->name }}</dd></div>
            <div><dt>Ghế</dt><dd>{{ $booking->seat_codes }}</dd></div>
            <div><dt>Tổng thanh toán</dt><dd>{{ number_format((int)$booking->total_amount, 0, ',', '.') }} VNĐ</dd></div>
            <div><dt>Phương thức</dt><dd>{{ $booking->sales_channel === 'counter' ? 'Thanh toán tại quầy' : \App\Support\PaymentPresentation::providerLabel($booking->payment_method) }}</dd></div>
            <div><dt>Nhân viên</dt><dd>{{ request()->user()->name }}</dd></div>
            <div><dt>Bắt đầu in</dt><dd>{{ $printState->updated_at?->format('d/m/Y H:i:s') }}</dd></div>
        </dl>
        <div class="qr"><canvas data-qr-value="{{ $ticketQrPayload }}" data-qr-size="180" width="180" height="180" aria-label="QR xác minh vé"></canvas><div><strong>{{ $booking->booking_code }}</strong><p>QR xác minh đúng mã vé này. Việc in không đồng nghĩa vé đã được sử dụng.</p></div></div>
    </article>

    <section class="controls">
        <h2>Hoàn tất lần in</h2>
        <p>Hộp thoại trình duyệt không thể xác nhận máy in vật lý. Hãy chọn kết quả thực tế.</p>
        <button type="button" class="btn-primary" data-staff-print-trigger><i class="ph ph-printer"></i>Mở hộp thoại in</button>
        <form method="POST" action="{{ route('staff.tickets.print.succeed', $booking) }}" class="mt-4">@csrf
            <button type="submit" class="btn-primary">Đã in thành công</button>
        </form>
        <form method="POST" action="{{ route('staff.tickets.print.fail', $booking) }}" class="mt-5 space-y-3">@csrf
            <label class="cinema-label">Lý do in lỗi<select name="failure_code" class="cinema-input mt-1" required><option value="">Chọn lý do</option>@foreach($failureReasons as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></label>
            <label class="cinema-label">Ghi chú an toàn<textarea name="safe_note" class="cinema-input mt-1" maxlength="300"></textarea></label>
            <button type="submit" class="btn-secondary text-error">Báo lỗi in</button>
        </form>
    </section>
</main>
</body>
</html>
