<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title>Phiếu nhận đồ ăn {{ $voucher->voucher_code }} - MovieMate</title>
    @vite(['resources/css/app.css'])
    @include('staff.tickets._print-styles')
</head>
<body>
@include('staff.tickets._food-voucher', [
    'voucher' => $voucher,
    'booking' => $booking,
    'allocatedAmount' => $allocatedAmount,
])
<div class="print-controls"><button class="btn-primary" type="button" onclick="window.print()">In phiếu</button></div>
<script>window.setTimeout(() => window.print(), 250);</script>
</body>
</html>
