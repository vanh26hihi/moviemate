@extends('layouts.user')

@section('title', 'Trạng thái thanh toán')

@section('content')
@php
    $states = [
        'pending' => ['Đang chờ xác nhận', 'text-amber-700'],
        'success' => ['Đã thanh toán', 'text-green-700'],
        'failed' => ['Thanh toán thất bại', 'text-red-700'],
        'review' => ['Cần đối soát', 'text-orange-700'],
        'expired' => ['Đã hết hạn', 'text-gray-700'],
    ];
    [$label, $colour] = $states[$payment->status] ?? ['Đang xử lý', 'text-gray-700'];
@endphp
<main class="mx-auto max-w-2xl px-4 py-16">
    <section class="rounded-3xl bg-white p-8 text-center shadow-xl">
        <h1 class="text-2xl font-bold">Kết quả trở về từ ZaloPay</h1>
        <p class="mt-4 text-lg font-semibold {{ $colour }}" data-payment-state="{{ $payment->status }}">{{ $label }}</p>
        <p class="mt-3 text-sm text-gray-600">
            Trạng thái hiển thị lấy từ hệ thống MovieMate. Dữ liệu trình duyệt
            {{ $integrityVerified ? 'có checksum hợp lệ' : 'không được xác thực' }} và không tự xác nhận thanh toán.
        </p>
        <p class="mt-4 text-sm">Mã đặt vé: <strong>{{ $booking->booking_code }}</strong></p>
    </section>
</main>
@endsection
