@extends('layouts.user')

@section('title', 'Vé '.$booking->booking_code.' - MovieMate')

@section('content')
<section class="min-h-screen bg-[#080A12] px-6 py-12">
    <div class="mx-auto max-w-3xl">
        <div class="mb-10 text-center">
            <p class="mb-2 text-sm font-bold uppercase tracking-[0.3em] text-[#FF7A18]">My Ticket</p>
            <h1 class="text-4xl font-black">Vé điện tử</h1>
            <p class="mt-3 text-gray-400">Đưa mã QR này cho nhân viên để kiểm tra vé.</p>
        </div>
        <div class="overflow-hidden rounded-[36px] border border-white/10 bg-[#151A27] shadow-2xl">
            <div class="bg-gradient-to-r from-[#FF3D57] to-[#FF7A18] p-6 flex items-center justify-between">
                <h2 class="text-2xl font-black">MovieMate Ticket</h2>
                <span class="rounded-full bg-white/20 px-4 py-2 text-sm font-bold">{{ $booking->booking_status === 'used' ? 'Đã sử dụng' : 'Chưa sử dụng' }}</span>
            </div>
            <div class="grid gap-8 p-8 md:grid-cols-[1fr_220px]">
                <div class="space-y-5">
                    <div><p class="text-sm text-gray-400">Phim</p><h3 class="text-2xl font-black">{{ $booking->showtime->movie->title }}</h3></div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div><p class="text-sm text-gray-400">Rạp</p><p class="font-bold">{{ $booking->showtime->cinema->name }}</p></div>
                        <div><p class="text-sm text-gray-400">Phòng</p><p class="font-bold">{{ $booking->showtime->room->name }}</p></div>
                        <div><p class="text-sm text-gray-400">Ngày giờ</p><p class="font-bold">{{ \Carbon\Carbon::parse($booking->showtime->show_time)->format('H:i') }} - {{ $booking->showtime->show_date->format('d/m/Y') }}</p></div>
                        <div><p class="text-sm text-gray-400">Ghế</p><p class="font-bold text-[#FF7A18]">{{ $booking->bookingSeats->pluck('seat.seat_code')->join(', ') }}</p></div>
                        <div><p class="text-sm text-gray-400">Mã vé</p><p class="font-bold">{{ $booking->booking_code }}</p></div>
                        <div><p class="text-sm text-gray-400">Tổng tiền</p><p class="font-bold">{{ number_format($booking->total_amount, 0, ',', '.') }}đ</p></div>
                    </div>
                </div>
                <div class="flex flex-col items-center justify-center">
                    <img src="{{ $booking->qr_code_url }}" alt="QR Code {{ $booking->booking_code }}" class="h-52 w-52 rounded-3xl bg-white p-3">
                    <p class="mt-4 text-center text-sm text-gray-400">Mã QR soát vé</p>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
