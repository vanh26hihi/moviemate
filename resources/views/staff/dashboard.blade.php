@extends('layouts.staff')

@section('title', 'Bàn làm việc nhân viên - MovieMate')
@section('page-title', 'Bàn làm việc')

@section('content')
@php
    $cards = [
        [
            'label' => 'Đơn đã thanh toán hôm nay',
            'value' => $stats['paid_bookings_today'],
            'note' => $stats['tickets_sold_today'].' vé vật lý đã phát hành',
            'icon' => 'ph-receipt',
            'href' => route('staff.sales.index', ['date' => $now->format('Y-m-d'), 'status' => 'paid']),
        ],
        [
            'label' => 'Vé chưa in',
            'value' => $stats['waiting_print'],
            'note' => 'Mở hàng đợi để in đủ theo từng ghế',
            'icon' => 'ph-printer',
            'href' => route('staff.prints.index'),
        ],
        [
            'label' => 'In cần xử lý',
            'value' => $stats['print_attention'],
            'note' => 'Lần in lỗi hoặc cần quyền in lại',
            'icon' => 'ph-warning',
            'href' => route('staff.prints.index'),
        ],
        [
            'label' => 'Đơn của tôi đang giữ',
            'value' => $stats['pending_counter'],
            'note' => 'Tiếp tục hoặc hủy trước khi hết hạn',
            'icon' => 'ph-hourglass',
            'href' => route('staff.sales.index', ['date' => $now->format('Y-m-d'), 'status' => 'pending_payment', 'channel' => 'counter']),
        ],
    ];
    $showtimeStates = [
        'showing' => ['label' => 'Đang chiếu', 'class' => 'bg-success/10 text-success'],
        'doors_open' => ['label' => 'Sắp bắt đầu', 'class' => 'bg-warning/10 text-warning'],
        'upcoming' => ['label' => 'Sắp chiếu', 'class' => 'bg-blue-500/10 text-blue-400'],
    ];
@endphp
<div class="space-y-7">
    <header class="admin-page-header">
        <div><p class="text-sm font-bold text-brand-start">{{ $now->format('H:i · d/m/Y') }}</p><h1 class="admin-page-title">Bàn làm việc hôm nay</h1><p class="admin-page-subtitle">Bạn đang thao tác tại {{ $cinema?->name ?? 'chi nhánh chưa được phân công' }}.</p></div>
        @if($cinema)<div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row"><a href="{{ route('staff.counter.index') }}" class="btn-primary"><i class="ph ph-storefront"></i>Bán vé mới</a><a href="{{ route('staff.tickets.index') }}" class="btn-secondary"><i class="ph ph-qr-code"></i>Quét QR / Tra cứu đơn</a></div>@endif
    </header>

    @if(!$cinema)
        <x-empty-state title="Bạn chưa được phân công chi nhánh" description="Liên hệ quản lý để được phân công chi nhánh trước khi thực hiện nghiệp vụ." icon="ph-map-pin" />
    @else
        <section class="cinema-card p-5 sm:p-6" aria-labelledby="staff-attention-title">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div><h2 id="staff-attention-title" class="text-xl font-black app-heading">Việc cần xử lý ngay</h2><p class="mt-1 text-sm app-muted">Ưu tiên đơn sắp hết thời gian giữ, lỗi in và tài liệu chưa in.</p></div>
                <a href="{{ route('staff.tickets.index') }}" class="text-sm font-bold text-brand-start">Tra cứu đơn khác <i class="ph ph-arrow-right" aria-hidden="true"></i></a>
            </div>
            @if($attentionItems->isEmpty())
                <div class="mt-5 flex items-start gap-3 rounded-xl bg-success/10 p-4 text-success"><i class="ph ph-check-circle text-2xl" aria-hidden="true"></i><div><p class="font-extrabold">Chưa có việc khẩn cấp</p><p class="mt-1 text-sm">Các đơn đang giữ và hàng đợi in hiện không cần nhân viên xử lý ngay.</p></div></div>
            @else
                <div class="mt-5 divide-y app-border">
                    @foreach($attentionItems as $item)
                        @php
                            $booking = $item['booking'];
                        @endphp
                        <article class="grid gap-3 py-4 first:pt-0 last:pb-0 md:grid-cols-[auto_1fr_auto] md:items-center">
                            <div class="flex h-11 w-11 items-center justify-center rounded-xl {{ $item['type'] === 'payment' ? 'bg-warning/10 text-warning' : 'bg-error/10 text-error' }}"><i class="ph {{ $item['type'] === 'payment' ? 'ph-clock-countdown' : 'ph-printer' }} text-2xl" aria-hidden="true"></i></div>
                            <div class="min-w-0">
                                @if($item['type'] === 'payment')
                                    <p class="font-extrabold app-text">Đơn quầy đang giữ ghế</p>
                                    <p class="mt-1 text-sm app-muted"><span class="font-mono font-bold app-text">{{ $booking->booking_code }}</span> · {{ $booking->movie_title }} · {{ $booking->seat_codes }}</p>
                                    <p class="mt-2 text-sm font-bold text-warning">Còn <span data-countdown="{{ $item['expires_at']?->toIso8601String() }}" data-expired-label="Đã hết thời gian giữ">--:--</span>@if($item['payment_provider']) · Giao dịch {{ \App\Support\PaymentPresentation::providerLabel($item['payment_provider']) }} {{ in_array($item['payment_status'], [\App\Models\Payment::STATUS_PENDING, \App\Models\Payment::STATUS_PROCESSING, \App\Models\Payment::STATUS_UNRESOLVED], true) ? 'đang chờ' : 'cần tiếp tục' }}@endif</p>
                                @elseif($item['type'] === 'print_attention')
                                    <p class="font-extrabold app-text">Lần in cần xử lý</p>
                                    <p class="mt-1 text-sm app-muted"><span class="font-mono font-bold app-text">{{ $booking->booking_code }}</span> · {{ $booking->movie_title }} · {{ $item['count'] }} vé cần kiểm tra lý do hoặc quyền in lại.</p>
                                @else
                                    <p class="font-extrabold app-text">Đơn đã thanh toán còn vé chưa in</p>
                                    <p class="mt-1 text-sm app-muted"><span class="font-mono font-bold app-text">{{ $booking->booking_code }}</span> · {{ $booking->movie_title }} · {{ $item['count'] }} vé vật lý chưa in.</p>
                                @endif
                            </div>
                            @if($item['type'] === 'payment')
                                <a class="btn-secondary justify-center" href="{{ route('staff.counter.review', $booking) }}">Tiếp tục đơn</a>
                            @else
                                <a class="btn-secondary justify-center" href="{{ route('staff.tickets.operations', $booking) }}">Mở vận hành in</a>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Tình hình vận hành hôm nay">
            @foreach($cards as $card)
                <a href="{{ $card['href'] }}" class="cinema-card group block p-5 transition hover:-translate-y-0.5 hover:border-brand-start/50" aria-label="{{ $card['label'] }}: {{ $card['value'] }}. Mở màn hình xử lý.">
                    <div class="flex items-start justify-between gap-3"><i class="ph {{ $card['icon'] }} text-2xl text-brand-start" aria-hidden="true"></i><i class="ph ph-arrow-up-right text-lg app-muted transition group-hover:text-brand-start" aria-hidden="true"></i></div>
                    <p class="mt-3 text-3xl font-black app-heading">{{ $card['value'] }}</p><p class="mt-1 text-sm font-bold app-text">{{ $card['label'] }}</p><p class="mt-2 text-xs app-muted">{{ $card['note'] }}</p>
                </a>
            @endforeach
        </section>

        <section class="cinema-card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b app-border p-5"><div><h2 class="text-xl font-extrabold app-heading">Suất chiếu đang diễn ra và sắp tới</h2><p class="mt-1 text-sm app-muted">Ưu tiên các suất còn liên quan đến công việc bán vé tại {{ $cinema->name }}.</p></div><a href="{{ route('staff.counter.index') }}" class="text-sm font-bold text-brand-start">Mở toàn bộ lịch bán vé <i class="ph ph-arrow-right" aria-hidden="true"></i></a></div>
            @if($showtimes->isEmpty())
                <div class="p-6"><x-empty-state title="Không còn suất chiếu cần xử lý hôm nay" description="Hôm nay chưa có suất chiếu hoặc mọi suất đã kết thúc." icon="ph-calendar-check" /></div>
            @else
                <div class="divide-y app-border">
                    @foreach($showtimes as $showtime)
                        @php
                            $startsAt = \Carbon\CarbonImmutable::parse($showtime->show_date->format('Y-m-d').' '.$showtime->show_time, $cinema->timezone ?: config('cinema.timezone'));
                            $endsAt = $startsAt->addMinutes((int) ($showtime->movie->duration ?: 90));
                            $stateKey = $now->greaterThanOrEqualTo($startsAt) ? 'showing' : ($now->greaterThanOrEqualTo($startsAt->subMinutes(30)) ? 'doors_open' : 'upcoming');
                            $state = $showtimeStates[$stateKey];
                            $minutesUntil = max(0, (int) ceil($now->diffInMinutes($startsAt, false)));
                            $timeNote = $stateKey === 'showing' ? 'Kết thúc '.$endsAt->format('H:i') : ($minutesUntil < 60 ? 'Bắt đầu sau '.$minutesUntil.' phút' : 'Bắt đầu lúc '.$startsAt->format('H:i'));
                            $remainingSeats = max(0, (int) $showtime->operational_seats_count - (int) $showtime->sold_seats_count);
                            $totalSeats = (int) $showtime->operational_seats_count;
                        @endphp
                        <article class="grid gap-4 p-5 md:grid-cols-[6rem_1fr_auto] md:items-center">
                            <div><p class="text-2xl font-black app-heading">{{ $startsAt->format('H:i') }}</p><span class="mt-1 inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $state['class'] }}">{{ $state['label'] }}</span></div>
                            <div><h3 class="font-extrabold app-text">{{ $showtime->movie->title }}</h3><p class="mt-1 text-sm app-muted">{{ $showtime->room->name }} · Loại phòng: {{ $showtime->room->room_type_label }} · Định dạng trình chiếu: {{ $showtime->presentationFormat?->name ?? 'Không xác định' }}</p><p class="mt-2 text-sm font-bold app-text">{{ $timeNote }} · Còn bán {{ $remainingSeats }}/{{ $totalSeats }} ghế</p></div>
                            @if($startsAt->isFuture())<a class="btn-secondary justify-center" href="{{ route('staff.counter.seats',$showtime) }}">Chọn ghế</a>@else<span class="text-sm font-bold app-muted">Đã đóng bán vé</span>@endif
                        </article>
                    @endforeach
                </div>
            @endif
            @if($completedShowtimes->isNotEmpty())
                <details class="border-t app-border">
                    <summary class="cursor-pointer px-5 py-4 text-sm font-bold app-muted hover:text-brand-start">{{ $completedShowtimes->count() }} suất đã kết thúc hôm nay</summary>
                    <div class="grid gap-2 border-t app-border p-5 sm:grid-cols-2">
                        @foreach($completedShowtimes as $showtime)
                            @php
                                $completedStartsAt = \Carbon\CarbonImmutable::parse($showtime->show_date->format('Y-m-d').' '.$showtime->show_time, $cinema->timezone ?: config('cinema.timezone'));
                                $completedEndsAt = $completedStartsAt->addMinutes((int) ($showtime->movie->duration ?: 90));
                            @endphp
                            <div class="rounded-xl app-secondary px-4 py-3"><p class="font-bold app-text">{{ $completedStartsAt->format('H:i') }} · {{ $showtime->movie->title }}</p><p class="mt-1 text-xs app-muted">{{ $showtime->room->name }} · Đã kết thúc {{ $completedEndsAt->format('H:i') }}</p></div>
                        @endforeach
                    </div>
                </details>
            @endif
        </section>
    @endif
</div>
@endsection
