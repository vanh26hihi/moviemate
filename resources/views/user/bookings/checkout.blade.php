@extends('layouts.user')

@section('title', 'Thanh toán - MovieMate')

<<<<<<< HEAD
@php
    $loyaltyPoints = app(\App\Services\LoyaltyPointService::class)->calculate($totalAmount);
    $subtotalAmount = $subtotalAmount ?? $totalAmount;
    $voucherSummary = $voucherSummary ?? ['voucher' => null, 'code' => null, 'discount' => 0, 'total' => $totalAmount];
    $selectedSeatQuery = collect($seatSummaries)->pluck('id')->join(',');
@endphp

@section('content')
=======
@section('content')
<section class="user-page-shell py-8 lg:py-10">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
        <a href="{{ route('user.bookings.selectSeat', $showtime) }}" class="btn-secondary mb-6 !px-4 !py-2 text-sm"><i class="ph ph-arrow-left"></i>Quay lại chọn ghế</a>

        <div class="mb-8 flex items-center justify-center gap-2 text-xs sm:justify-start sm:gap-4 sm:text-sm" aria-label="Tiến trình đặt vé">
            <div class="flex items-center gap-2 font-semibold text-brand-start"><span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-start text-white"><i class="ph-bold ph-check"></i></span><span class="hidden sm:inline">Chọn phim & suất</span></div>
            <div class="h-px w-8 bg-brand-start sm:w-12"></div>
            <div class="flex items-center gap-2 font-semibold text-brand-start"><span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-start text-white"><i class="ph-bold ph-check"></i></span><span class="hidden sm:inline">Chọn ghế</span></div>
            <div class="h-px w-8 bg-brand-start sm:w-12"></div>
            <div class="flex items-center gap-2 font-semibold text-brand-start" aria-current="step"><span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-start text-xs font-black text-white">3</span><span>Thanh toán</span></div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1.05fr_0.95fr] lg:gap-8">
            <article class="cinema-card overflow-hidden rounded-3xl">
                <div class="relative min-h-48 overflow-hidden border-b app-border p-6 sm:p-8">
                    @if($showtime->movie->cover_url)
                        <img src="{{ $showtime->movie->cover_url }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-20" loading="lazy">
                    @endif
                    <div class="absolute inset-0 bg-gradient-to-r from-[var(--app-card)] via-[var(--app-card)]/95 to-transparent"></div>
                    <div class="relative max-w-xl">
                        <p class="mb-2 text-xs font-extrabold uppercase tracking-[0.24em] text-brand-start">Thông tin đặt vé</p>
                        <h1 class="text-2xl font-extrabold app-text sm:text-3xl">{{ $showtime->movie->title }}</h1>
                        <div class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                            <p class="flex items-start gap-2 app-muted"><i class="ph-fill ph-buildings mt-0.5 text-brand-start"></i><span><strong class="block app-text">{{ $showtime->cinema->name }}</strong>{{ $showtime->room->name }}</span></p>
                            <p class="flex items-start gap-2 app-muted"><i class="ph-fill ph-calendar-blank mt-0.5 text-brand-start"></i><span><strong class="block app-text">{{ $showtime->show_date->format('d/m/Y') }}</strong>{{ \Carbon\Carbon::parse($showtime->show_time)->format('H:i') }}</span></p>
                        </div>
                    </div>
                </div>

                <div class="p-6 sm:p-8">
                    <div class="mb-4 flex items-center justify-between gap-4"><h2 class="text-lg font-extrabold app-text">Ghế đã chọn</h2><span class="rounded-full bg-brand-start/10 px-3 py-1 text-xs font-bold text-brand-start">{{ $seatSummaries->count() }} ghế</span></div>
                    <div class="space-y-3">
                        @foreach($seatSummaries as $seat)
                            <div class="flex items-center justify-between gap-4 rounded-2xl border app-border app-secondary px-4 py-3">
                                <div class="flex items-center gap-3"><span class="flex h-10 min-w-10 items-center justify-center rounded-xl bg-brand-start/10 px-2 font-black text-brand-start">{{ $seat['seat_code'] }}</span><div><p class="text-sm font-bold app-text">Ghế {{ $seat['type'] === 'vip' ? 'VIP' : ($seat['type'] === 'couple' ? 'đôi' : 'thường') }}</p><p class="text-xs app-muted">{{ $showtime->room->name }}</p></div></div>
                                <strong class="whitespace-nowrap app-text">{{ number_format($seat['price'], 0, ',', '.') }}đ</strong>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-6 flex items-end justify-between gap-4 border-t pt-5 app-border"><div><p class="text-sm app-muted">Tổng thanh toán</p><p class="mt-1 text-xs app-muted">Giá được xác nhận lại khi đặt vé</p></div><strong class="text-3xl font-extrabold text-brand-start">{{ number_format($totalAmount, 0, ',', '.') }}đ</strong></div>
                </div>
            </article>

            <form action="{{ route('user.bookings.store') }}" method="POST" class="cinema-card self-start rounded-3xl p-6 sm:p-8 lg:sticky lg:top-24">
                @csrf
                <input type="hidden" name="showtime_id" value="{{ $showtime->id }}">
                @foreach($seatSummaries as $seat)
                    <input type="hidden" name="seat_ids[]" value="{{ $seat['id'] }}">
                @endforeach

                <div class="mb-6"><p class="text-xs font-extrabold uppercase tracking-[0.24em] text-ai-start">Thanh toán an toàn</p><h2 class="mt-2 text-2xl font-extrabold app-text">Hoàn tất đặt vé</h2><p class="mt-2 text-sm app-muted">Vé điện tử sẽ được gửi tới email sau khi đặt thành công.</p></div>

                <div>
                    <label for="customer_email" class="mb-2 block text-sm font-semibold app-text">Email nhận vé</label>
                    <div class="relative"><i class="ph ph-envelope-simple absolute left-4 top-1/2 -translate-y-1/2 app-muted"></i><input id="customer_email" name="customer_email" type="email" required autocomplete="email" value="{{ old('customer_email', $user?->email) }}" class="user-form-control !pl-11" placeholder="ban@example.com"></div>
                    @error('customer_email')<p class="mt-2 text-xs font-semibold text-error">{{ $message }}</p>@enderror
                </div>

                <fieldset class="mt-6 space-y-3">
                    <legend class="mb-3 text-sm font-semibold app-text">Phương thức thanh toán</legend>
                    @foreach([
                        'fake' => ['Mô phỏng thanh toán', 'ph-check-circle', 'Xác nhận thanh toán ngay trong hệ thống'],
                        'counter' => ['Thanh toán tại quầy', 'ph-storefront', 'Thanh toán khi đến rạp'],
                        'vnpay' => ['VNPay mô phỏng', 'ph-qr-code', 'Luồng VNPay trong môi trường hiện tại'],
                    ] as $value => [$label, $icon, $description])
                        <label class="group flex cursor-pointer items-center gap-3 rounded-2xl border app-border app-secondary p-4 transition hover:border-brand-start/50">
                            <input type="radio" name="payment_method" value="{{ $value }}" @checked(old('payment_method', 'fake') === $value) class="peer h-4 w-4 text-brand-start focus:ring-brand-start">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-start/10 text-brand-start"><i class="ph-fill {{ $icon }} text-xl"></i></span>
                            <span class="min-w-0"><strong class="block text-sm app-text">{{ $label }}</strong><span class="block text-xs app-muted">{{ $description }}</span></span>
                        </label>
                    @endforeach
                    @error('payment_method')<p class="text-xs font-semibold text-error">{{ $message }}</p>@enderror
                </fieldset>

                <button type="submit" class="mt-7 inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-brand-start to-brand-end px-5 py-3.5 font-extrabold text-white shadow-lg shadow-brand-start/20 transition hover:-translate-y-0.5 hover:shadow-brand-start/35"><i class="ph-fill ph-lock-key"></i>Xác nhận và thanh toán</button>
                <p class="mt-4 text-center text-xs app-muted"><i class="ph-fill ph-shield-check mr-1 text-success"></i>Số tiền do backend MovieMate xác nhận.</p>
            </form>
        </div>
    </div>
</section>
@endsection
>>>>>>> 2085321f924f762f85bc22987b110ce9eaa68f44
