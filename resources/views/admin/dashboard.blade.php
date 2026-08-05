@extends('layouts.admin')

@section('title', 'Tổng quan - MovieMate')
@section('page-title', 'Tổng quan')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Tổng quan</h1>
        <p class="admin-page-subtitle">Theo dõi hoạt động vận hành và kinh doanh của MovieMate.</p>
    </div>
</div>

<section aria-label="Chỉ số tổng quan" class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
    <article class="app-card rounded-[24px] border app-border p-6">
        <div class="flex items-center justify-between gap-4">
            <p class="text-sm app-muted">Tổng doanh thu</p>
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-start/10 text-brand-start"><i class="ph-bold ph-currency-circle-dollar text-xl"></i></span>
        </div>
        <p class="mt-3 text-3xl font-black text-brand-end">{{ number_format($metrics['totalRevenue'], 0, ',', '.') }} ₫</p>
        <p class="mt-2 text-xs app-muted">Giao dịch thành công đã xác minh</p>
    </article>

    <article class="app-card rounded-[24px] border app-border p-6">
        <div class="flex items-center justify-between gap-4">
            <p class="text-sm app-muted">Vé đã bán</p>
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-ai-start/10 text-ai-start"><i class="ph-bold ph-ticket text-xl"></i></span>
        </div>
        <p class="mt-3 text-3xl font-black app-text">{{ number_format($metrics['ticketsSold'], 0, ',', '.') }}</p>
        <p class="mt-2 text-xs app-muted">Số ghế thuộc booking đã thanh toán</p>
    </article>

    <article class="app-card rounded-[24px] border app-border p-6">
        <div class="flex items-center justify-between gap-4">
            <p class="text-sm app-muted">Người dùng</p>
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-500/10 text-blue-400"><i class="ph-bold ph-users-three text-xl"></i></span>
        </div>
        <p class="mt-3 text-3xl font-black app-text">{{ number_format($metrics['users'], 0, ',', '.') }}</p>
        <p class="mt-2 text-xs app-muted">Tất cả tài khoản trong hệ thống</p>
    </article>

    <article class="app-card rounded-[24px] border app-border p-6">
        <div class="flex items-center justify-between gap-4">
            <p class="text-sm app-muted">Phim đang chiếu</p>
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-success/10 text-success"><i class="ph-bold ph-film-slate text-xl"></i></span>
        </div>
        <p class="mt-3 text-3xl font-black text-success">{{ number_format($metrics['nowShowingMovies'], 0, ',', '.') }}</p>
        <p class="mt-2 text-xs app-muted">Phim có trạng thái đang chiếu</p>
    </article>
</section>

<div class="mt-8 grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
    <section class="app-card rounded-[28px] border app-border p-5 sm:p-6" aria-labelledby="revenue-chart-title">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 id="revenue-chart-title" class="text-xl font-black app-text">Doanh thu 7 ngày gần nhất</h2>
                <p class="mt-1 text-xs app-muted">Chỉ tính booking có thanh toán thành công đã xác minh</p>
            </div>
            <p class="text-sm font-bold text-brand-end">{{ number_format(collect($revenueChart)->sum('revenue'), 0, ',', '.') }} ₫</p>
        </div>

        @if($hasRevenueChartData)
            <div class="grid h-72 grid-cols-7 items-end gap-2 sm:gap-4" role="img" aria-label="Biểu đồ doanh thu bảy ngày gần nhất">
                @foreach($revenueChart as $day)
                    <div class="flex h-full min-w-0 flex-col items-center justify-end gap-2">
                        <span class="hidden max-w-full truncate text-[10px] font-semibold text-brand-end sm:block" title="{{ number_format($day['revenue'], 0, ',', '.') }} ₫">
                            {{ $day['revenue'] > 0 ? number_format($day['revenue'] / 1000, 0, ',', '.').'K' : '0' }}
                        </span>
                        <div class="flex h-52 w-full items-end rounded-xl bg-brand-start/5 p-1">
                            <div class="w-full rounded-lg bg-gradient-to-t from-brand-start to-brand-end" style="height: {{ $day['heightPercent'] }}%" title="{{ $day['date'] }}: {{ number_format($day['revenue'], 0, ',', '.') }} ₫"></div>
                        </div>
                        <div class="text-center leading-tight">
                            <p class="text-xs font-bold app-text">{{ $day['label'] }}</p>
                            <p class="text-[10px] app-muted">{{ $day['date'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="flex h-72 flex-col items-center justify-center rounded-2xl border border-dashed app-border text-center">
                <i class="ph ph-chart-bar text-4xl text-brand-start"></i>
                <p class="mt-3 font-bold app-text">Chưa có doanh thu trong 7 ngày gần đây</p>
                <p class="mt-1 text-sm app-muted">Biểu đồ sẽ cập nhật khi có thanh toán thành công.</p>
            </div>
        @endif
    </section>

    <section class="app-card rounded-[28px] border app-border p-5 sm:p-6" aria-labelledby="top-movies-title">
        <h2 id="top-movies-title" class="mb-6 text-xl font-black app-text">Phim bán chạy</h2>
        <div class="space-y-3">
            @forelse($topMovies as $movie)
                <article class="flex items-center justify-between gap-4 rounded-2xl app-secondary border app-border p-4">
                    <div class="min-w-0">
                        <p class="truncate font-bold app-text" title="{{ $movie->title }}">{{ $movie->title }}</p>
                        <p class="mt-1 text-sm app-muted">{{ number_format((int) $movie->tickets_sold, 0, ',', '.') }} vé đã bán</p>
                    </div>
                    <span class="shrink-0 font-black text-brand-end">#{{ $loop->iteration }}</span>
                </article>
            @empty
                <div class="flex min-h-64 flex-col items-center justify-center rounded-2xl border border-dashed app-border px-4 text-center">
                    <i class="ph ph-film-strip text-4xl text-brand-start"></i>
                    <p class="mt-3 font-bold app-text">Chưa có phim bán chạy</p>
                    <p class="mt-1 text-sm app-muted">Dữ liệu sẽ xuất hiện sau khi có vé được thanh toán.</p>
                </div>
            @endforelse
        </div>
    </section>
</div>

<section class="mt-8 app-card rounded-[28px] border app-border p-5 sm:p-6" aria-labelledby="recent-bookings-title">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 id="recent-bookings-title" class="text-xl font-black app-text">Đơn đặt vé gần đây</h2>
            <p class="mt-1 text-xs app-muted">Sáu booking mới nhất trong hệ thống</p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[860px] text-left text-sm">
            <thead class="app-muted">
                <tr class="border-b app-border">
                    <th class="py-4 pr-4 font-semibold">Mã vé</th>
                    <th class="px-4 font-semibold">Khách hàng</th>
                    <th class="px-4 font-semibold">Phim</th>
                    <th class="px-4 font-semibold">Rạp / phòng</th>
                    <th class="px-4 font-semibold">Tổng tiền</th>
                    <th class="pl-4 font-semibold">Trạng thái</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentBookings as $booking)
                    @php
                        $status = match ($booking->booking_status) {
                            'pending_payment' => ['label' => 'Chờ thanh toán', 'class' => 'bg-warning/10 text-warning'],
                            'paid' => ['label' => 'Đã thanh toán', 'class' => 'bg-success/10 text-success'],
                            'used' => ['label' => 'Đã sử dụng', 'class' => 'bg-blue-500/10 text-blue-400'],
                            'cancelled' => ['label' => 'Đã hủy', 'class' => 'bg-error/10 text-error'],
                            'expired' => ['label' => 'Hết hạn', 'class' => 'app-secondary app-muted'],
                            default => ['label' => 'Không xác định', 'class' => 'app-secondary app-muted'],
                        };
                    @endphp
                    <tr class="border-b app-border last:border-b-0">
                        <td class="py-4 pr-4 font-mono font-bold app-text">{{ $booking->booking_code }}</td>
                        <td class="px-4 app-text">{{ $booking->user?->name ?? 'Khách vãng lai' }}</td>
                        <td class="max-w-52 px-4"><span class="block truncate app-text" title="{{ $booking->showtime?->movie?->title ?? 'Không còn dữ liệu phim' }}">{{ $booking->showtime?->movie?->title ?? 'Không còn dữ liệu phim' }}</span></td>
                        <td class="px-4 app-muted">
                            {{ $booking->showtime?->cinema?->name ?? 'Chưa xác định' }}
                            <span class="block text-xs">{{ $booking->showtime?->room?->name ?? 'Chưa xác định phòng' }}</span>
                        </td>
                        <td class="px-4 font-bold text-brand-end">{{ number_format((int) $booking->total_amount, 0, ',', '.') }} ₫</td>
                        <td class="pl-4"><span class="inline-flex rounded-full px-3 py-1 text-xs font-bold {{ $status['class'] }}">{{ $status['label'] }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-12 text-center">
                            <i class="ph ph-ticket text-4xl text-brand-start"></i>
                            <p class="mt-3 font-bold app-text">Chưa có đơn đặt vé</p>
                            <p class="mt-1 text-sm app-muted">Các booking mới sẽ xuất hiện tại đây.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
