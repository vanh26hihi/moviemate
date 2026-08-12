<!doctype html>
<html lang="vi"><body style="font-family:Arial,sans-serif;color:#172033">
<h1>Cập nhật ghế xem phim</h1>
<p>Đơn <strong>{{ $booking->booking_code }}</strong> · {{ $booking->showtime?->movie?->title }}</p>
<p>{{ $booking->showtime_label }} · {{ $booking->showtime?->cinema?->name }} · {{ $booking->showtime?->room?->name }}</p>
<ul>
@foreach($relocations as $row)<li>Ghế {{ $row['original'] }} đã được đổi sang <strong>{{ $row['replacement'] }}</strong> do sự cố ghế.</li>@endforeach
</ul>
<p><strong>Bạn không phải thanh toán thêm.</strong></p>
@if(collect($relocations)->contains('reprint_required', true))
<p>Vé giấy cũ đã được in. Vui lòng đến quầy nhân viên để nhận vé giấy thay thế trước giờ chiếu.</p>
@endif
</body></html>
