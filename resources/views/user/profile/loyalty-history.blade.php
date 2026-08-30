@extends('layouts.user')

@section('title', 'Lịch sử điểm - MovieMate')

@php
    $typeLabels = [
        'earn' => ['label' => 'Cộng điểm', 'class' => 'bg-success/10 text-success border-success/25', 'icon' => 'ph-plus-circle'],
        'reverse' => ['label' => 'Hoàn điểm', 'class' => 'bg-error/10 text-error border-error/25', 'icon' => 'ph-arrow-counter-clockwise'],
        'adjustment' => ['label' => 'Điều chỉnh', 'class' => 'bg-ai-start/10 text-ai-start border-ai-start/25', 'icon' => 'ph-sliders-horizontal'],
        'redeem' => ['label' => 'Đổi điểm', 'class' => 'bg-brand-start/10 text-brand-start border-brand-start/25', 'icon' => 'ph-gift'],
    ];

    $filterOptions = [
        '' => 'Tất cả',
        'earn' => 'Cộng điểm',
        'reverse' => 'Hoàn điểm',
        'adjustment' => 'Điều chỉnh',
        'redeem' => 'Đổi điểm',
    ];
@endphp

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <aside class="lg:col-span-1">
                <div class="app-card border app-border rounded-2xl p-6 flex flex-col items-center text-center sticky top-24">
                    <div class="relative w-20 h-20 rounded-full overflow-hidden mb-3 border-2 border-brand-start">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode($user->name) }}&background=FF3D57&color=fff&size=128"
                             alt="{{ $user->name }}" class="w-full h-full object-cover">
                    </div>
                    <h2 class="text-lg font-bold app-heading mb-0.5">{{ $user->name }}</h2>
                    <p class="text-xs text-ai-start font-bold mb-5">Hạng {{ $user->membership_tier }}</p>

                    <div class="w-full rounded-2xl border border-ai-start/30 bg-ai-start/10 px-4 py-3 mb-4 text-left">
                        <p class="text-xs app-muted">Điểm khả dụng</p>
                        <p class="text-2xl font-extrabold text-ai-start">{{ number_format($user->loyalty_points, 0, ',', '.') }}</p>
                    </div>

                    <div class="w-full space-y-1 text-left">
                        <a href="{{ route('user.profile') }}" class="flex items-center gap-3 px-4 py-2.5 app-text-muted hover:app-text hover:bg-brand-start/5 rounded-xl font-medium transition-colors text-sm">
                            <i class="ph ph-user text-lg"></i> Thông tin cá nhân
                        </a>
                        <a href="{{ route('user.bookings.history') }}" class="flex items-center gap-3 px-4 py-2.5 app-text-muted hover:app-text hover:bg-brand-start/5 rounded-xl font-medium transition-colors text-sm">
                            <i class="ph ph-ticket text-lg"></i> Lịch sử đặt vé
                        </a>
                        <a href="{{ route('user.loyalty.history') }}" class="flex items-center gap-3 px-4 py-2.5 bg-brand-start/10 text-brand-start rounded-xl font-bold border border-brand-start/20 text-sm">
                            <i class="ph-fill ph-coins text-lg"></i> Lịch sử điểm
                        </a>
                    </div>
                </div>
            </aside>

            <section class="lg:col-span-3 space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold app-text">Lịch sử điểm</h1>
                        <p class="app-muted mt-1">Theo dõi điểm cộng từ đặt vé, điểm bị hoàn khi hủy vé và các lần đổi điểm.</p>
                    </div>

                    <form method="GET" action="{{ route('user.loyalty.history') }}" class="flex gap-2">
                        <select name="type" class="app-input border app-border rounded-xl text-sm px-3 py-2">
                            @foreach($filterOptions as $value => $label)
                                <option value="{{ $value }}" {{ (string) $selectedType === (string) $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="px-4 py-2 bg-brand-start text-white text-sm font-bold rounded-xl">Lọc</button>
                    </form>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div class="app-card border app-border rounded-2xl p-4">
                        <p class="text-xs app-muted mb-1">Điểm khả dụng</p>
                        <p class="text-2xl font-extrabold text-ai-start">{{ number_format($summary['available_points'], 0, ',', '.') }}</p>
                    </div>
                    <div class="app-card border app-border rounded-2xl p-4">
                        <p class="text-xs app-muted mb-1">Điểm lên hạng</p>
                        <p class="text-2xl font-extrabold app-text">{{ number_format($summary['lifetime_points'], 0, ',', '.') }}</p>
                    </div>
                    <div class="app-card border app-border rounded-2xl p-4">
                        <p class="text-xs app-muted mb-1">Tổng điểm cộng</p>
                        <p class="text-2xl font-extrabold text-success">{{ number_format($summary['earned_points'], 0, ',', '.') }}</p>
                    </div>
                    <div class="app-card border app-border rounded-2xl p-4">
                        <p class="text-xs app-muted mb-1">Tổng điểm trừ</p>
                        <p class="text-2xl font-extrabold text-error">{{ number_format($summary['used_points'], 0, ',', '.') }}</p>
                    </div>
                </div>

                <div class="app-card border app-border rounded-3xl overflow-hidden">
                    <div class="hidden md:grid grid-cols-[130px_130px_1fr_140px] gap-4 px-5 py-3 border-b app-border text-xs font-bold app-muted uppercase">
                        <span>Ngày</span>
                        <span>Loại</span>
                        <span>Nội dung</span>
                        <span class="text-right">Điểm</span>
                    </div>

                    <div class="divide-y app-border">
                        @forelse($transactions as $transaction)
                            @php
                                $meta = $typeLabels[$transaction->type] ?? ['label' => ucfirst($transaction->type), 'class' => 'app-secondary app-text app-border', 'icon' => 'ph-dots-three-circle'];
                                $movieTitle = $transaction->booking?->showtime?->movie?->title;
                                $points = (int) $transaction->points;
                            @endphp

                            <article class="grid grid-cols-1 md:grid-cols-[130px_130px_1fr_140px] gap-3 md:gap-4 px-5 py-4">
                                <div>
                                    <p class="app-text font-bold">{{ $transaction->created_at?->format('d/m/Y') }}</p>
                                    <p class="app-muted text-xs">{{ $transaction->created_at?->format('H:i') }}</p>
                                </div>

                                <div>
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-xs font-bold {{ $meta['class'] }}">
                                        <i class="ph {{ $meta['icon'] }}"></i>
                                        {{ $meta['label'] }}
                                    </span>
                                </div>

                                <div class="min-w-0">
                                    <p class="app-text font-semibold">{{ $transaction->description ?: 'Giao dịch điểm MovieMate' }}</p>
                                    @if($transaction->booking)
                                        <p class="app-muted text-xs mt-1">
                                            Mã vé {{ $transaction->booking->booking_code }}
                                            @if($movieTitle)
                                                · {{ $movieTitle }}
                                            @endif
                                        </p>
                                    @endif
                                </div>

                                <div class="md:text-right">
                                    <p class="text-lg font-extrabold {{ $points >= 0 ? 'text-success' : 'text-error' }}">
                                        {{ $points >= 0 ? '+' : '' }}{{ number_format($points, 0, ',', '.') }}
                                    </p>
                                </div>
                            </article>
                        @empty
                            <div class="p-10 text-center">
                                <div class="w-16 h-16 rounded-2xl bg-ai-start/10 text-ai-start flex items-center justify-center mx-auto mb-4">
                                    <i class="ph ph-coins text-3xl"></i>
                                </div>
                                <p class="app-muted">Chưa có giao dịch điểm nào.</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                {{ $transactions->links() }}
            </section>
        </div>
    </div>
@endsection
