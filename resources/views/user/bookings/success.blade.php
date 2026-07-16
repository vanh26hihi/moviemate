@extends('layouts.user')

@section('title', 'Đặt vé thành công - MovieMate')

@section('content')
<div class="min-h-[80vh] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 bg-[url('https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80')] bg-cover bg-center bg-no-repeat bg-fixed relative">
    <div class="absolute inset-0 bg-dark-main/90 backdrop-blur-sm"></div>

    <div class="relative max-w-lg w-full bg-dark-card/90 border border-dark-border rounded-3xl p-8 sm:p-12 text-center shadow-2xl shadow-brand-start/20 backdrop-blur-md animate-[fade-in-up_0.5s_ease-out]">

        <div class="w-24 h-24 bg-success/20 rounded-full flex items-center justify-center mx-auto mb-6 relative">
            <div class="absolute inset-0 rounded-full border-4 border-success animate-ping opacity-20"></div>
            <i class="ph-bold ph-check text-5xl text-success animate-[fade-in_0.5s_ease-out_0.2s_both]"></i>
        </div>

        <h1 class="text-3xl font-bold text-white mb-2">Đặt vé thành công!</h1>
        <p class="text-text-sub mb-8">Cảm ơn bạn đã sử dụng dịch vụ của MovieMate.</p>

        <div class="bg-dark-main border border-dark-border rounded-2xl p-6 mb-8 text-left relative overflow-hidden">
            <!-- Ticket Notch Left/Right -->
            <div class="absolute top-1/2 -left-3 -translate-y-1/2 w-6 h-6 bg-dark-card rounded-full border-r border-dark-border"></div>
            <div class="absolute top-1/2 -right-3 -translate-y-1/2 w-6 h-6 bg-dark-card rounded-full border-l border-dark-border"></div>

            <div class="border-b border-dashed border-dark-border pb-4 mb-4 flex justify-between items-center">
                <div>
                    <p class="text-xs text-text-sub mb-1">Mã đặt vé</p>
                    <p class="text-xl font-bold text-brand-start font-mono">{{ $booking->booking_code }}</p>
                </div>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=60x60&data={{ $booking->booking_code }}&color=FF3D57&bgcolor=080A12" alt="QR Code" class="w-12 h-12 rounded bg-white p-1">
            </div>

            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-text-sub">Phim</span>
                    <span class="text-white font-medium text-right max-w-[60%]">{{ $booking->showtime->movie->title }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-text-sub">Rạp</span>
                    <span class="text-white font-medium text-right">{{ $booking->showtime->cinema->name }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-text-sub">Thời gian</span>
                    <span class="text-white font-medium text-right">{{ $booking->showtime->show_date->format('d/m/Y') }} {{ \Carbon\Carbon::parse($booking->showtime->show_time)->format('H:i') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-text-sub">Ghế</span>
                    <span class="text-white font-bold text-right">
                        {{ $booking->bookingSeats->pluck('seat.seat_code')->join(', ') }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-text-sub">Tổng tiền</span>
                    <span class="text-white font-bold text-right">{{ number_format($booking->total_amount,0,',','.') }}đ</span>
                </div>
            </div>
        </div>

        <p class="text-sm text-text-sub mb-8">
            <i class="ph-fill ph-envelope-simple text-brand-start"></i> Vé điện tử đã được gửi đến email <br>
            <span class="text-white font-medium mt-1 inline-block">{{ $booking->user->email }}</span>
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ route('user.bookings.ticket') }}" class="px-6 py-3 bg-gradient-to-r from-brand-start to-brand-end text-white rounded-xl font-bold hover:shadow-lg hover:shadow-brand-start/20 transition-all transform hover:-translate-y-0.5">
                Xem vé QR của tôi
            </a>
            <a href="{{ route('home') }}" class="px-6 py-3 bg-dark-main border border-dark-border text-white rounded-xl font-bold hover:bg-dark-border transition-colors">
                Về trang chủ
            </a>
        </div>
    </div>
</div>
@endsection