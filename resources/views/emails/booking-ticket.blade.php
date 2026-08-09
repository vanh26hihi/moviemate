<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Vé điện tử MovieMate</title>
</head>
<body style="margin:0;background:#080A12;font-family:Arial,sans-serif;color:#ffffff;">
    <div style="max-width:640px;margin:0 auto;padding:28px 16px;">
        <div style="background:#151A27;border:1px solid #2D3343;border-radius:18px;padding:24px;">
            <h1 style="margin:0 0 8px;color:#FF3D57;">MovieMate</h1>
            <p style="margin:0 0 22px;color:#9CA3AF;">Vé điện tử của bạn đã được thanh toán thành công.</p>

            <div style="text-align:center;margin-bottom:22px;">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data={{ urlencode($booking->booking_code) }}"
                     alt="QR vé {{ $booking->booking_code }}"
                     style="background:#ffffff;padding:10px;border-radius:12px;">
                <p style="margin:12px 0 0;font-size:20px;font-weight:bold;color:#FFB703;">{{ $booking->booking_code }}</p>
            </div>

            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <tr>
                    <td style="padding:10px 0;color:#9CA3AF;">Phim</td>
                    <td style="padding:10px 0;text-align:right;font-weight:bold;">{{ $booking->showtime?->movie?->title }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 0;color:#9CA3AF;">Rạp</td>
                    <td style="padding:10px 0;text-align:right;font-weight:bold;">{{ $booking->showtime?->cinema?->name }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 0;color:#9CA3AF;">Phòng</td>
                    <td style="padding:10px 0;text-align:right;font-weight:bold;">{{ $booking->showtime?->room?->name }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 0;color:#9CA3AF;">Suất chiếu</td>
                    <td style="padding:10px 0;text-align:right;font-weight:bold;">
                        {{ $booking->showtime?->show_date ? \Carbon\Carbon::parse($booking->showtime->show_date)->format('d/m/Y') : '--' }}
                        {{ $booking->showtime?->show_time ? \Carbon\Carbon::parse($booking->showtime->show_time)->format('H:i') : '--:--' }}
                    </td>
                </tr>
                <tr>
                    <td style="padding:10px 0;color:#9CA3AF;">Ghế</td>
                    <td style="padding:10px 0;text-align:right;font-weight:bold;">{{ $booking->bookingSeats->pluck('seat.seat_code')->join(', ') }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 0;color:#9CA3AF;">Tổng thanh toán</td>
                    <td style="padding:10px 0;text-align:right;font-weight:bold;color:#FF3D57;">{{ number_format($booking->total_amount, 0, ',', '.') }}đ</td>
                </tr>
                @if((float) $booking->discount_amount > 0)
                    <tr>
                        <td style="padding:10px 0;color:#9CA3AF;">Voucher</td>
                        <td style="padding:10px 0;text-align:right;font-weight:bold;color:#22C55E;">
                            {{ $booking->voucher_code }} -{{ number_format($booking->discount_amount, 0, ',', '.') }}đ
                        </td>
                    </tr>
                @endif
            </table>

            <p style="margin:24px 0 0;color:#9CA3AF;font-size:13px;line-height:1.6;">
                Khi đến rạp, vui lòng đưa mã QR hoặc mã vé cho nhân viên soát vé.
            </p>
        </div>
    </div>
</body>
</html>
