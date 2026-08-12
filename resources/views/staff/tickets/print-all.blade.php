<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title>In toàn bộ {{ $booking->booking_code }} - MovieMate</title>
    @vite(['resources/css/app.css'])
    @include('staff.tickets._print-styles')
</head>
<body>
@php($printedAt = now($booking->showtime?->cinema?->timezone ?: config('app.timezone')))
@foreach($booking->admissionTickets->sortBy('booking_seat_id') as $ticket)
    @include('staff.tickets._physical-ticket', [
        'ticket' => $ticket,
        'booking' => $booking,
        'allocatedAmount' => $printAmounts->forTicket($ticket),
        'printedAt' => $printedAt,
    ])
@endforeach
@if($booking->foodPickupVoucher)
    @include('staff.tickets._food-voucher', [
        'voucher' => $booking->foodPickupVoucher,
        'booking' => $booking,
        'allocatedAmount' => $printAmounts->foodVoucherAmount,
        'printedAt' => $printedAt,
    ])
@endif
<div class="print-controls"><button class="btn-primary" type="button" onclick="window.print()">In toàn bộ</button></div>
<script>window.setTimeout(() => window.print(), 250);</script>
</body>
</html>
