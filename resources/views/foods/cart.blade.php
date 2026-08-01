@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.3em] text-brand-start">Giỏ hàng của bạn</p>
            <h1 class="mt-3 text-3xl font-bold app-text">Kiểm tra trước khi thanh toán</h1>
        </div>
        <a href="{{ route('foods.index') }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/10 bg-app-card px-5 py-3 text-sm font-semibold app-text hover:border-brand-start hover:text-brand-start transition">
            <i class="ph ph-arrow-left"></i>
            Quay lại thực đơn
        </a>
    </div>

    @if(empty($cart))
        <div class="rounded-[2rem] border border-white/10 bg-app-card p-10 text-center">
            <p class="text-xl font-bold app-text">Giỏ hàng đang trống</p>
            <p class="mt-3 text-sm app-muted">Thêm món ăn vào giỏ để tiếp tục thanh toán.</p>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-[1.6fr_0.9fr]">
            <div class="space-y-4">
                @php $total = 0; @endphp
                @foreach($cart as $id => $qty)
                    @php $fi = $items[$id]; $line = $fi->price * $qty; $total += $line; @endphp
                    <div class="rounded-[2rem] border border-white/10 bg-app-card p-5 shadow-sm">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <h2 class="text-lg font-semibold app-text">{{ $fi->name }}</h2>
                                <p class="mt-2 text-sm app-muted">{{ Str::limit($fi->description, 100) }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm app-muted">Số lượng: <strong>{{ $qty }}</strong></p>
                                <p class="mt-2 text-lg font-semibold">{{ number_format($line,0,',','.') }}đ</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <aside class="rounded-[2rem] border border-white/10 bg-app-card p-6 shadow-sm">
                <div class="mb-6">
                    <p class="text-sm font-semibold uppercase tracking-[0.3em] text-brand-start">Tóm tắt đơn</p>
                    <p class="mt-3 text-sm app-muted">Kiểm tra tổng và tiếp tục thanh toán.</p>
                </div>
                <div class="space-y-3 border-y border-white/10 py-4">
                    <div class="flex items-center justify-between text-sm app-muted">
                        <span>Tổng tiền</span>
                        <span>{{ number_format($total,0,',','.') }}đ</span>
                    </div>
                    <div class="flex items-center justify-between text-sm app-muted">
                        <span>Phí dịch vụ</span>
                        <span>0đ</span>
                    </div>
                </div>
                <div class="mt-6 text-right">
                    <p class="text-sm app-muted">Tổng thanh toán</p>
                    <p class="mt-2 text-3xl font-bold app-text">{{ number_format($total,0,',','.') }}đ</p>
                </div>
                <a href="{{ route('foods.checkout') }}" class="mt-6 inline-flex w-full items-center justify-center rounded-2xl bg-brand-start px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-start/20 hover:bg-brand-end transition">Thanh toán</a>
            </aside>
        </div>
    @endif
</div>
@endsection
