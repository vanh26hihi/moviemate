@extends('layouts.admin')

@section('title', 'Tổng quan hệ thống - MovieMate')
@section('page-title', 'Tổng quan hệ thống')

@section('content')
@php
    $weekdayNames = [
        \Carbon\CarbonInterface::SUNDAY => 'Chủ nhật',
        \Carbon\CarbonInterface::MONDAY => 'Thứ hai',
        \Carbon\CarbonInterface::TUESDAY => 'Thứ ba',
        \Carbon\CarbonInterface::WEDNESDAY => 'Thứ tư',
        \Carbon\CarbonInterface::THURSDAY => 'Thứ năm',
        \Carbon\CarbonInterface::FRIDAY => 'Thứ sáu',
        \Carbon\CarbonInterface::SATURDAY => 'Thứ bảy',
    ];
    $kpis = [
        [
            'label' => 'Tổng doanh thu',
            'value' => number_format($metrics['totalRevenue'], 0, ',', '.').' VNĐ',
            'context' => 'Từ giao dịch đã xác minh',
            'icon' => 'ph-currency-circle-dollar',
            'iconClass' => 'bg-success/10 text-success',
            'valueClass' => 'text-success',
            'route' => null,
            'permission' => null,
        ],
        [
            'label' => 'Tổng vé đã bán',
            'value' => number_format($metrics['ticketsSold'], 0, ',', '.'),
            'context' => 'Lượt ghế vào rạp đã phân bổ',
            'icon' => 'ph-ticket',
            'iconClass' => 'bg-brand-start/10 text-brand-start',
            'valueClass' => 'text-brand-start',
            'route' => null,
            'permission' => null,
        ],
        [
            'label' => 'Người dùng',
            'value' => number_format($metrics['users'], 0, ',', '.'),
            'context' => 'Tài khoản đã đăng ký',
            'icon' => 'ph-users',
            'iconClass' => 'bg-ai-start/10 text-ai-start',
            'valueClass' => 'text-ai-start',
            'route' => 'admin.users.index',
            'permission' => 'users.view',
        ],
        [
            'label' => 'Phim đang chiếu',
            'value' => number_format($metrics['nowShowingMovies'], 0, ',', '.'),
            'context' => 'Phim đang phục vụ khán giả',
            'icon' => 'ph-film-slate',
            'iconClass' => 'bg-warning/10 text-warning',
            'valueClass' => 'text-warning',
            'route' => 'admin.movies.index',
            'permission' => 'movies.view',
        ],
        [
            'label' => 'Suất chiếu hôm nay',
            'value' => number_format($metrics['showtimesToday'], 0, ',', '.'),
            'context' => 'Suất chiếu đang hoạt động',
            'icon' => 'ph-video-camera',
            'iconClass' => 'bg-blue-500/10 text-blue-400',
            'valueClass' => 'text-blue-400',
            'route' => 'admin.showtimes.index',
            'permission' => 'showtimes.view',
        ],
    ];
    $bestMovie = $topMovies->first();
    $leadingTickets = max(1, (int) ($bestMovie?->tickets_sold ?? 0));
@endphp

<header class="admin-page-header items-start">
    <div class="min-w-0">
        <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-start">MovieMate Cinema</p>
        <h1 class="admin-page-title mt-2">Tổng quan hệ thống</h1>
        <p class="admin-page-subtitle max-w-2xl">Theo dõi nhanh hoạt động kinh doanh và vận hành của MovieMate Cinema.</p>
        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs app-muted">
            <span class="inline-flex items-center gap-1.5">
                <i class="ph ph-calendar-blank" aria-hidden="true"></i>
                {{ $weekdayNames[$generatedAt->dayOfWeek] }}, {{ $generatedAt->format('d/m/Y') }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <i class="ph ph-clock" aria-hidden="true"></i>
                Dữ liệu cập nhật lúc {{ $generatedAt->format('H:i') }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <i class="ph ph-user-circle" aria-hidden="true"></i>
                {{ auth()->user()->name }}
            </span>
        </div>
    </div>
    @can('showtimes.create')
        <a href="{{ route('admin.showtimes.create') }}" class="btn-primary min-h-11 shrink-0">
            <i class="ph-bold ph-plus" aria-hidden="true"></i>
            Tạo suất chiếu
        </a>
    @endcan
</header>

<section aria-label="Chỉ số tổng quan" class="grid grid-cols-1 gap-4 sm:gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
    @foreach($kpis as $kpi)
        @php
            $canOpenKpi = $kpi['route']
                && \Illuminate\Support\Facades\Route::has($kpi['route'])
                && auth()->user()->hasPermission($kpi['permission']);
            $cardClasses = 'group app-card rounded-2xl border app-border p-5 shadow-sm transition duration-200 motion-reduce:transition-none hover:-translate-y-0.5 hover:shadow-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-start';
        @endphp

        @if($canOpenKpi)
            <a href="{{ route($kpi['route']) }}" class="{{ $cardClasses }}" aria-label="{{ $kpi['label'] }}: {{ $kpi['value'] }}">
        @else
            <article class="{{ $cardClasses }}">
        @endif
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-[0.12em] app-muted">{{ $kpi['label'] }}</p>
                    <p class="mt-3 truncate text-2xl font-black sm:text-3xl {{ $kpi['valueClass'] }}" title="{{ $kpi['value'] }}">{{ $kpi['value'] }}</p>
                </div>
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $kpi['iconClass'] }}">
                    <i class="ph-bold {{ $kpi['icon'] }} text-xl" aria-hidden="true"></i>
                </span>
            </div>
            <p class="mt-3 text-xs leading-relaxed app-muted">{{ $kpi['context'] }}</p>
        @if($canOpenKpi)
            </a>
        @else
            </article>
        @endif
    @endforeach
</section>

<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
    <section class="app-card rounded-2xl border app-border p-5 sm:p-6 lg:col-span-2" aria-labelledby="revenue-chart-title">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="revenue-chart-title" class="text-xl font-black app-text">Doanh thu 7 ngày qua</h2>
                <p class="mt-1 text-xs app-muted">Chỉ tính các giao dịch đã được xác minh thành công.</p>
            </div>
            <div class="text-right">
                <p class="text-xs app-muted">Tổng trong kỳ</p>
                <p class="mt-1 font-black text-brand-end">{{ number_format(collect($revenueChart)->sum('revenue'), 0, ',', '.') }} VNĐ</p>
            </div>
        </div>

        @unless($hasRevenueChartData)
            <div class="mt-5 rounded-xl border border-warning/20 bg-warning/5 px-4 py-3 text-sm text-warning" role="status">
                Chưa có doanh thu được xác minh trong 7 ngày qua.
            </div>
        @endunless

        <div class="mt-6 overflow-x-auto pb-2" role="region" aria-label="Biểu đồ doanh thu có thể cuộn ngang">
            <div class="relative h-72 min-w-[640px]">
                <div class="pointer-events-none absolute inset-x-0 top-0 h-52" aria-hidden="true">
                    <span class="absolute inset-x-0 top-0 border-t app-border"></span>
                    <span class="absolute inset-x-0 top-1/3 border-t app-border"></span>
                    <span class="absolute inset-x-0 top-2/3 border-t app-border"></span>
                    <span class="absolute inset-x-0 bottom-0 border-t app-border"></span>
                </div>
                <div class="absolute inset-0 grid grid-cols-7 gap-3 sm:gap-5" role="list" aria-label="Doanh thu từng ngày">
                    @foreach($revenueChart as $day)
                        <div class="group flex h-full min-w-0 flex-col items-center" role="listitem">
                            <div
                                class="flex h-52 w-full items-end justify-center rounded-t-xl px-1.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-start {{ $day['isToday'] ? 'bg-ai-start/10' : '' }}"
                                tabindex="0"
                                title="{{ $day['date'] }}: {{ number_format($day['revenue'], 0, ',', '.') }} VNĐ"
                                aria-label="{{ $day['label'] }}, ngày {{ $day['date'] }}: {{ number_format($day['revenue'], 0, ',', '.') }} VNĐ"
                            >
                                @if($day['revenue'] > 0)
                                    <div
                                        class="relative w-full max-w-14 rounded-t-lg bg-gradient-to-t from-brand-start to-brand-end shadow-lg shadow-brand-start/10 transition-opacity group-hover:opacity-90 motion-reduce:transition-none"
                                        style="height: {{ $day['heightPercent'] }}%"
                                        aria-hidden="true"
                                    ></div>
                                @else
                                    <span class="mb-px h-1 w-1 rounded-full app-muted bg-current" aria-hidden="true"></span>
                                @endif
                            </div>
                            <p class="mt-3 text-xs font-black {{ $day['isToday'] ? 'text-brand-start' : 'app-text' }}">{{ $day['label'] }}</p>
                            <p class="mt-1 text-[10px] app-muted">{{ $day['date'] }}</p>
                            @if($day['isToday'])
                                <span class="mt-1 rounded-full bg-brand-start/10 px-2 py-0.5 text-[9px] font-bold text-brand-start">Hôm nay</span>
                            @endif
                            <span class="sr-only">Doanh thu chính xác {{ number_format($day['revenue'], 0, ',', '.') }} VNĐ</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <aside class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-ai-start to-blue-600 p-6 text-white shadow-xl shadow-ai-start/15" aria-labelledby="insight-title">
        <i class="ph-fill ph-sparkle absolute -right-7 -top-8 text-[9rem] text-white/10" aria-hidden="true"></i>
        <div class="relative">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 backdrop-blur-sm">
                <i class="ph-fill ph-magic-wand text-2xl" aria-hidden="true"></i>
            </span>
            <h2 id="insight-title" class="mt-5 text-xl font-black">Phân tích nhanh MovieMate</h2>
            <p class="mt-1 text-xs text-white/75">Nhận định tự động từ dữ liệu vận hành đã xác minh.</p>

            <div class="mt-6 space-y-4">
                <section class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur-sm">
                    <h3 class="text-sm font-bold">Phim bán chạy nhất</h3>
                    @if($bestMovie)
                        <p class="mt-2 text-sm leading-relaxed text-white/85">
                            <strong class="text-white">{{ $bestMovie->title }}</strong> đang dẫn đầu với
                            {{ number_format((int) $bestMovie->tickets_sold, 0, ',', '.') }} vé bán ra trong 7 ngày gần đây.
                        </p>
                    @else
                        <p class="mt-2 text-sm leading-relaxed text-white/85">Chưa có đủ dữ liệu bán vé để xếp hạng phim.</p>
                    @endif
                </section>

                <section class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur-sm">
                    <h3 class="text-sm font-bold">Vận hành hôm nay</h3>
                    <p class="mt-2 text-sm leading-relaxed text-white/85">
                        Hôm nay có {{ number_format($metrics['showtimesToday'], 0, ',', '.') }} suất chiếu và
                        {{ number_format($operations['pendingBookings'], 0, ',', '.') }} đơn đang chờ thanh toán.
                    </p>
                </section>
            </div>
        </div>
    </aside>
</div>

<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
    <section class="app-card rounded-2xl border app-border p-5 sm:p-6" aria-labelledby="top-movies-title">
        <div class="mb-5">
            <h2 id="top-movies-title" class="text-xl font-black app-text">Top phim bán chạy</h2>
            <p class="mt-1 text-xs app-muted">Theo giao dịch đã xác minh trong 7 ngày gần đây.</p>
        </div>

        <div class="space-y-3">
            @forelse($topMovies as $movie)
                @php
                    $progress = max(4, (int) round(((int) $movie->tickets_sold / $leadingTickets) * 100));
                @endphp
                <article class="group flex items-center gap-3 rounded-2xl border app-border app-secondary p-3">
                    <span class="w-7 shrink-0 text-center text-sm font-black {{ $loop->first ? 'text-warning' : 'app-muted' }}">#{{ $loop->iteration }}</span>
                    <div class="h-16 w-12 shrink-0 overflow-hidden rounded-xl border app-border app-card">
                        @if($movie->poster_url)
                            <img src="{{ $movie->poster_url }}" alt="Ảnh áp phích phim {{ $movie->title }}" class="h-full w-full object-cover" loading="lazy">
                        @else
                            <div class="admin-media-fallback h-full w-full text-[10px]" aria-label="Chưa có ảnh áp phích">MM</div>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        @can('movies.view')
                            <a href="{{ route('admin.movies.show', $movie) }}" class="block truncate font-bold app-text hover:text-brand-start focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-start" title="{{ $movie->title }}">{{ $movie->title }}</a>
                        @else
                            <p class="truncate font-bold app-text" title="{{ $movie->title }}">{{ $movie->title }}</p>
                        @endcan
                        <p class="mt-1 text-xs app-muted">
                            {{ number_format((int) $movie->tickets_sold, 0, ',', '.') }} vé ·
                            {{ number_format((int) $movie->booking_count, 0, ',', '.') }} đơn
                        </p>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full app-card" aria-hidden="true">
                            <div class="h-full rounded-full bg-gradient-to-r from-brand-start to-brand-end" style="width: {{ $progress }}%"></div>
                        </div>
                    </div>
                    <p class="shrink-0 text-right text-xs font-black text-brand-end">{{ number_format((int) $movie->revenue, 0, ',', '.') }}<span class="block font-semibold app-muted">VNĐ</span></p>
                </article>
            @empty
                <div class="flex min-h-72 flex-col items-center justify-center rounded-2xl border border-dashed app-border px-5 text-center">
                    <i class="ph ph-film-strip text-4xl text-brand-start" aria-hidden="true"></i>
                    <p class="mt-3 font-bold app-text">Chưa có dữ liệu bán vé để xếp hạng phim.</p>
                </div>
            @endforelse
        </div>
    </section>

    <section class="app-card rounded-2xl border app-border p-5 sm:p-6" aria-labelledby="recent-bookings-title">
        <div class="mb-5">
            <h2 id="recent-bookings-title" class="text-xl font-black app-text">Đơn đặt vé gần đây</h2>
            <p class="mt-1 text-xs app-muted">Sáu đơn mới nhất trong hệ thống.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[680px] text-left text-sm">
                <thead class="app-muted">
                    <tr class="border-b app-border">
                        <th scope="col" class="pb-3 pr-3 font-semibold">Mã đặt vé</th>
                        <th scope="col" class="px-3 pb-3 font-semibold">Khách hàng</th>
                        <th scope="col" class="px-3 pb-3 font-semibold">Tổng tiền</th>
                        <th scope="col" class="pb-3 pl-3 font-semibold">Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentBookings as $booking)
                        @php
                            $statusLabel = \App\Support\StatusLabel::for('booking_admin', $booking->booking_status);
                            $statusClass = match ($booking->booking_status) {
                                'paid' => 'bg-success/10 text-success',
                                'used' => 'bg-blue-500/10 text-blue-400',
                                'pending_payment' => 'bg-warning/10 text-warning',
                                'cancelled', 'expired' => 'bg-error/10 text-error',
                                default => 'app-secondary app-muted',
                            };
                            $showtimeLabel = $booking->showtime
                                ? $booking->showtime->show_date->format('d/m/Y').' '.\Carbon\Carbon::parse($booking->showtime->show_time)->format('H:i')
                                : null;
                        @endphp
                        <tr class="border-b app-border last:border-b-0">
                            <td class="py-4 pr-3 align-top">
                                <span class="font-mono font-bold app-text">{{ $booking->booking_code }}</span>
                                <span class="mt-1 block max-w-48 truncate text-xs app-muted" title="{{ $booking->showtime?->movie?->title ?? 'Không còn dữ liệu phim' }}">
                                    {{ $booking->showtime?->movie?->title ?? 'Không còn dữ liệu phim' }}
                                </span>
                                @if($showtimeLabel)
                                    <span class="mt-1 block text-[10px] app-muted">{{ $showtimeLabel }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-4 align-top app-text">{{ $booking->user?->name ?? 'Khách đặt vé' }}</td>
                            <td class="px-3 py-4 align-top font-bold text-brand-end">{{ number_format((int) $booking->total_amount, 0, ',', '.') }} VNĐ</td>
                            <td class="py-4 pl-3 align-top">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold {{ $statusClass }}">{{ $statusLabel }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-14 text-center">
                                <i class="ph ph-ticket text-4xl text-brand-start" aria-hidden="true"></i>
                                <p class="mt-3 font-bold app-text">Chưa có đơn đặt vé.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
