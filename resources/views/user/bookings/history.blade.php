@extends('layouts.user')

@section('title', 'Lịch sử đặt vé - MovieMate')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <aside class="lg:col-span-1">
            <div class="app-card border app-border rounded-3xl p-6 flex flex-col items-center text-center sticky top-24">
                <div class="w-20 h-20 rounded-full overflow-hidden mb-3 border-2 border-brand-start/30">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&background=FF3D57&color=fff&size=128" alt="Avatar" class="w-full h-full object-cover">
                </div>
                <h2 class="font-bold app-text mb-1">{{ Auth::user()->name }}</h2>
                <p class="text-xs text-ai-start font-bold mb-5">Hạng {{ Auth::user()->role->name ?? 'Khách' }}</p>

                <div class="w-full rounded-2xl border border-ai-start/30 bg-ai-start/10 px-4 py-3 mb-4 text-left">
                    <p class="text-xs app-muted">Thành viên {{ Auth::user()->membership_tier }}</p>
                    <p class="text-2xl font-extrabold text-ai-start">{{ number_format(Auth::user()->loyalty_points, 0, ',', '.') }}</p>
                    <p class="text-xs app-muted">điểm khả dụng</p>
                </div>
                <div class="w-full space-y-1 text-left">
                    <a href="{{ route('user.profile') }}" class="flex items-center gap-3 px-4 py-2.5 app-muted hover:app-text hover:bg-brand-start/5 rounded-xl font-medium transition-colors text-sm">
                        <i class="ph ph-user text-lg"></i> Thông tin cá nhân
                    </a>
                    <a href="{{ route('user.bookings.history') }}" class="flex items-center gap-3 px-4 py-2.5 bg-brand-start/10 text-brand-start rounded-xl font-bold border border-brand-start/20 text-sm">
                        <i class="ph-fill ph-ticket text-lg"></i> Lịch sử đặt vé
                    </a>
                    <a href="{{ route('user.loyalty.history') }}" class="flex items-center gap-3 px-4 py-2.5 app-muted hover:app-text hover:bg-brand-start/5 rounded-xl font-medium transition-colors text-sm">
                        <i class="ph ph-coins text-lg"></i> Lịch sử điểm
                    </a>
                    <a href="#" class="flex items-center gap-3 px-4 py-2.5 app-muted hover:app-text hover:bg-brand-start/5 rounded-xl font-medium transition-colors text-sm">
                        <i class="ph ph-star text-lg"></i> Đánh giá của tôi
                    </a>
                </div>
            </div>
        </aside>
        <section class="lg:col-span-3">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-3xl font-bold app-text">Lịch sử đặt vé</h1>
                    <p class="app-muted mt-1">Theo dõi vé đã đặt và mã QR của bạn.</p>
                </div>

                <form method="GET" action="{{ route('user.bookings.history') }}" class="flex gap-2">
                    <select name="status" class="app-input border app-border rounded-xl text-sm px-3 py-2">
                        <option value="">Tất cả</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Chờ thanh toán</option>
                        <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Chưa sử dụng</option>
                        <option value="used" {{ request('status') == 'used' ? 'selected' : '' }}>Đã sử dụng</option>
                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Đã hủy</option>
                        <option value="expired" {{ request('status') == 'expired' ? 'selected' : '' }}>Hết hạn</option>
                    </select>
                    <button type="submit" class="px-4 py-2 bg-brand-start text-white text-sm font-bold rounded-xl">Lọc</button>
                </form>
            </div>