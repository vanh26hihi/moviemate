<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="referrer" content="no-referrer">
    <title>In vé xem phim {{ $ticket->ticket_code }} - MovieMate</title>
    <x-brand.head-icons />
    @vite(['resources/css/app.css', 'resources/js/staff-ticket-print.js'])
    @include('staff.tickets._print-styles')
</head>
<body data-print-operation-id="{{ $printOperationId }}">
<x-form-validation-state :errors="$errors" />
<main>
    @include('staff.tickets._physical-ticket', [
        'ticket' => $ticket,
        'booking' => $booking,
        'allocatedAmount' => $allocatedAmount,
    ])

    <section class="print-controls">
        <h2>Hoàn tất lần in</h2>
        <p>Hộp thoại trình duyệt không thể xác nhận máy in vật lý. Hãy chọn kết quả thực tế.</p>
        <button type="button" class="btn-primary" data-staff-print-trigger><i class="ph ph-printer"></i>In vé ngay</button>
        <form method="POST" action="{{ route('staff.admission-tickets.print.succeed', $ticket) }}" data-submit-once>@csrf
            <button type="submit" class="btn-primary">Đã in thành công</button>
        </form>
        <form method="POST" action="{{ route('staff.admission-tickets.print.fail', $ticket) }}" class="space-y-3" data-submit-once data-inline-validation>@csrf
            <label class="cinema-label">Lý do in lỗi<select name="failure_code" class="cinema-input mt-1" required><option value="">Chọn lý do</option>@foreach($failureReasons as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></label>
            <label class="cinema-label">Ghi chú an toàn<textarea name="safe_note" class="cinema-input mt-1" maxlength="300" data-validation-required-if="failure_code:other"></textarea></label>
            <button type="submit" class="btn-secondary text-error">Báo lỗi in</button>
        </form>
    </section>
</main>
</body>
</html>
