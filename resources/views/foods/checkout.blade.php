@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.3em] text-brand-start">Thanh toán đồ ăn</p>
            <h1 class="mt-3 text-3xl font-bold app-text">Hoàn tất đơn hàng</h1>
            <p class="mt-2 text-sm app-muted">Nhập thông tin nhận hàng để chúng tôi chuẩn bị sẵn khi bạn tới rạp.</p>
        </div>
        <a href="{{ route('foods.cart') }}" class="inline-flex items-center gap-2 rounded-2xl border border-white/10 bg-app-card px-5 py-3 text-sm font-semibold app-text hover:border-brand-start hover:text-brand-start transition">
            <i class="ph ph-arrow-left"></i>
            Quay lại giỏ hàng
        </a>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
        <section class="rounded-[2rem] border border-white/10 bg-app-card p-6 shadow-sm">
            <h2 class="text-xl font-semibold app-text mb-4">Thông tin khách hàng</h2>
            <form method="POST" action="{{ route('foods.store') }}" class="space-y-5">
                @csrf
                <div>
                    <label class="block text-sm font-medium app-text mb-2">Họ và tên</label>
                    <input type="text" name="customer_name" required class="admin-input w-full" placeholder="Nguyễn Văn A" />
                </div>
                <div>
                    <label class="block text-sm font-medium app-text mb-2">Số điện thoại</label>
                    <input type="text" name="customer_phone" class="admin-input w-full" placeholder="0987 654 321" />
                </div>
                <div>
                    <label class="block text-sm font-medium app-text mb-2">Email</label>
                    <input type="email" name="customer_email" class="admin-input w-full" placeholder="email@example.com" />
                </div>
                <div>
                    <label class="block text-sm font-medium app-text mb-2">Chọn rạp nhận</label>
                    <select name="pickup_cinema_id" class="admin-input w-full">
                        <option value="">Chọn rạp</option>
                        @foreach(\App\Models\Cinema::orderBy('name')->get() as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="mt-2 inline-flex w-full items-center justify-center rounded-2xl bg-brand-start px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-start/20 hover:bg-brand-end transition">Đặt hàng và thanh toán</button>
            </form>
        </section>

        <aside class="rounded-[2rem] border border-white/10 bg-app-card p-6 shadow-sm">
            <h2 class="text-xl font-semibold app-text mb-4">Tóm tắt đơn hàng</h2>
            <div class="space-y-4">
                @php $total = 0; @endphp
                @foreach($cart as $id => $qty)
                    @php $fi = $items[$id]; $line = $fi->price * $qty; $total += $line; @endphp
                    <div class="rounded-3xl border border-white/10 bg-app-secondary p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="font-semibold app-text">{{ $fi->name }}</h3>
                                <p class="text-sm app-muted">x{{ $qty }} • {{ number_format($fi->price,0,',','.') }}đ</p>
                            </div>
                            <p class="font-semibold">{{ number_format($line,0,',','.') }}đ</p>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-6 border-t border-white/10 pt-5 text-sm app-muted space-y-3">
                <div class="flex justify-between">
                    <span>Tổng hàng</span>
                    <span>{{ number_format($total,0,',','.') }}đ</span>
                </div>
                <div class="flex justify-between">
                    <span>Phí dịch vụ</span>
                    <span>0đ</span>
                </div>
            </div>
            <div class="mt-6 flex items-center justify-between text-sm font-semibold app-text">
                <span>Tổng thanh toán</span>
                <span>{{ number_format($total,0,',','.') }}đ</span>
            </div>
        </aside>
    </div>
</div>
@endsection
