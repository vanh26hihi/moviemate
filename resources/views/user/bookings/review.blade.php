@extends('layouts.user')

@section('title', 'Xác nhận đơn - MovieMate')
@section('suppress-global-validation-summary', '1')

@section('content')
@php
    $seatTypeLabels = ['normal' => 'Thường', 'vip' => 'VIP', 'couple' => 'Ghế đôi'];
    $checkoutDeadline = \Carbon\Carbon::createFromTimestamp((int) $draft['created_at'])
        ->addMinutes((int) config('booking.checkout_token_ttl_minutes', 15));
    $pendingMinutes = (int) config('booking.pending_ttl_minutes', 15);
    $defaultProvider = ($paymentProviders['vnpay'] ?? false)
        ? 'vnpay'
        : (($paymentProviders['payos'] ?? false) ? 'payos' : 'zalopay');
@endphp

<x-validation-summary class="mx-auto mt-6 max-w-7xl px-4 sm:px-6 lg:px-8" :errors="$errors" :except="['payment_method']" />

<main class="user-page-shell px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-5xl">
        <x-checkout-progress current="review" class="mb-8" />

        <section class="cinema-card rounded-3xl p-5 sm:p-8" aria-labelledby="review-title">
            <header class="flex flex-col gap-5 border-b pb-6 app-border sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.24em] text-brand-start">Bước 3/4 · Kiểm tra lần cuối</p>
                    <h1 id="review-title" class="mt-2 text-2xl font-extrabold app-text sm:text-3xl">Xác nhận đơn đặt vé</h1>
                    <p class="mt-2 app-muted">Vui lòng kiểm tra suất chiếu, ghế, đồ ăn, email và kênh thanh toán.</p>
                </div>
                <div class="rounded-2xl border border-warning/30 bg-warning/10 px-4 py-3 sm:max-w-xs" data-countdown-wrapper>
                    <p class="text-xs font-bold uppercase tracking-wide text-warning">Thời gian phiên đặt vé còn lại</p>
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
                            <div><dt class="app-muted">Địa chỉ</dt><dd class="mt-1 font-bold app-text">{{ $preview->showtime->cinema->address }}</dd></div>
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
                                    <span class="app-text">
                                        @if($seat['is_couple'])<strong>{{ $seat['label'] }}</strong>
                                        @else Ghế <strong>{{ $seat['seat_code'] }}</strong> · {{ $seatTypeLabels[$seat['type']] ?? \App\Support\StatusLabel::for('seat_type', $seat['type']) }}
                                        @endif
                                    </span>
                                    <strong class="whitespace-nowrap app-text">{{ number_format($seat['price'], 0, ',', '.') }} VNĐ</strong>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <section class="rounded-2xl border app-border p-5" aria-labelledby="food-summary-title">
                        <h2 id="food-summary-title" class="flex items-center gap-2 font-bold app-text"><i class="ph-fill ph-popcorn text-warning" aria-hidden="true"></i>Đồ ăn nhận tại rạp</h2>
                        <div class="mt-4 space-y-3">
                            @forelse($preview->prices->foodLines as $line)
                                <div class="flex items-start justify-between gap-3 text-sm">
                                    <span class="app-text">{{ $line->snapshotName }} <strong>× {{ $line->quantity }}</strong><span class="mt-0.5 block text-xs app-muted">{{ number_format($line->unitPrice, 0, ',', '.') }} VNĐ / phần</span></span>
                                    <strong class="whitespace-nowrap app-text">{{ number_format($line->lineTotal, 0, ',', '.') }} VNĐ</strong>
                                </div>
                            @empty
                                <p class="text-sm app-muted">Không chọn đồ ăn. Đây là đơn chỉ gồm vé xem phim.</p>
                            @endforelse
                        </div>
                    </section>
                </div>

                <aside class="self-start rounded-2xl app-secondary p-5 sm:p-6 lg:sticky lg:top-24" aria-labelledby="payment-summary-title">
                    <section class="mb-5 rounded-xl border app-border p-4" aria-labelledby="promotion-title">
                        <h2 id="promotion-title" class="text-sm font-bold app-text">Mã giảm giá</h2>
                        <form method="POST" action="{{ route('user.bookings.promotions') }}" class="mt-3 flex gap-2">@csrf<input type="hidden" name="action" value="apply"><label class="sr-only" for="discount-code">Mã giảm giá</label><input id="discount-code" name="code" maxlength="50" class="user-form-control uppercase" placeholder="Nhập mã"><button class="btn-secondary" type="submit">Áp dụng</button></form>
                        @error('promotion_code')<p class="mt-2 text-sm text-error" role="alert">{{ $message }}</p>@enderror
                        <div class="mt-3 space-y-2">@foreach($promotion->lines as $line)<div class="flex items-center justify-between gap-2 text-sm"><span><strong class="app-text">{{ $line['promotion']->code }}</strong><span class="block text-xs app-muted">{{ $line['promotion']->name }}</span></span><div class="flex items-center gap-2"><strong class="text-success">−{{ number_format($line['discount_amount'], 0, ',', '.') }} VNĐ</strong><form method="POST" action="{{ route('user.bookings.promotions') }}">@csrf<input type="hidden" name="action" value="remove"><input type="hidden" name="code" value="{{ $line['promotion']->code }}"><button type="submit" class="text-error" aria-label="Gỡ mã {{ $line['promotion']->code }}"><i class="ph ph-x"></i></button></form></div></div>@endforeach</div>
                    </section>
                    <div class="flex items-center justify-between gap-3">
                        <h2 id="payment-summary-title" class="font-bold app-text">Chi tiết thanh toán</h2>
                        <span class="rounded-lg bg-red-600 px-2.5 py-1 text-xs font-extrabold text-white">VNĐ</span>
                    </div>

                    <dl class="mt-5 space-y-3 text-sm">
                        <div class="flex justify-between gap-3"><dt class="app-muted">Tiền ghế</dt><dd class="font-bold app-text">{{ number_format($preview->prices->seatSubtotal, 0, ',', '.') }} VNĐ</dd></div>
                        <div class="flex justify-between gap-3"><dt class="app-muted">Tiền đồ ăn</dt><dd class="font-bold app-text">{{ number_format($preview->prices->foodSubtotal, 0, ',', '.') }} VNĐ</dd></div>
                        @if($promotion->discountAmount > 0)<div class="flex justify-between gap-3 text-success"><dt>Giảm giá</dt><dd class="font-bold">−{{ number_format($promotion->discountAmount, 0, ',', '.') }} VNĐ</dd></div>@endif
                        <div class="flex justify-between gap-3 border-t pt-4 app-border"><dt class="font-bold app-text">Tổng thanh toán</dt><dd class="text-xl font-extrabold text-brand-start">{{ number_format($promotion->finalAmount, 0, ',', '.') }} VNĐ</dd></div>
                    </dl>

                    <div class="mt-5 rounded-xl border app-border px-4 py-3">
                        <p class="text-xs app-muted">Email nhận vé</p>
                        <p class="mt-1 break-all text-sm font-bold app-text">{{ $draft['customer_email'] }}</p>
                    </div>

                    <div class="mt-5 rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm leading-relaxed text-warning" role="note">
                        <i class="ph-fill ph-warning-circle mr-1" aria-hidden="true"></i>
                        Sau khi xác nhận, ghế chỉ được giữ tối đa {{ $pendingMinutes }} phút trong lúc chờ thanh toán. Chỉ giao dịch được MovieMate xác minh mới xác nhận đơn đặt vé.
                    </div>

                    <div class="mt-6 flex flex-col gap-3">
                        <form method="POST" action="{{ route('user.bookings.confirm') }}" data-submit-once>
                            @csrf
                            <fieldset class="mb-4 space-y-3">
                                <legend class="mb-2 text-sm font-bold app-text">Chọn cổng thanh toán</legend>
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border app-border bg-white/5 p-3">
                                    <input type="radio" name="payment_method" value="vnpay" class="accent-red-600" @checked(old('payment_method', $defaultProvider) === 'vnpay') @disabled(!($paymentProviders['vnpay'] ?? false))>
                                    <i class="ph-bold ph-qr-code text-2xl text-red-500" aria-hidden="true"></i>
                                    <span><strong class="block app-text">Thanh toán bằng VNPAY</strong><small class="app-muted">Bạn sẽ được chuyển đến VNPAY để quét QR hoặc chọn ngân hàng.</small></span>
                                    @unless($paymentProviders['vnpay'] ?? false)<small class="ml-auto text-warning">Chưa cấu hình</small>@endunless
                                </label>
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border app-border bg-white/5 p-3">
                                    <input type="radio" name="payment_method" value="payos" class="accent-emerald-600" @checked(old('payment_method', $defaultProvider) === 'payos') @disabled(!($paymentProviders['payos'] ?? false))>
                                    <i class="ph-bold ph-bank text-2xl text-emerald-500" aria-hidden="true"></i>
                                    <span><strong class="block app-text">payOS — Chuyển khoản ngân hàng / VietQR</strong><small class="app-muted">Bạn sẽ được chuyển tới trang thanh toán bảo mật của payOS.</small></span>
                                    @unless($paymentProviders['payos'] ?? false)<small class="ml-auto text-warning">Chưa cấu hình</small>@endunless
                                </label>
                                @if($paymentProviders['zalopay'] ?? false)
                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border app-border bg-white/5 p-3">
                                        <input type="radio" name="payment_method" value="zalopay" class="accent-blue-600" @checked(old('payment_method', $defaultProvider) === 'zalopay')>
                                        <i class="ph-bold ph-wallet text-2xl text-blue-500" aria-hidden="true"></i>
                                        <span><strong class="block app-text">ZaloPay</strong><small class="app-muted">Kênh thanh toán nội bộ hiện có</small></span>
                                    </label>
                                @endif
                            </fieldset>
                            @error('payment_method')<p class="mb-3 text-sm text-error" role="alert">{{ $message }}</p>@enderror
                            <button type="submit" class="btn-primary w-full" data-loading-label="Đang chuyển đến cổng thanh toán…">
                                Tiếp tục thanh toán
                                <i class="ph-bold ph-arrow-square-out" aria-hidden="true"></i>
                            </button>
                            <p class="mt-2 text-center text-sm app-muted" data-submit-status aria-live="polite"></p>
                        </form>
                        <a href="{{ route('user.bookings.food') }}" class="btn-secondary w-full text-center"><i class="ph-bold ph-arrow-left" aria-hidden="true"></i>Quay lại đồ ăn</a>
                    </div>
                    <p class="mt-4 text-xs leading-relaxed app-muted"><i class="ph-fill ph-shield-check mr-1 text-success" aria-hidden="true"></i>Tổng tiền hiển thị được tính lại trên máy chủ; trang này không gửi tổng tiền từ trình duyệt.</p>
                </aside>
            </div>
        </section>
    </div>
</main>
@endsection
