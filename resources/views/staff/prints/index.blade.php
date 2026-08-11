@extends('layouts.staff')
@section('title','Vé cần in - MovieMate')
@section('page-title','Vé cần in')
@section('content')
<div class="space-y-6">
    <header class="admin-page-header"><div><h1 class="admin-page-title">Vé cần in</h1><p class="admin-page-subtitle">{{ $cinema ? 'Các vé xem phim theo ghế chưa hoàn tất in tại '.$cinema->name.'.' : 'Bạn chưa được phân công chi nhánh.' }}</p></div></header>
    @if($bookings->isEmpty())
        <x-empty-state title="Không có vé chờ in" description="Mọi vé đủ điều kiện đã được xử lý hoặc hiện chưa có giao dịch mới." icon="ph-printer" />
    @else
        <div class="space-y-3">
            @foreach($bookings as $booking)
                <article class="cinema-card p-5">
                    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                        <div><p class="font-mono text-lg font-black text-brand-start">{{ $booking->booking_code }}</p><p class="font-bold app-text">{{ $booking->showtime?->movie?->title }}</p><p class="text-sm app-muted">{{ $booking->showtime_label }} · {{ $booking->showtime?->room?->name }}</p></div>
                        <a class="btn-secondary" href="{{ route('staff.tickets.operations',$booking) }}">Mở vận hành vé</a>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">@foreach($booking->admissionTickets as $ticket)<span class="status-badge {{ $ticket->print_count > 0 ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning' }}">Ghế {{ $ticket->seat_code }} · {{ $ticket->print_count > 0 ? 'Đã in '.$ticket->print_count.' lần' : 'Chưa in' }}</span>@endforeach</div>
                </article>
            @endforeach
        </div>
        <div>{{ $bookings->links() }}</div>
    @endif
</div>
@endsection
