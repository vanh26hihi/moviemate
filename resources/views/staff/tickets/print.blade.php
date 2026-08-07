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
        *{box-sizing:border-box}body{margin:0;background:#eef1f5;color:#111;font-family:Arial,sans-serif}.ticket{width:80mm;margin:16px auto;background:#fff;padding:4mm 4.5mm;font-size:11px;line-height:1.35}.brand{text-align:center}.brand-name{font-size:24px;font-weight:900;letter-spacing:1.5px}.ticket-title{margin:2px 0 0;font-size:12px;font-weight:800;letter-spacing:2px}.rule{margin:3mm 0;border:0;border-top:1px dashed #111}.movie{margin:0;text-align:center;font-size:17px;line-height:1.2;text-transform:uppercase}.code{margin:2mm 0 0;text-align:center;font-family:ui-monospace,monospace;font-size:13px;font-weight:900;overflow-wrap:anywhere}.facts{display:grid;gap:1.5mm}.fact{display:grid;grid-template-columns:22mm 1fr;gap:2mm}.fact dt{font-size:10px;color:#444}.fact dd{margin:0;font-weight:700;text-align:right;overflow-wrap:anywhere}.fact.strong dd{font-size:14px}.food-line,.money-line{display:flex;justify-content:space-between;gap:3mm;margin-top:1.5mm}.money-total{font-size:14px;font-weight:900}.qr{text-align:center}.qr canvas{display:block;width:32mm!important;height:32mm!important;margin:0 auto;background:#fff}.qr-note{margin:2mm 0 0;font-size:9px}.footer{text-align:center;font-size:9px}.controls{max-width:680px;margin:20px auto;background:#fff;border-radius:18px;padding:20px}.controls form{margin-top:16px}@page{size:80mm auto;margin:0}@media(max-width:360px){.ticket{width:58mm;padding:3mm;font-size:9px}.brand-name{font-size:19px}.movie{font-size:14px}.fact{grid-template-columns:17mm 1fr}.qr canvas{width:28mm!important;height:28mm!important}}@media print{html,body{width:80mm;background:#fff}.controls{display:none!important}.ticket{width:80mm;margin:0;padding:3mm 4mm}.qr canvas{width:32mm!important;height:32mm!important}}
    </style>
</head>
<body data-print-operation-id="{{ $printOperationId }}">
<main>
    <article class="ticket">
        <header class="brand"><div class="brand-name">MOVIEMATE</div><p class="ticket-title">VÉ XEM PHIM</p></header>
        <hr class="rule">
        <h1 class="movie">{{ $booking->showtime?->movie?->title }}</h1>
        <p class="code">{{ $booking->booking_code }}</p>
        <hr class="rule">
        <dl class="facts">
            <div class="fact"><dt>Rạp</dt><dd>{{ $booking->showtime?->cinema?->name }}</dd></div>
            <div class="fact"><dt>Địa chỉ</dt><dd>{{ $booking->showtime?->cinema?->address }}</dd></div>
            <div class="fact"><dt>Định dạng</dt><dd>{{ $booking->showtime?->room?->room_type ?: '2D' }}</dd></div>
            <div class="fact"><dt>Ngày</dt><dd>{{ $booking->showtime?->show_date?->format('d/m/Y') }}</dd></div>
            <div class="fact"><dt>Giờ</dt><dd>{{ \Carbon\CarbonImmutable::parse($booking->showtime?->show_time)->format('H:i') }}</dd></div>
            <div class="fact"><dt>Phòng</dt><dd>{{ $booking->showtime?->room?->name }}</dd></div>
            <div class="fact strong"><dt>Ghế</dt><dd>{{ $booking->seat_codes }}</dd></div>
        </dl>
        <hr class="rule">
        <div class="money-line"><span>Tiền vé</span><strong>{{ number_format((int) $booking->seat_subtotal, 0, ',', '.') }} VNĐ</strong></div>
        @forelse($booking->foodOrder?->items ?? [] as $item)
            <div class="food-line"><span>{{ $item->snapshot_name }} × {{ $item->quantity }}</span><strong>{{ number_format((int) $item->line_total, 0, ',', '.') }} VNĐ</strong></div>
        @empty
            <div class="money-line"><span>Đồ ăn</span><strong>0 VNĐ</strong></div>
        @endforelse
        <div class="money-line money-total"><span>Tổng</span><span>{{ number_format((int) $booking->total_amount, 0, ',', '.') }} VNĐ</span></div>
        <hr class="rule">
        <dl class="facts">
            <div class="fact"><dt>Kênh</dt><dd>{{ $booking->sales_channel === 'counter' ? 'Tại quầy' : 'Online' }}</dd></div>
            <div class="fact"><dt>Thanh toán</dt><dd>{{ match($booking->payments->filter(fn($payment) => $payment->hasAuthoritativeSuccessEvidence())->sortByDesc('id')->first()?->provider) { 'counter_cash' => 'Tiền mặt', 'vnpay' => 'VNPAY', 'payos' => 'payOS', default => 'Đã xác minh' } }}</dd></div>
            <div class="fact"><dt>Thu ngân</dt><dd>{{ $booking->payments->first(fn($payment) => $payment->settledBy)?->settledBy?->name ?? $booking->createdByStaff?->name ?? 'Online' }}</dd></div>
            <div class="fact"><dt>Thời gian in</dt><dd>{{ now($booking->showtime?->cinema?->timezone)->format('d/m/Y H:i') }}</dd></div>
        </dl>
        <hr class="rule">
        <div class="qr"><canvas data-qr-value="{{ $ticketQrPayload }}" data-qr-size="256" width="256" height="256" aria-label="QR bảo mật xác minh vé"></canvas><p class="code">{{ $booking->booking_code }}</p><p class="qr-note">QR bảo mật dùng để xác minh vé. Việc in không đồng nghĩa vé đã được soát.</p></div>
        <hr class="rule">
        <footer class="footer">Vui lòng đến trước giờ chiếu 15 phút.<br>Giữ vé trong suốt thời gian xem phim.</footer>
    </article>

    <section class="controls">
        <h2>Hoàn tất lần in</h2>
        <p>Hộp thoại trình duyệt không thể xác nhận máy in vật lý. Hãy chọn kết quả thực tế.</p>
        <button type="button" class="btn-primary" data-staff-print-trigger><i class="ph ph-printer"></i>In vé ngay</button>
        <form method="POST" action="{{ route('staff.tickets.print.succeed', $booking) }}" class="mt-4" data-submit-once>@csrf
            <button type="submit" class="btn-primary">Đã in thành công</button>
        </form>
        <form method="POST" action="{{ route('staff.tickets.print.fail', $booking) }}" class="mt-5 space-y-3" data-submit-once>@csrf
            <label class="cinema-label">Lý do in lỗi<select name="failure_code" class="cinema-input mt-1" required><option value="">Chọn lý do</option>@foreach($failureReasons as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></label>
            <label class="cinema-label">Ghi chú an toàn<textarea name="safe_note" class="cinema-input mt-1" maxlength="300"></textarea></label>
            <button type="submit" class="btn-secondary text-error">Báo lỗi in</button>
        </form>
    </section>
</main>
<script>
    (() => {
        const printNow = () => window.print();
        document.querySelector('[data-staff-print-trigger]')?.addEventListener('click', printNow);
        window.__moviemateStaffPrintBound = true;
        const operationId = document.body.dataset.printOperationId;
        if (!operationId) return;
        const key = `moviemate:staff-print-dialog:${operationId}`;
        try {
            if (sessionStorage.getItem(key) === 'opened') return;
            sessionStorage.setItem(key, 'opened');
        } catch {
            return;
        }
        window.setTimeout(printNow, 250);
    })();
</script>
</body>
</html>
