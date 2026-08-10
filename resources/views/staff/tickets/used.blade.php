@extends('layouts.staff')

@section('title', 'Vé đã sử dụng - MovieMate Staff')
@section('page-title', 'Kết quả kiểm tra vé')

@php
    $seatCodes = $booking->bookingSeats->pluck('seat.seat_code')->filter()->join(', ') ?: 'Chưa có ghế';
@endphp

@section('content')
<div class="max-w-xl mx-auto">
    <div class="bg-warning/10 border-2 border-warning rounded-3xl p-8 text-center shadow-lg shadow-warning/20 mb-6">
        <div class="w-20 h-20 bg-warning text-white rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg shadow-warning/40">
            <i class="ph-bold ph-warning text-4xl"></i>
        </div>

        <h1 class="text-3xl font-bold text-warning mb-2">Vé đã được sử dụng</h1>
        <p class="app-muted font-medium mb-6">Vé này đã check-in trước đó. Không thể sử dụng lại.</p>

        <div class="app-card border app-border rounded-2xl p-6 text-left mb-8">
            <div class="border-b app-border pb-4 mb-4 flex justify-between items-center gap-4">
                <div>
                    <p class="text-xs app-muted uppercase tracking-wider mb-1">Mã đặt vé</p>
                    <p class="text-xl font-bold app-text font-mono">{{ $booking->booking_code }}</p>
                </div>
                <div class="px-3 py-1 bg-warning/20 text-warning text-xs font-bold rounded uppercase">
                    Đã sử dụng
                </div>
            </div>

            <div class="space-y-4 text-sm mb-6">
                <div class="flex justify-between gap-4">
                    <span class="app-muted">Khách hàng</span>
                    <span class="app-text font-semibold text-right">{{ $booking->user->name ?? 'Khách' }}</span>
                </div>
                <div class="flex justify-between gap-4 items-start">
                    <span class="app-muted">Phim</span>
                    <span class="app-text font-bold text-right max-w-[60%]">{{ $booking->showtime?->movie?->title ?? 'Không rõ phim' }}</span>
                </div>
                <div class="flex justify-between gap-4">
                    <span class="app-muted">Ghế</span>
                    <span class="app-text font-bold text-right">{{ $seatCodes }}</span>
                </div>
            </div>

            <div class="app-secondary border app-border rounded-xl p-4">
                <p class="text-xs app-muted uppercase tracking-wider mb-3 font-bold">Lịch sử check-in</p>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <span class="app-muted">Thời gian quét:</span>
                        <span class="app-text font-semibold text-right">{{ $booking->used_at ? $booking->used_at->format('d/m/Y H:i:s') : 'Không rõ' }}</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="app-muted">Trạng thái:</span>
                        <span class="text-warning font-bold text-right">{{ $booking->booking_status }}</span>
                    </div>
                </div>
            </div>
        </div>

        <a href="{{ route('staff.tickets.check') }}" class="block w-full py-4 app-secondary border app-border app-text rounded-2xl font-bold hover:border-warning transition-colors">
            Kiểm tra vé khác
        </a>
    </div>
</div>
@endsection
