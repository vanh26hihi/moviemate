@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="rounded-[2rem] border border-white/10 bg-app-card p-10 text-center shadow-lg shadow-black/10">
        <div class="mx-auto mb-6 inline-flex h-20 w-20 items-center justify-center rounded-full bg-success/10 text-success text-4xl">
            <i class="ph-fill ph-check-circle"></i>
        </div>
        <h1 class="text-3xl font-bold app-text">Đặt đồ ăn thành công!</h1>
        <p class="mt-4 text-sm app-muted">Đơn hàng #{{ $order->id }} đã được ghi nhận. Chúng tôi sẽ chuẩn bị đồ ăn và giao khi bạn đến rạp.</p>

        <div class="mt-8 grid gap-4 sm:grid-cols-2">
            <div class="rounded-3xl border border-white/10 bg-app-secondary p-5">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-start font-semibold">Tổng đơn</p>
                <p class="mt-3 text-2xl font-bold app-text">{{ number_format($order->total_amount,0,',','.') }}đ</p>
            </div>
            <div class="rounded-3xl border border-white/10 bg-app-secondary p-5">
                <p class="text-xs uppercase tracking-[0.3em] text-brand-start font-semibold">Trạng thái đơn</p>
                <p class="mt-3 text-2xl font-bold app-text capitalize">{{ $order->status }}</p>
            </div>
        </div>

        <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
            <a href="{{ route('foods.index') }}" class="inline-flex items-center justify-center rounded-2xl bg-brand-start px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-start/20 hover:bg-brand-end transition">Quay lại thực đơn</a>
            <a href="{{ route('user.showtimes.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-white/10 bg-app-card px-6 py-3 text-sm font-semibold app-text hover:border-brand-start hover:text-brand-start transition">Xem lịch chiếu</a>
        </div>
    </div>
</div>
@endsection
