@extends('layouts.admin')

@section('title', 'Chi tiết đơn đồ ăn - MovieMate Admin')
@section('page-title', 'Chi tiết đơn đồ ăn')

@section('content')
    <div class="app-card border app-border rounded-2xl overflow-hidden shadow-lg">
        <div class="p-5 border-b app-border flex flex-col sm:flex-row justify-between gap-4 items-start sm:items-center">
            <div>
                <h2 class="text-lg font-bold">Đơn #{{ $order->id }}</h2>
                <p class="text-sm app-muted">{{ $order->created_at->format('d/m/Y H:i') }}</p>
            </div>
            <a href="{{ route('admin.food-orders.index') }}" class="px-4 py-2 rounded-xl border app-border app-muted hover:app-text transition-colors text-sm">Quay lại danh sách</a>
        </div>

        <div class="p-5 space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="app-card rounded-3xl p-4 border app-border">
                    <div class="text-xs uppercase tracking-wider app-muted mb-2">Khách hàng</div>
                    <div class="font-semibold">{{ $order->customer_name }}</div>
                    <div class="text-sm app-muted">{{ $order->customer_phone }}</div>
                    <div class="text-sm app-muted">{{ $order->customer_email }}</div>
                </div>
                <div class="app-card rounded-3xl p-4 border app-border">
                    <div class="text-xs uppercase tracking-wider app-muted mb-2">Rạp nhận</div>
                    <div class="font-semibold">{{ optional($order->pickupCinema)->name ?? 'Chưa chọn' }}</div>
                    <div class="text-sm app-muted">{{ optional($order->pickupCinema)->address ?? '—' }}</div>
                </div>
                <div class="app-card rounded-3xl p-4 border app-border">
                    <div class="text-xs uppercase tracking-wider app-muted mb-2">Tổng</div>
                    <div class="text-2xl font-bold">{{ number_format($order->total_amount,2) }}đ</div>
                    <div class="text-xs app-muted">Trạng thái: {{ ucfirst($order->status) }}</div>
                </div>
            </div>

            <div class="app-card rounded-3xl p-4 border app-border">
                <div class="text-sm font-semibold mb-3">Chi tiết món</div>
                <div class="divide-y divide-[var(--border-color)]">
                    @foreach($order->items as $item)
                        <div class="py-3 grid grid-cols-12 gap-4 items-center">
                            <div class="col-span-6">
                                <div class="font-semibold">{{ $item->food->name }}</div>
                                <div class="text-sm app-muted">{{ $item->quantity }} x {{ number_format($item->price,2) }}đ</div>
                            </div>
                            <div class="col-span-6 text-right font-semibold">{{ number_format($item->total,2) }}đ</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection
