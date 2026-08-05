@extends('layouts.user')

@section('title', 'Chọn đồ ăn - MovieMate')

@section('content')
@php
    $maxFoodQuantity = (int) config('booking.max_food_quantity', 20);
@endphp

<main class="user-page-shell px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-6xl">
        <x-checkout-progress current="food" class="mb-8" />

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
            <form method="POST" action="{{ route('user.bookings.food.store') }}" class="cinema-card rounded-3xl p-5 sm:p-8" data-food-picker data-submit-once>
                @csrf
                <header>
                    <p class="text-xs font-bold uppercase tracking-[0.24em] text-brand-start">Bước 2/4 · Tùy chọn</p>
                    <h1 class="mt-2 text-2xl font-extrabold app-text sm:text-3xl">Thêm đồ ăn</h1>
                    <p class="mt-2 leading-relaxed app-muted">Chọn trước để nhận tại {{ $preview->showtime->cinema->name }}, hoặc bỏ qua nếu bạn chỉ cần vé xem phim.</p>
                </header>

                <div class="mt-7">
                    <label for="customer_email" class="mb-2 block text-sm font-semibold app-text">Email nhận vé <span class="text-error" aria-hidden="true">*</span></label>
                    <input
                        id="customer_email"
                        name="customer_email"
                        type="email"
                        required
                        autocomplete="email"
                        value="{{ old('customer_email', $draft['customer_email']) }}"
                        class="user-form-control"
                        placeholder="ban@example.com"
                        @error('customer_email') aria-invalid="true" aria-describedby="customer-email-error" @enderror
                    >
                    <p class="mt-2 text-xs app-muted">MovieMate sẽ gửi thông tin vé đến địa chỉ này sau khi thanh toán được xác minh.</p>
                    @error('customer_email')<p id="customer-email-error" class="mt-2 text-sm font-semibold text-error" role="alert">{{ $message }}</p>@enderror
                    @error('food_items')<p class="mt-2 text-sm font-semibold text-error" role="alert" aria-live="assertive">{{ $message }}</p>@enderror
                </div>

                <fieldset class="mt-7">
                    <legend class="text-base font-bold app-text">Thực đơn đang phục vụ</legend>
                    <p class="mt-1 text-sm app-muted">Mỗi món tối đa {{ $maxFoodQuantity }} phần.</p>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        @forelse($foods as $index => $food)
                            @php
                                $selected = collect($draft['food_items'])->firstWhere('food_id', $food->id);
                                $quantity = (int) old("food_items.$index.quantity", $selected['quantity'] ?? 0);
                            @endphp
                            <article class="rounded-2xl border app-border app-secondary p-4" data-food-card data-unit-price="{{ (int) $food->price }}">
                                <div class="flex min-h-16 items-start justify-between gap-3">
                                    <div>
                                        <h2 class="font-bold app-text">{{ $food->name }}</h2>
                                        <p class="mt-1 text-sm font-semibold text-brand-start">{{ number_format((int) $food->price, 0, ',', '.') }} VNĐ</p>
                                    </div>
                                    <span class="rounded-full bg-brand-start/10 px-2.5 py-1 text-[0.65rem] font-bold uppercase tracking-wide text-brand-start">Đang bán</span>
                                </div>

                                <input type="hidden" name="food_items[{{ $index }}][food_id]" value="{{ $food->id }}">
                                <div class="mt-4 flex items-center justify-between gap-3">
                                    <label for="food-quantity-{{ $food->id }}" class="text-sm font-semibold app-text">Số lượng</label>
                                    <div class="flex items-center gap-2" role="group" aria-label="Số lượng {{ $food->name }}">
                                        <button type="button" class="checkout-quantity-button" data-quantity-decrease aria-label="Giảm số lượng {{ $food->name }}">
                                            <i class="ph-bold ph-minus" aria-hidden="true"></i>
                                        </button>
                                        <input
                                            id="food-quantity-{{ $food->id }}"
                                            type="number"
                                            inputmode="numeric"
                                            name="food_items[{{ $index }}][quantity]"
                                            min="0"
                                            max="{{ $maxFoodQuantity }}"
                                            step="1"
                                            value="{{ $quantity }}"
                                            class="user-form-control !w-16 !px-2 text-center font-bold"
                                            data-food-quantity
                                        >
                                        <button type="button" class="checkout-quantity-button" data-quantity-increase aria-label="Tăng số lượng {{ $food->name }}">
                                            <i class="ph-bold ph-plus" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                                <p class="mt-3 text-right text-xs app-muted">Tạm tính: <strong class="app-text" data-food-line-total>{{ number_format((int) $food->price * $quantity, 0, ',', '.') }} VNĐ</strong></p>
                            </article>
                        @empty
                            <div class="rounded-2xl border border-dashed app-border p-6 text-center sm:col-span-2" data-food-empty>
                                <i class="ph ph-popcorn text-4xl text-brand-start" aria-hidden="true"></i>
                                <p class="mt-3 font-bold app-text">Hiện chưa có món khả dụng</p>
                                <p class="mt-1 text-sm app-muted">Bạn vẫn có thể tiếp tục với lựa chọn chỉ mua vé.</p>
                            </div>
                        @endforelse
                    </div>
                </fieldset>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <button type="submit" name="checkout_action" value="confirm_food" class="btn-primary flex-1" data-loading-label="Đang lưu lựa chọn…">
                        Tiếp tục xác nhận
                        <i class="ph-bold ph-arrow-right" aria-hidden="true"></i>
                    </button>
                    <button type="submit" name="checkout_action" value="skip_food" class="btn-secondary flex-1" data-loading-label="Đang bỏ qua…">
                        <i class="ph ph-fast-forward" aria-hidden="true"></i>
                        Bỏ qua đồ ăn
                    </button>
                </div>
                <p class="mt-3 text-center text-sm app-muted" data-submit-status aria-live="polite"></p>
            </form>

            <aside class="cinema-card self-start rounded-3xl p-5 sm:p-6 lg:sticky lg:top-24" aria-labelledby="food-summary-title">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-ai-start">Tóm tắt đơn</p>
                <h2 id="food-summary-title" class="mt-2 text-xl font-bold app-text">{{ $preview->showtime->movie->title }}</h2>
                <p class="mt-1 text-sm app-muted">{{ $preview->showtime->show_date->format('d/m/Y') }} · {{ \Carbon\Carbon::parse($preview->showtime->show_time)->format('H:i') }}</p>
                <p class="mt-1 text-sm app-muted">{{ $preview->showtime->cinema->name }} · {{ $preview->showtime->room->name }}</p>

                <div class="mt-5 space-y-3">
                    @foreach($preview->seatSummaries() as $seat)
                        <div class="flex justify-between gap-3 text-sm">
                            <span class="app-text">{{ $seat['is_couple'] ? $seat['label'] : 'Ghế '.$seat['seat_code'] }}</span>
                            <strong class="app-text">{{ number_format($seat['price'], 0, ',', '.') }} VNĐ</strong>
                        </div>
                    @endforeach
                </div>

                <dl class="mt-5 space-y-3 border-t pt-4 app-border">
                    <div class="flex justify-between gap-3 text-sm"><dt class="app-muted">Tiền ghế</dt><dd class="font-bold app-text">{{ number_format($preview->prices->seatSubtotal, 0, ',', '.') }} VNĐ</dd></div>
                    <div class="flex justify-between gap-3 text-sm"><dt class="app-muted">Đồ ăn tạm tính</dt><dd class="font-bold app-text" data-food-subtotal aria-live="polite">{{ number_format($preview->prices->foodSubtotal, 0, ',', '.') }} VNĐ</dd></div>
                    <div class="flex justify-between gap-3 border-t pt-3 app-border"><dt class="font-bold app-text">Tổng tạm tính</dt><dd class="text-xl font-extrabold text-brand-start" data-food-grand-total data-seat-subtotal="{{ $preview->prices->seatSubtotal }}">{{ number_format($preview->prices->grandTotal, 0, ',', '.') }} VNĐ</dd></div>
                </dl>
                <p class="mt-4 text-xs leading-relaxed app-muted"><i class="ph-fill ph-shield-check mr-1 text-success" aria-hidden="true"></i>Đây chỉ là tạm tính trên giao diện. Máy chủ sẽ xác nhận lại món, số lượng và tổng tiền.</p>
            </aside>
        </div>
    </div>
</main>
@endsection
