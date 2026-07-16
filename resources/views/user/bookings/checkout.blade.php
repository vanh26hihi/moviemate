@extends('layouts.user')

@section('title', 'Thanh toán - MovieMate')

@section('content')
<div class="min-h-screen py-8 app-bg">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Progress Steps -->
        <div class="mb-8">
            <div class="flex items-center justify-center sm:justify-start gap-2 sm:gap-4 text-xs sm:text-sm">
                <div class="flex items-center gap-2 text-brand-start font-medium">
                    <div class="w-7 h-7 rounded-full bg-brand-start text-white flex items-center justify-center font-bold text-xs"><i class="ph-bold ph-check"></i></div>
                    <span class="hidden sm:inline">Chọn phim & Suất</span>
                </div>
                <div class="h-px w-8 sm:w-12 bg-brand-start"></div>
                <div class="flex items-center gap-2 text-brand-start font-medium">
                    <div class="w-7 h-7 rounded-full bg-brand-start text-white flex items-center justify-center font-bold text-xs"><i class="ph-bold ph-check"></i></div>
                    <span class="hidden sm:inline">Chọn ghế</span>
                </div>
                <div class="h-px w-8 sm:w-12 bg-brand-start"></div>
                <div class="flex items-center gap-2 text-brand-start font-medium">
                    <div class="w-7 h-7 rounded-full bg-brand-start text-white flex items-center justify-center font-bold text-xs">3</div>
                    <span>Thanh toán</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8">

            <!-- Left: Summary -->
            <div class="app-card border app-border rounded-2xl p-6">
                <h2 class="text-xl font-bold app-text mb-4">Thông tin đặt vé</h2>

                <ul class="space-y-3 text-sm">
                    <li class="flex justify-between"><span class="app-muted">Phim</span><span class="app-text font-medium">{{ $showtime->movie->title }}</span></li>
                    <li class="flex justify-between"><span class="app-muted">Rạp</span><span class="app-text font-medium">{{ $showtime->cinema->name }}</span></li>
                    <li class="flex justify-between"><span class="app-muted">Phòng</span><span class="app-text font-medium">{{ $showtime->room->name }}</span></li>
                    <li class="flex justify-between"><span class="app-muted">Ngày & Giờ</span><span class="app-text font-medium">{{ $showtime->show_date->format('d/m/Y') }} {{ \Carbon\Carbon::parse($showtime->show_time)->format('H:i') }}</span></li>
                </ul>

                <h3 class="text-lg font-bold app-text mt-6 mb-2">Ghế đã chọn</h3>
                <ul class="list-disc list-inside text-sm app-muted">
                    @foreach($seatSummaries as $seat)
                        <li>{{ $seat['seat_code'] }} ({{ ucfirst($seat['type']) }}) - {{ number_format($seat['price'],0,',','.') }}đ</li>
                    @endforeach
                </ul>

                <div class="flex justify-between items-center mt-4 pt-4 border-t app-border">
                    <span class="app-muted text-sm font-medium">Tổng tiền:</span>
                    <span class="text-2xl font-bold text-brand-start">{{ number_format($totalAmount,0,',','.') }}đ</span>
                </div>
            </div>

            <!-- Right: Payment Form -->
            <form action="{{ route('user.bookings.store') }}" method="POST" class="app-card border app-border rounded-2xl p-6">
                @csrf
                <input type="hidden" name="showtime_id" value="{{ $showtime->id }}">
                @foreach($seatSummaries as $seat)
                    <input type="hidden" name="seat_ids[]" value="{{ $seat['id'] }}">
                @endforeach

                <h2 class="text-xl font-bold app-text mb-4">Phương thức thanh toán</h2>

                <div class="space-y-3">
                    <label class="flex items-center p-3 app-input border border-brand-start rounded-xl cursor-pointer hover:border-brand-start transition-colors">
                        <input type="radio" name="payment_method" value="fake" checked class="text-brand-start focus:ring-brand-start w-4 h-4 mr-2">
                        <span class="app-text font-medium">Thanh toán giả lập (đã thanh toán)</span>
                    </label>

                    <label class="flex items-center p-3 app-input border app-border rounded-xl cursor-pointer hover:border-brand-start transition-colors">
                        <input type="radio" name="payment_method" value="counter" class="text-brand-start focus:ring-brand-start w-4 h-4 mr-2">
                        <span class="app-text font-medium">Thanh toán tại quầy</span>
                    </label>

                    <label class="flex items-center p-3 app-input border app-border rounded-xl cursor-pointer hover:border-brand-start transition-colors">
                        <input type="radio" name="payment_method" value="vnpay" class="text-brand-start focus:ring-brand-start w-4 h-4 mr-2">
                        <span class="app-text font-medium">Thanh toán VNPay (giả lập)</span>
                    </label>
                </div>

                <button type="submit" class="w-full mt-6 py-3.5 bg-gradient-to-r from-brand-start to-brand-end text-white rounded-xl font-bold hover:shadow-lg hover:shadow-brand-start/30 transition-all">
                    Xác nhận và thanh toán
                </button>
            </form>
        </div>
    </div>
</div>
@endsection