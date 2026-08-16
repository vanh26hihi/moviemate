@extends('layouts.admin')

@php
    $dashboardAccess = app(\App\Services\CinemaAccessService::class);
    $isGlobalDashboard = $dashboardAccess->hasGlobalAccess(auth()->user()) && $scope->selectedCinemaId === null;
    $dashboardCinema = $scope->cinemas->first();
    $money = fn (int $value): string => number_format($value, 0, ',', '.').' ₫';
    $needsPaymentHelp = $attention['total'] > 0;
    $printActionTotal = $ticketOperations['unprinted'] + $ticketOperations['printFailed'] + $ticketOperations['printWaiting'];
    $needsPrintHelp = $printActionTotal > 0;
    $cards = [
        ['label' => 'Doanh thu hôm nay', 'value' => $money($summary['revenue']), 'note' => 'Tiền đã thu hoặc xác minh', 'icon' => 'ph-currency-circle-dollar', 'color' => 'text-success'],
        ['label' => 'Đơn đã thanh toán', 'value' => number_format($summary['paidBookings'], 0, ',', '.'), 'note' => 'Một bằng chứng thanh toán / đơn', 'icon' => 'ph-receipt', 'color' => 'text-warning'],
        ['label' => 'Vé / chỗ đã bán', 'value' => number_format($summary['logicalTickets'], 0, ',', '.').' / '.number_format($summary['physicalSeats'], 0, ',', '.'), 'note' => 'Ghế đôi tính một vé, hai chỗ', 'icon' => 'ph-ticket', 'color' => 'text-brand-start'],
        ['label' => 'Suất chiếu hôm nay', 'value' => number_format($summary['showtimes'], 0, ',', '.'), 'note' => 'Không gồm suất đã hủy', 'icon' => 'ph-video-camera', 'color' => 'text-blue-400'],
    ];
    $statePresentation = [
        'showing' => ['label' => 'Đang chiếu', 'class' => 'bg-success/10 text-success', 'time' => 'Kết thúc'],
        'cleaning' => ['label' => 'Đang vệ sinh', 'class' => 'bg-warning/10 text-warning', 'time' => 'Sẵn sàng'],
        'upcoming' => ['label' => 'Sắp chiếu', 'class' => 'bg-blue-500/10 text-blue-400', 'time' => 'Bắt đầu'],
    ];
@endphp

@section('title', ($isGlobalDashboard ? 'Tổng quan hệ thống' : 'Tổng quan chi nhánh').' - MovieMate')
@section('page-title', $isGlobalDashboard ? 'Tổng quan hệ thống' : 'Tổng quan chi nhánh')

@section('content')
<header class="admin-page-header items-start">
    <div>
        <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-start">Trung tâm điều hành hôm nay</p>
        <h1 class="admin-page-title mt-2 break-words">{{ $isGlobalDashboard ? 'Tổng quan hệ thống' : 'Tổng quan — '.($dashboardCinema?->name ?? 'Chưa chọn chi nhánh') }}</h1>
        <p class="admin-page-subtitle">Việc đang diễn ra, việc sắp tới và các điểm cần xử lý trong ngày {{ $scope->from->format('d/m/Y') }}.</p>
    </div>
    <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
        @if(!$isGlobalDashboard && $dashboardCinema)
            <a class="btn-secondary" href="{{ route('admin.cinemas.show', $dashboardCinema) }}"><i class="ph ph-buildings"></i>Mở Branch 360</a>
        @endif
        @can('reports.view')
            <a class="btn-secondary" href="{{ route('admin.reports.index', $filters) }}"><i class="ph ph-chart-line-up"></i>Xem báo cáo hôm nay</a>
        @endcan
    </div>
</header>

<section aria-label="Chỉ số điều hành hôm nay" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    @foreach($cards as $card)
        <article class="app-card rounded-2xl border app-border p-5">
            <div class="flex items-start justify-between gap-3">
                <div><p class="text-xs font-bold uppercase tracking-wide app-muted">{{ $card['label'] }}</p><p class="mt-3 text-2xl font-black {{ $card['color'] }}">{{ $card['value'] }}</p></div>
                <i class="ph {{ $card['icon'] }} text-2xl {{ $card['color'] }}" aria-hidden="true"></i>
            </div>
            <p class="mt-3 text-xs app-muted">{{ $card['note'] }}</p>
        </article>
    @endforeach
</section>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <section class="app-card rounded-2xl border app-border p-5 sm:p-6 xl:col-span-2" aria-labelledby="operations-timeline-title">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="operations-timeline-title" class="text-xl font-black app-text">Vận hành phòng hôm nay</h2>
                <p class="mt-1 text-xs app-muted">Chỉ hiện phòng đang chiếu, đang vệ sinh hoặc có suất sắp bắt đầu.</p>
            </div>
            @can('showtimes.view')<a class="text-sm font-bold text-brand-start" href="{{ route('admin.showtimes.index', ['show_date' => $scope->from->toDateString()]) }}">Mở lịch vận hành <i class="ph ph-arrow-right"></i></a>@endcan
        </div>
        <dl class="mt-5 grid grid-cols-3 gap-3">
            <div class="app-secondary rounded-xl p-3"><dt class="text-xs app-muted">Đang chiếu</dt><dd class="mt-1 text-xl font-black text-success">{{ $timelineStats['showing'] }}</dd></div>
            <div class="app-secondary rounded-xl p-3"><dt class="text-xs app-muted">Đang vệ sinh</dt><dd class="mt-1 text-xl font-black text-warning">{{ $timelineStats['cleaning'] }}</dd></div>
            <div class="app-secondary rounded-xl p-3"><dt class="text-xs app-muted">Sắp chiếu</dt><dd class="mt-1 text-xl font-black text-blue-400">{{ $timelineStats['upcoming'] }}</dd></div>
        </dl>
        <div class="mt-5 grid gap-3 md:grid-cols-2">
            @forelse($operationalShowtimes as $showtime)
                @php($state = $statePresentation[$showtime['operationalState']])
                <article class="rounded-xl app-secondary p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0"><p class="truncate font-bold app-text">{{ $showtime['movie'] }}</p><p class="mt-1 text-xs app-muted">{{ $showtime['cinema'] }} · Phòng {{ $showtime['room'] }}</p></div>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold {{ $state['class'] }}">{{ $state['label'] }}</span>
                    </div>
                    <p class="mt-3 text-sm font-bold app-text">
                        {{ $state['time'] }}
                        {{ $showtime['operationalState'] === 'showing' ? $showtime['end']->format('H:i') : ($showtime['operationalState'] === 'cleaning' ? $showtime['cleaningUntil']->format('H:i') : $showtime['start']->format('H:i')) }}
                    </p>
                    <p class="mt-1 text-xs app-muted">{{ $showtime['logicalTickets'] }} vé · {{ $showtime['physicalSeats'] }} chỗ đã bán</p>
                </article>
            @empty
                <div class="rounded-xl border border-dashed app-border p-8 text-center app-muted md:col-span-2">
                    <i class="ph ph-calendar-check text-3xl" aria-hidden="true"></i>
                    <p class="mt-2 font-bold app-text">Hiện không còn phòng nào cần theo dõi</p>
                    <p class="mt-1 text-sm">Hôm nay chưa có suất chiếu hoặc mọi suất đã hoàn tất.</p>
                </div>
            @endforelse
        </div>
    </section>

    <aside class="app-card rounded-2xl border app-border p-5 sm:p-6" aria-labelledby="attention-title">
        <h2 id="attention-title" class="text-xl font-black app-text">Việc cần xử lý</h2>
        <p class="mt-1 text-xs app-muted">Ưu tiên ngoại lệ; không trộn với phân tích kinh doanh.</p>
        <div class="mt-5 space-y-3">
            <article class="rounded-xl border {{ $needsPaymentHelp ? 'border-warning/40 bg-warning/5' : 'app-border app-secondary' }} p-4">
                <div class="flex items-center justify-between gap-3"><p class="font-bold app-text">Đối soát thanh toán</p><strong class="{{ $needsPaymentHelp ? 'text-warning' : 'text-success' }}">{{ $attention['total'] }}</strong></div>
                <p class="mt-2 text-xs app-muted">{{ $attention['unresolved'] }} chưa xác định · {{ $attention['review'] }} cần xem xét</p>
                @can('payments.reconcile')<a class="mt-3 inline-flex text-sm font-bold text-brand-start" href="{{ route('admin.payment-reconciliation.index') }}">Mở đối soát <i class="ph ph-arrow-right ml-1"></i></a>@endcan
            </article>
            <article class="rounded-xl border {{ $needsPrintHelp ? 'border-warning/40 bg-warning/5' : 'app-border app-secondary' }} p-4">
                <div class="flex items-center justify-between gap-3"><p class="font-bold app-text">In vé tại quầy</p><strong class="{{ $needsPrintHelp ? 'text-warning' : 'text-success' }}">{{ $printActionTotal }}</strong></div>
                <p class="mt-2 text-xs app-muted">{{ $ticketOperations['unprinted'] }} chưa in · {{ $ticketOperations['printFailed'] }} lỗi · {{ $ticketOperations['printWaiting'] }} đang/chờ in lại</p>
                @can('tickets.print')<a class="mt-3 inline-flex text-sm font-bold text-brand-start" href="{{ route('staff.prints.index') }}">Mở hàng đợi in <i class="ph ph-arrow-right ml-1"></i></a>@endcan
            </article>
        </div>
        @if(!$needsPaymentHelp && !$needsPrintHelp)
            <p class="mt-4 rounded-xl bg-success/10 p-3 text-sm font-bold text-success"><i class="ph ph-check-circle mr-1"></i>Chưa có việc khẩn cấp cần xử lý.</p>
        @endif
    </aside>
</div>

<section class="app-card mt-6 rounded-2xl border app-border p-5 sm:p-6" aria-labelledby="quick-actions-title">
    <div><h2 id="quick-actions-title" class="text-xl font-black app-text">Lối tắt vận hành</h2><p class="mt-1 text-xs app-muted">Đi thẳng tới đúng màn hình để xử lý công việc.</p></div>
    <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @can('showtimes.view')<a class="rounded-xl app-secondary p-4 font-bold app-text transition hover:text-brand-start" href="{{ route('admin.showtimes.index', ['show_date' => $scope->from->toDateString()]) }}"><i class="ph ph-calendar-dots mr-2 text-brand-start"></i>Lịch vận hành hôm nay</a>@endcan
        @can('bookings.view')<a class="rounded-xl app-secondary p-4 font-bold app-text transition hover:text-brand-start" href="{{ route('admin.bookings.index') }}"><i class="ph ph-ticket mr-2 text-brand-start"></i>Đơn đặt vé</a>@endcan
        @can('tickets.lookup')<a class="rounded-xl app-secondary p-4 font-bold app-text transition hover:text-brand-start" href="{{ route('staff.tickets.index') }}"><i class="ph ph-printer mr-2 text-brand-start"></i>Tra cứu & in đơn</a>@endcan
        @can('reports.view')<a class="rounded-xl app-secondary p-4 font-bold app-text transition hover:text-brand-start" href="{{ route('admin.reports.index') }}"><i class="ph ph-chart-line-up mr-2 text-brand-start"></i>Phân tích báo cáo</a>@endcan
    </div>
</section>

<p class="mt-5 text-xs app-muted">Cập nhật lúc {{ $generatedAt->format('d/m/Y H:i') }}. Doanh thu dùng bằng chứng thanh toán có thẩm quyền; lịch phòng dùng giờ địa phương của từng chi nhánh.</p>
@endsection
