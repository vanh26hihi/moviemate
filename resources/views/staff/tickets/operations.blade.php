@extends('layouts.staff')

@section('title', 'Vận hành vé '.$booking->booking_code.' - MovieMate')
@section('page-title', 'Xem trước vé')

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <a href="{{ route('staff.tickets.index') }}" class="mb-3 inline-flex items-center gap-2 text-sm font-bold text-brand-start"><i class="ph ph-arrow-left"></i>Quét vé khác</a>
            <h1 class="text-3xl font-extrabold app-heading">{{ $booking->booking_code }}</h1>
            <p class="mt-2 app-muted">{{ $eligibilityMessage }}</p>
        </div>
        <span class="status-badge {{ $booking->booking_status === 'paid' && $booking->payment_status === 'paid' ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning' }}">
            {{ $booking->booking_status === 'used' ? 'Đã soát vé' : 'Chưa soát vé' }}
        </span>
    </header>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="cinema-card p-5"><p class="text-sm app-muted">Chi nhánh</p><p class="mt-2 font-extrabold app-text">{{ $booking->showtime?->cinema?->name ?? '—' }}</p></div>
        <div class="cinema-card p-5"><p class="text-sm app-muted">Thanh toán</p><p class="mt-2 font-extrabold app-text">{{ $booking->payment_status_label }}</p></div>
        <div class="cinema-card p-5"><p class="text-sm app-muted">Trạng thái in vé</p><p class="mt-2 font-extrabold app-text">{{ $printState?->status_label ?? 'Chưa có dữ liệu in' }}</p></div>
        <div class="cinema-card p-5"><p class="text-sm app-muted">Trạng thái soát vé</p><p class="mt-2 font-extrabold app-text">{{ $booking->booking_status === 'used' ? 'Đã soát vé' : 'Chưa soát vé' }}</p></div>
    </section>

    <section class="cinema-card p-6">
        <h2 class="text-xl font-extrabold app-heading">Thông tin đã xác thực</h2>
        <dl class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div><dt class="text-sm app-muted">Phim</dt><dd class="font-bold app-text">{{ $booking->showtime?->movie?->title ?? '—' }}</dd></div>
            <div><dt class="text-sm app-muted">Suất chiếu</dt><dd class="font-bold app-text">{{ $booking->showtime_label }}</dd></div>
            <div><dt class="text-sm app-muted">Phòng</dt><dd class="font-bold app-text">{{ $booking->showtime?->room?->name ?? '—' }}</dd></div>
            <div><dt class="text-sm app-muted">Ghế</dt><dd class="font-bold app-text">{{ $booking->seat_codes ?: '—' }}</dd></div>
            <div><dt class="text-sm app-muted">Khách hàng</dt><dd class="font-bold app-text">{{ $customerName }} · {{ $customerEmail }}</dd></div>
            <div><dt class="text-sm app-muted">Gửi vé điện tử</dt><dd class="font-bold app-text">{{ $booking->ticketDelivery?->status_label ?? 'Chưa có dữ liệu' }}</dd></div>
        </dl>
    </section>

    <section class="cinema-card p-6">
        <h2 class="text-xl font-extrabold app-heading">Thao tác riêng biệt</h2>
        <p class="mt-2 app-muted">In vé cứng không đánh dấu đã soát. Soát vé vẫn là một thao tác xác nhận độc lập.</p>
        <div class="mt-5 flex flex-wrap gap-3">
            @can('tickets.print')
                @if($booking->booking_status === 'paid' && $booking->payment_status === 'paid' && (!$printState || in_array($printState->status, ['retry_allowed', 'retry_authorized'], true)))
                    <form method="POST" action="{{ route('staff.tickets.print.start', $booking) }}">@csrf
                        <button type="submit" class="btn-primary"><i class="ph ph-printer"></i>In vé cứng</button>
                    </form>
                @elseif($printState?->status === 'printed')
                    <span class="rounded-xl bg-success/10 px-4 py-3 font-bold text-success">Đã in thành công {{ $printState->printed_at?->format('d/m/Y H:i') }}</span>
                @elseif($printState?->status === 'retry_requires_authorization')
                    <span class="rounded-xl bg-warning/10 px-4 py-3 font-bold text-warning">Cần quản lý cho phép in lại</span>
                @endif
            @endcan
            @can('tickets.checkin')
                <a href="{{ route('staff.tickets.check') }}" class="btn-secondary"><i class="ph ph-qr-code"></i>Chuyển sang soát vé</a>
            @endcan
        </div>
    </section>
</div>
@endsection
