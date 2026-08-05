@extends('layouts.user')

@section('title', 'Checkout - MovieMate')

<<<<<<< HEAD
@php
    $loyaltyPoints = app(\App\Services\LoyaltyPointService::class)->calculate($totalAmount);
    $subtotalAmount = $subtotalAmount ?? $totalAmount;
    $voucherSummary = $voucherSummary ?? ['voucher' => null, 'code' => null, 'discount' => 0, 'total' => $totalAmount];
    $selectedSeatQuery = collect($seatSummaries)->pluck('id')->join(',');
@endphp

@section('content')
=======
@section('content')
<main class="user-page-shell px-4 py-10 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-3xl">
        <x-checkout-progress current="food" class="mb-8" />
        <section class="cinema-card rounded-3xl p-6 text-center sm:p-10">
            <i class="ph-fill ph-shield-check text-5xl text-success" aria-hidden="true"></i>
            <h1 class="mt-4 text-2xl font-extrabold app-text">Checkout thống nhất đã sẵn sàng</h1>
            <p class="mx-auto mt-3 max-w-xl leading-relaxed app-muted">MovieMate lưu lựa chọn ghế trong phiên bảo vệ và tiếp tục qua bước đồ ăn, xác nhận rồi thanh toán. Trang này không chứa token hoặc tổng tiền có thể dùng làm nguồn dữ liệu thật.</p>
            @isset($showtime)
                <a href="{{ route('user.bookings.selectSeat', $showtime) }}" class="btn-primary mt-6"><i class="ph-bold ph-arrow-left" aria-hidden="true"></i>Chọn lại ghế</a>
            @else
                <a href="{{ route('user.movies.index') }}" class="btn-primary mt-6">Chọn suất chiếu</a>
            @endisset
        </section>
    </div>
</main>
@endsection
>>>>>>> 2085321f924f762f85bc22987b110ce9eaa68f44
