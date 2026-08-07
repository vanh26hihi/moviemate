@extends('layouts.staff')

@section('title', 'Xác nhận thu tiền - MovieMate')
@section('page-title', 'Xác nhận đơn tại quầy')

@section('content')
@php $cashPayment=$booking->payments->first(fn($payment)=>$payment->provider===\App\Models\Payment::PROVIDER_COUNTER_CASH && $payment->hasAuthoritativeSuccessEvidence()); @endphp
<div class="mx-auto max-w-5xl space-y-6">
    <header><p class="text-sm font-bold text-brand-start">{{ $booking->booking_code }}</p><h1 class="mt-2 text-3xl font-extrabold app-heading">{{ $cashPayment ? 'Thanh toán tại quầy thành công' : 'Kiểm tra trước khi thu tiền' }}</h1></header>
    <section class="cinema-card p-6"><h2 class="text-xl font-extrabold app-heading">Thông tin bán vé</h2><dl class="mt-5 grid gap-4 sm:grid-cols-2">
        <div><dt class="text-sm app-muted">Chi nhánh</dt><dd class="font-bold">{{ $booking->showtime->cinema->name }}</dd></div><div><dt class="text-sm app-muted">Kênh bán</dt><dd class="font-bold">Tại quầy</dd></div>
        <div><dt class="text-sm app-muted">Phim</dt><dd class="font-bold">{{ $booking->showtime->movie->title }}</dd></div><div><dt class="text-sm app-muted">Suất chiếu</dt><dd class="font-bold">{{ $booking->showtime_label }}</dd></div>
        <div><dt class="text-sm app-muted">Phòng</dt><dd class="font-bold">{{ $booking->showtime->room->name }}</dd></div><div><dt class="text-sm app-muted">Ghế</dt><dd class="font-bold">{{ $booking->seat_codes }}</dd></div>
        <div><dt class="text-sm app-muted">Người tạo đơn</dt><dd class="font-bold">{{ $booking->createdByStaff?->name ?? '—' }}</dd></div><div><dt class="text-sm app-muted">Khách hàng</dt><dd class="font-bold">{{ $booking->customer_name ?: 'Khách vãng lai' }}</dd></div>
        <div><dt class="text-sm app-muted">Điện thoại</dt><dd class="font-bold">{{ $booking->customer_phone ?: '—' }}</dd></div><div><dt class="text-sm app-muted">Email</dt><dd class="font-bold">{{ $booking->customer_email ?: '—' }}</dd></div>
    </dl></section>
    <div class="grid gap-6 lg:grid-cols-2"><section class="cinema-card p-6"><h2 class="text-xl font-extrabold app-heading">Đồ ăn</h2>@forelse($booking->foodOrder?->items ?? [] as $item)<div class="mt-3 flex justify-between"><span>{{ $item->snapshot_name }} × {{ $item->quantity }}</span><strong>{{ number_format((int)$item->line_total,0,',','.') }} VNĐ</strong></div>@empty<p class="mt-3 app-muted">Không kèm đồ ăn.</p>@endforelse</section>
    <section class="cinema-card p-6"><h2 class="text-xl font-extrabold app-heading">Tổng tiền lưu trên máy chủ</h2><dl class="mt-4 space-y-3"><div class="flex justify-between"><dt>Tiền vé</dt><dd class="font-bold">{{ number_format((int)$booking->seat_subtotal,0,',','.') }} VNĐ</dd></div><div class="flex justify-between"><dt>Đồ ăn</dt><dd class="font-bold">{{ number_format((int)$booking->food_subtotal,0,',','.') }} VNĐ</dd></div><div class="flex justify-between border-t app-border pt-3 text-xl"><dt class="font-extrabold">Tổng</dt><dd class="font-extrabold text-brand-start">{{ number_format((int)$booking->total_amount,0,',','.') }} VNĐ</dd></div></dl></section></div>

    @if($cashPayment)
        <section class="cinema-card border border-success/40 p-6"><p class="font-extrabold text-success">Thanh toán tiền mặt thành công.</p><p class="mt-2 app-muted">Thu ngân: {{ $cashPayment->settledBy?->name ?? '—' }} · {{ $cashPayment->settled_at?->format('d/m/Y H:i:s') }} · {{ $cashPayment->transaction_code }}</p><div class="mt-5 flex flex-wrap gap-3">@can('tickets.print')<form method="POST" action="{{ route('staff.tickets.print.start', $booking) }}" data-submit-once>@csrf<button class="btn-primary" type="submit"><i class="ph ph-printer"></i>In vé</button></form>@endcan<a class="btn-secondary" href="{{ route('staff.tickets.operations',$booking) }}">Xem chi tiết</a><a class="btn-secondary" href="{{ route('staff.counter.index') }}">Tạo giao dịch mới</a></div></section>
    @else
        <div class="flex flex-wrap justify-end gap-3">@can('counter_sales.cancel')<form method="POST" action="{{ route('staff.counter.cancel',$booking) }}" data-submit-once>@csrf<button class="btn-secondary" type="submit">Hủy đơn và giải phóng ghế</button></form>@endcan @can('counter_sales.settle')<form method="POST" action="{{ route('staff.counter.cash',$booking) }}" data-submit-once>@csrf<button class="btn-primary" type="submit">Xác nhận đã thu tiền mặt</button></form>@endcan</div>
    @endif
</div>
@endsection
