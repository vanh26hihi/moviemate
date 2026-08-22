<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn đặt vé MovieMate</title>
</head>
@php
    $verifiedPayment = $booking->payments
        ->where('status', \App\Models\Payment::STATUS_SUCCESS)
        ->sortByDesc('id')
        ->first();
    $provider = match ($verifiedPayment?->provider) {
        'vnpay' => 'VNPAY',
        'zalopay' => 'ZaloPay',
        'payos' => 'payOS',
        'counter_cash' => 'Tiền mặt tại quầy',
        default => 'Cổng thanh toán',
    };
    $foodItems = $booking->foodOrder?->items ?? collect();
@endphp
<body style="margin:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827">
<div style="max-width:640px;margin:0 auto;padding:28px 16px">
    <div style="overflow:hidden;border:1px solid #e5e7eb;border-radius:18px;background:#ffffff">
        <div style="padding:24px;background:linear-gradient(120deg,#e91e3d,#ff6b20);color:#ffffff">
            <img src="{{ $message->embed(public_path('images/brand/logo-on-dark.png')) }}" width="196" height="42" alt="MovieMate" style="display:block;width:196px;height:auto;margin:0 0 12px">
            <h1 style="margin:0;font-size:28px">Thanh toán thành công</h1>
            <p style="margin:8px 0 0;color:#fff1f2">Đơn đặt vé của bạn đã được xác nhận.</p>
        </div>

        <div style="padding:24px">
            <div style="margin-bottom:22px;padding:16px;border:1px dashed #fb7185;border-radius:12px;text-align:center">
                <p style="margin:0;color:#6b7280;font-size:12px;text-transform:uppercase">Đơn đặt vé</p>
                <p style="margin:10px 0 0;color:#e91e3d;font-family:monospace;font-size:21px;font-weight:bold;letter-spacing:1px">{{ $booking->booking_code }}</p>
            </div>

            <div style="margin-bottom:22px;text-align:center">
                <img src="{{ $message->embedData($ticketQrPng, 'moviemate-booking-qr.png', 'image/png') }}" width="220" height="220" alt="QR đơn đặt vé MovieMate" style="display:block;margin:0 auto;max-width:220px;width:100%;height:auto">
                <p style="margin:8px 0 0;color:#6b7280;font-size:12px">QR ĐƠN ĐẶT VÉ</p>
            </div>

            <table role="presentation" style="width:100%;border-collapse:collapse;font-size:14px">
                @foreach([
                    'Phim' => $booking->movie_title,
                    'Rạp' => $booking->cinema_label,
                    'Địa chỉ' => $booking->showtime?->cinema?->address,
                    'Phòng' => $booking->room_label,
                    'Định dạng' => $booking->showtime?->presentationFormat?->name ?? 'Không xác định',
                    'Suất chiếu' => $booking->showtime_label,
                    'Ghế' => $booking->seat_codes,
                    'Thanh toán' => $provider.' · '.($verifiedPayment?->provider === 'counter_cash' ? 'Đã thu tại quầy' : 'Đã xác minh'),
                ] as $label => $value)
                    <tr>
                        <td style="padding:9px 0;color:#6b7280;vertical-align:top">{{ $label }}</td>
                        <td style="padding:9px 0;text-align:right;font-weight:bold;vertical-align:top">{{ $value }}</td>
                    </tr>
                @endforeach
            </table>

            <div style="margin-top:18px;padding-top:16px;border-top:1px solid #e5e7eb">
                <p style="margin:0 0 8px;font-size:13px;font-weight:bold;text-transform:uppercase">{{ $booking->foodPickupVoucher ? 'Phiếu nhận đồ ăn' : 'Đồ ăn' }}</p>
                @forelse($foodItems as $item)
                    <p style="display:flex;justify-content:space-between;gap:12px;margin:6px 0;font-size:14px">
                        <span>{{ $item->snapshot_name }} × {{ $item->quantity }}</span>
                        <strong>{{ number_format((int) $item->line_total, 0, ',', '.') }} {{ $booking->currency_label }}</strong>
                    </p>
                @empty
                    <p style="margin:0;color:#6b7280;font-size:14px">Không có đồ ăn trong đơn.</p>
                @endforelse
            </div>

            <table role="presentation" style="width:100%;margin-top:16px;border-collapse:collapse;font-size:14px">
                <tr><td style="padding:6px 0;color:#6b7280">Tiền ghế</td><td style="padding:6px 0;text-align:right;font-weight:bold">{{ $booking->formatted_seat_subtotal }}</td></tr>
                <tr><td style="padding:6px 0;color:#6b7280">Tiền đồ ăn</td><td style="padding:6px 0;text-align:right;font-weight:bold">{{ $booking->formatted_food_subtotal }}</td></tr>
                <tr><td style="padding:10px 0 0;font-weight:bold">Tổng thanh toán</td><td style="padding:10px 0 0;text-align:right;color:#e91e3d;font-size:18px;font-weight:bold">{{ $booking->formatted_total }}</td></tr>
            </table>

            <p style="margin:24px 0 0;text-align:center">
                <a href="{{ $ticketAccessUrl }}" style="display:inline-block;padding:13px 22px;border-radius:10px;background:#e91e3d;color:#ffffff;text-decoration:none;font-weight:bold">Xem đơn đặt vé</a>
            </p>
            <p style="margin:20px 0 0;color:#4b5563;font-size:13px;line-height:1.65">Vui lòng xuất trình mã đơn hoặc QR đơn đặt vé tại quầy để nhận vé. QR này dùng để tra cứu đơn tại quầy, không phải vé vào phòng chiếu.</p>
            <p style="margin:12px 0 0;color:#6b7280;font-size:12px;line-height:1.55">MovieMate không bao giờ yêu cầu bạn gửi lại mật khẩu, mã thanh toán hoặc thông tin ngân hàng qua email. Cần hỗ trợ, vui lòng liên hệ quầy MovieMate tại rạp.</p>
        </div>
    </div>
</div>
</body>
</html>
