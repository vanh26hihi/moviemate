@extends('layouts.staff')
@section('title','Giao dịch quầy - MovieMate')
@section('page-title','Giao dịch quầy')
@section('content')
<div class="space-y-6">
    <header class="admin-page-header"><div><h1 class="admin-page-title">Giao dịch tại chi nhánh</h1><p class="admin-page-subtitle">{{ $cinema?->name ?? 'Bạn chưa được phân công chi nhánh' }} · Dữ liệu vận hành, không phải báo cáo tài chính.</p></div></header>
    <form method="GET" class="cinema-card grid gap-4 p-5 sm:grid-cols-3">
        <label class="cinema-label">Ngày<input class="cinema-input mt-1" type="date" name="date" value="{{ $date->format('Y-m-d') }}"></label>
        <label class="cinema-label">Trạng thái<select class="cinema-input mt-1" name="status"><option value="">Tất cả</option>@foreach(['pending_payment'=>'Đang giữ','paid'=>'Đã thanh toán','cancelled'=>'Đã hủy','expired'=>'Hết hạn'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></label>
        <label class="cinema-label">Kênh<select class="cinema-input mt-1" name="channel"><option value="">Tất cả</option><option value="counter" @selected(request('channel')==='counter')>Tại quầy</option><option value="online" @selected(request('channel')==='online')>Online</option></select><button class="btn-secondary mt-3 w-full" type="submit">Lọc giao dịch</button></label>
    </form>
    @if($bookings->isEmpty())
        <x-empty-state title="Chưa có giao dịch tại quầy" description="Không có đơn phù hợp với bộ lọc trong chi nhánh này." icon="ph-receipt" />
    @else
        <div class="space-y-3">@foreach($bookings as $booking)<article class="cinema-card grid gap-4 p-5 lg:grid-cols-[1fr_1fr_auto] lg:items-center"><div><a class="font-mono text-lg font-black text-brand-start" href="{{ route('staff.tickets.operations',$booking) }}">{{ $booking->booking_code }}</a><p class="mt-1 text-sm app-muted">{{ $booking->created_at->format('H:i') }} · {{ $booking->sales_channel === 'counter' ? 'Tại quầy' : 'Online' }} · {{ $booking->status_label }}</p></div><div><p class="font-bold app-text">{{ $booking->showtime?->movie?->title }}</p><p class="text-sm app-muted">{{ $booking->showtime_label }} · {{ $booking->showtime?->room?->name }} · {{ $booking->seat_codes }}</p></div><div class="lg:text-right"><p class="font-black app-text">{{ $booking->formatted_total }}</p><p class="text-xs app-muted">Tạo: {{ $booking->createdByStaff?->name ?? 'Khách online' }} · Thu: {{ $booking->authoritativePayment?->settledBy?->name ?? '—' }}</p><p class="mt-1 text-xs app-muted">In: {{ $booking->ticketPrint?->status_label ?? 'Chưa in' }} @if(($booking->ticketPrint?->attempts_count ?? 0) > 1)· In lại {{ $booking->ticketPrint->attempts_count - 1 }} lần @endif</p></div></article>@endforeach</div>
        <div>{{ $bookings->links() }}</div>
    @endif
</div>
@endsection
