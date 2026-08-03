<!DOCTYPE html>
<html lang="vi">
<head><meta charset="UTF-8"><title>Vé điện tử MovieMate</title></head>
<body style="margin:0;background:#080a12;font-family:Arial,sans-serif;color:#fff">
<div style="max-width:640px;margin:0 auto;padding:28px 16px">
    <div style="background:#151a27;border:1px solid #2d3343;border-radius:18px;padding:24px">
        <h1 style="margin:0 0 8px;color:#ff3d57">MovieMate</h1>
        <p style="margin:0 0 22px;color:#9ca3af">Vé điện tử của bạn đã được đặt thành công.</p>
        <div style="text-align:center;margin-bottom:22px">
            <img src="{{ $booking->qr_code_url }}"
                 width="180" height="180" alt="QR vé {{ $booking->booking_code }}"
                 style="background:#fff;padding:10px;border-radius:12px">
            <p style="margin:12px 0 0;font-size:20px;font-weight:bold;color:#ffb703">{{ $booking->booking_code }}</p>
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:14px">
            @foreach([
                'Phim' => $booking->showtime?->movie?->title,
                'Rạp' => $booking->showtime?->cinema?->name,
                'Địa chỉ' => $booking->showtime?->cinema?->address,
                'Phòng' => $booking->showtime?->room?->name,
                'Suất chiếu' => $booking->showtime?->show_date?->format('d/m/Y').' '.\Carbon\Carbon::parse($booking->showtime?->show_time)->format('H:i'),
                'Ghế' => $booking->bookingSeats->pluck('seat.seat_code')->join(', '),
            ] as $label => $value)
                <tr><td style="padding:10px 0;color:#9ca3af">{{ $label }}</td><td style="padding:10px 0;text-align:right;font-weight:bold">{{ $value }}</td></tr>
            @endforeach
            <tr><td style="padding:10px 0;color:#9ca3af">Tổng thanh toán</td><td style="padding:10px 0;text-align:right;font-weight:bold;color:#ff3d57">{{ number_format($booking->total_amount, 0, ',', '.') }}đ</td></tr>
        </table>
        <p style="margin:24px 0 0;color:#9ca3af;font-size:13px;line-height:1.6">Khi đến rạp, vui lòng đưa mã vé cho nhân viên soát vé.</p>
    </div>
</div>
</body>
</html>
