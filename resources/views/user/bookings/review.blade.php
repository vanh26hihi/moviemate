@extends('layouts.user')

@section('title', 'Xác nhận đơn - MovieMate')

@section('content')
@php
    $seatTypeLabels = ['normal' => 'Thường', 'vip' => 'VIP', 'couple' => 'Ghế đôi'];
    $checkoutDeadline = \Carbon\Carbon::createFromTimestamp((int) $draft['created_at'])
        ->addMinutes((int) config('booking.checkout_token_ttl_minutes', 15));
    $pendingMinutes = (int) config('booking.pending_ttl_minutes', 15);
@endphp

<main class="user-page-shell px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-5xl">
        <x-checkout-progress current="review" class="mb-8" />

        <section class="cinema-card rounded-3xl p-5 sm:p-8" aria-labelledby="review-title">
            <header class="flex flex-col gap-5 border-b pb-6 app-border sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.24em] text-brand-start">Bước 3/4 · Kiểm tra lần cuối</p>
                    <h1 id="review-title" class="mt-2 text-2xl font-extrabold app-text sm:text-3xl">Xác nhận đơn đặt vé</h1>
                    <p class="mt-2 app-muted">Vui lòng kiểm tra suất chiếu, ghế, đồ ăn và email trước khi sang ZaloPay.</p>
                </div>
                <div class="rounded-2xl border border-warning/30 bg-warning/10 px-4 py-3 sm:max-w-xs" data-countdown-wrapper>
                    <p class="text-xs font-bold uppercase tracking-wide text-warning">Thời gian phiên checkout còn lại</p>
                    <p class="mt-1 text-xl font-extrabold app-text" data-countdown="{{ $checkoutDeadline->toIso8601String() }}" data-expired-label="Đã hết thời gian">--:--</p>
                    <p class="mt-1 text-xs app-muted">Nếu hết giờ, bạn cần chọn lại ghế.</p>
                </div>
            </header>

            <div class="mt-7 grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
                <div class="space-y-6">
                    <section class="rounded-2xl border app-border p-5" aria-labelledby="showtime-summary-title">
                        <h2 id="showtime-summary-title" class="flex items-center gap-2 font-bold app-text"><i class="ph-fill ph-film-strip text-brand-start" aria-hidden="true"></i>Suất chiếu</h2>
                        <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                            <div><dt class="app-muted">Phim</dt><dd class="mt-1 font-bold app-text">{{ $preview->showtime->movie->title }}</dd></div>
                            <div><dt class="app-muted">Ngày giờ</dt><dd class="mt-1 font-bold app-text">{{ $preview->showtime->show_date->format('d/m/Y') }} · {{ \Carbon\Carbon::parse($preview->showtime->show_time)->format('H:i') }}</dd></div>
                            <div><dt class="app-muted">Rạp</dt><dd class="mt-1 font-bold app-text">{{ $preview->showtime->cinema->name }}</dd></div>
                            <div><dt class="app-muted">Phòng</dt><dd class="mt-1 font-bold app-text">{{ $preview->showtime->room->name }}</dd></div>
                        </dl>
                    </section>

                    <section class="rounded-2xl border app-border p-5" aria-labelledby="seat-summary-title">
                        <div class="flex items-center justify-between gap-3">
                            <h2 id="seat-summary-title" class="flex items-center gap-2 font-bold app-text"><i class="ph-fill ph-armchair text-brand-start" aria-hidden="true"></i>Ghế đã chọn</h2>
                            <span class="rounded-full bg-brand-start/10 px-3 py-1 text-xs font-bold text-brand-start">{{ $preview->seats->count() }} ghế</span>
                        </div>
                        <div class="mt-4 space-y-3">
                            @foreach($preview->seatSummaries() as $seat)
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <span class="app-text">Ghế <strong>{{ $seat['seat_code'] }}</strong> · {{ $seatTypeLabels[$seat['type']] ?? ucfirst($seat['type']) }}</span>
                                    <strong class="whitespace-nowrap app-text">{{ number_format($seat['price'], 0, ',', '.') }} VND</strong>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <section class="rounded-2xl border app-border p-5" aria-labelledby="food-summary-title">
                        <h2 id="food-summary-title" class="flex items-center gap-2 font-bold app-text"><i class="ph-fill ph-popcorn text-warning" aria-hidden="true"></i>Đồ ăn nhận tại rạp</h2>
                        <div class="mt-4 space-y-3">
                            @forelse($preview->prices->foodLines as $line)
                                <div class="flex items-start justify-between gap-3 text-sm">
                                    <span class="app-text">{{ $line->snapshotName }} <strong>× {{ $line->quantity }}</strong><span class="mt-0.5 block text-xs app-muted">{{ number_format($line->unitPrice, 0, ',', '.') }} VND / phần</span></span>
                                    <strong class="whitespace-nowrap app-text">{{ number_format($line->lineTotal, 0, ',', '.') }} VND</strong>
                                </div>
                            @empty
                                <p class="text-sm app-muted">Không chọn đồ ăn. Đây là đơn chỉ gồm vé xem phim.</p>
                            @endforelse
                        </div>
                    </section>
                </div>

                <aside class="self-start rounded-2xl app-secondary p-5 sm:p-6 lg:sticky lg:top-24" aria-labelledby="payment-summary-title">
                    <div class="flex items-center justify-between gap-3">
                        <h2 id="payment-summary-title" class="font-bold app-text">Chi tiết thanh toán</h2>
                        <span class="rounded-lg bg-blue-600 px-2.5 py-1 text-xs font-extrabold text-white">ZaloPay</span>
                    </div>

                    <dl class="mt-5 space-y-3 text-sm">
                        <div class="flex justify-between gap-3"><dt class="app-muted">Tiền ghế</dt><dd class="font-bold app-text">{{ number_format($preview->prices->seatSubtotal, 0, ',', '.') }} VND</dd></div>
                        <div class="flex justify-between gap-3"><dt class="app-muted">Tiền đồ ăn</dt><dd class="font-bold app-text">{{ number_format($preview->prices->foodSubtotal, 0, ',', '.') }} VND</dd></div>
                        <div class="flex justify-between gap-3 border-t pt-4 app-border"><dt class="font-bold app-text">Tổng thanh toán</dt><dd class="text-xl font-extrabold text-brand-start">{{ number_format($preview->prices->grandTotal, 0, ',', '.') }} VND</dd></div>
                    </dl>

                    <div class="mt-5 rounded-xl border app-border px-4 py-3">
                        <p class="text-xs app-muted">Email nhận vé</p>
                        <p class="mt-1 break-all text-sm font-bold app-text">{{ $draft['customer_email'] }}</p>
                    </div>

                    <div class="mt-5 rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm leading-relaxed text-warning" role="note">
                        <i class="ph-fill ph-warning-circle mr-1" aria-hidden="true"></i>
                        Sau khi xác nhận, ghế chỉ được giữ tối đa {{ $pendingMinutes }} phút trong lúc chờ thanh toán. Chỉ giao dịch được MovieMate xác minh mới phát hành vé.
                    </div>

                    <div class="mt-6 flex flex-col gap-3">
                        <form method="POST" action="{{ route('user.bookings.confirm') }}" data-submit-once>
                            @csrf
                            <button type="submit" class="btn-primary w-full" data-loading-label="Đang chuyển đến ZaloPay…">
                                Thanh toán bằng ZaloPay
                                <i class="ph-bold ph-arrow-square-out" aria-hidden="true"></i>
                            </button>
                            <p class="mt-2 text-center text-sm app-muted" data-submit-status aria-live="polite"></p>
                        </form>
                        <a href="{{ route('user.bookings.food') }}" class="btn-secondary w-full text-center"><i class="ph-bold ph-arrow-left" aria-hidden="true"></i>Quay lại đồ ăn</a>
                    </div>
                    <p class="mt-4 text-xs leading-relaxed app-muted"><i class="ph-fill ph-shield-check mr-1 text-success" aria-hidden="true"></i>Tổng tiền hiển thị được tính lại trên máy chủ; trang này không gửi total từ trình duyệt.</p>
                </aside>
            </div>
        </section>
    </div>
</main>
@endsection
