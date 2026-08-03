@extends('layouts.admin')

@section('title', 'Quản lý Đơn đồ ăn - MovieMate Admin')
@section('page-title', 'Đơn đồ ăn')

@section('content')
    <div class="app-card border app-border rounded-2xl overflow-hidden shadow-lg">
        <div class="p-5 border-b app-border flex flex-col sm:flex-row justify-between gap-3 items-center">
            <div>
                <h2 class="text-lg font-bold">Danh sách đơn</h2>
                <p class="text-sm app-muted">Xem đơn hàng đồ ăn nhận tại rạp.</p>
            </div>
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <input type="text" class="app-input w-full sm:w-72 px-3 py-2 border app-border rounded-xl text-sm" placeholder="Tìm mã đơn, tên khách...">
            </div>
        </div>

        <div class="overflow-x-auto hide-scrollbar">
            <table class="w-full text-left border-collapse whitespace-nowrap">
                <thead>
                    <tr class="app-secondary text-xs uppercase tracking-wider app-muted border-b app-border">
                        <th class="px-5 py-3 font-semibold">Mã đơn</th>
                        <th class="px-5 py-3 font-semibold">Khách hàng</th>
                        <th class="px-5 py-3 font-semibold">Rạp nhận</th>
                        <th class="px-5 py-3 font-semibold text-right">Tổng tiền</th>
                        <th class="px-5 py-3 font-semibold">Trạng thái</th>
                        <th class="px-5 py-3 font-semibold text-center">Hành động</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border-color)] text-sm">
                    @forelse($orders as $order)
                        <tr class="hover:bg-brand-start/5 transition-colors">
                            <td class="px-5 py-4">
                                <div class="font-semibold">#{{ $order->id }}</div>
                                <div class="text-xs app-muted">{{ $order->created_at->format('d/m/Y H:i') }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="font-semibold">{{ $order->customer_name }}</div>
                                <div class="text-xs app-muted">{{ $order->customer_phone }}</div>
                            </td>
                            <td class="px-5 py-4">{{ optional($order->pickupCinema)->name ?? 'Chưa chọn' }}</td>
                            <td class="px-5 py-4 text-right font-bold">{{ number_format($order->total_amount,2) }}đ</td>
                            <td class="px-5 py-4">
                                <span class="inline-flex px-2 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $order->status === 'paid' ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning' }}">
                                    {{ ucfirst($order->status) }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <a href="{{ route('admin.food-orders.show', $order) }}" class="inline-flex items-center justify-center w-10 h-10 rounded-xl border app-border app-muted hover:app-text hover:border-brand-start transition-colors">
                                    <i class="ph-bold ph-eye text-base"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-6 text-center app-muted">Chưa có đơn đồ ăn nào.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-5 py-4 border-t app-border">
            {{ $orders->links() }}
        </div>
    </div>
@endsection
