@extends('layouts.user')

@section('title', 'Chọn đồ ăn - MovieMate')

@section('content')
<main class="user-page-shell mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
    <nav class="mb-8 flex flex-wrap items-center gap-3 text-sm" aria-label="Tiến trình checkout">
        <span class="font-bold text-success">Ghế ✓</span><span class="app-muted">→</span>
        <span class="font-bold text-brand-start" aria-current="step">Đồ ăn</span><span class="app-muted">→</span>
        <span class="app-muted">Xác nhận</span><span class="app-muted">→</span>
        <span class="app-muted">Thanh toán</span>
    </nav>

    <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <form method="POST" action="{{ route('user.bookings.food.store') }}" class="cinema-card rounded-3xl p-6 sm:p-8">
            @csrf
            <p class="text-xs font-bold uppercase tracking-[0.24em] text-brand-start">Bước 2</p>
            <h1 class="mt-2 text-3xl font-extrabold app-text">Thêm đồ ăn (tùy chọn)</h1>
            <p class="mt-2 app-muted">Giá và tình trạng món được MovieMate kiểm tra lại ở từng bước.</p>

            <div class="mt-7">
                <label for="customer_email" class="mb-2 block text-sm font-semibold app-text">Email nhận vé</label>
                <input id="customer_email" name="customer_email" type="email" required autocomplete="email"
                    value="{{ old('customer_email', $draft['customer_email']) }}" class="user-form-control" placeholder="ban@example.com">
                @error('customer_email')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
                @error('food_items')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
            </div>

            <div class="mt-7 grid gap-4 sm:grid-cols-2">
                @forelse($foods as $index => $food)
                    @php
                        $selected = collect($draft['food_items'])->firstWhere('food_id', $food->id);
                    @endphp
                    <label class="rounded-2xl border app-border app-secondary p-4">
                        <span class="block font-bold app-text">{{ $food->name }}</span>
                        <span class="mt-1 block text-sm app-muted">{{ number_format((int) $food->price, 0, ',', '.') }}đ / phần</span>
                        <input type="hidden" name="food_items[{{ $index }}][food_id]" value="{{ $food->id }}">
                        <span class="mt-3 flex items-center gap-3 text-sm app-text">
                            Số lượng
                            <input type="number" name="food_items[{{ $index }}][quantity]" min="0"
                                max="{{ config('booking.max_food_quantity', 20) }}"
                                value="{{ old("food_items.$index.quantity", $selected['quantity'] ?? 0) }}"
                                class="w-20 rounded-xl border app-border app-secondary px-3 py-2">
                        </span>
                    </label>
                @empty
                    <p class="app-muted sm:col-span-2">Hiện chưa có món khả dụng. Bạn vẫn có thể tiếp tục đặt vé.</p>
                @endforelse
            </div>

            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <button type="submit" class="btn-primary flex-1">Tiếp tục xác nhận</button>
                <button type="submit" name="skip_food" value="1" class="btn-secondary flex-1">Bỏ qua đồ ăn</button>
            </div>
        </form>

        <aside class="cinema-card self-start rounded-3xl p-6 lg:sticky lg:top-24">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-ai-start">Ghế đã chọn</p>
            <h2 class="mt-2 text-xl font-bold app-text">{{ $preview->showtime->movie->title }}</h2>
            <p class="mt-1 text-sm app-muted">{{ $preview->showtime->cinema->name }} · {{ $preview->showtime->room->name }}</p>
            <div class="mt-5 space-y-3">
                @foreach($preview->seatSummaries() as $seat)
                    <div class="flex justify-between gap-3 text-sm">
                        <span class="app-text">Ghế {{ $seat['seat_code'] }}</span>
                        <strong class="app-text">{{ number_format($seat['price'], 0, ',', '.') }}đ</strong>
                    </div>
                @endforeach
            </div>
            <div class="mt-5 flex justify-between border-t pt-4 app-border">
                <span class="app-muted">Tiền ghế</span>
                <strong class="text-brand-start">{{ number_format($preview->prices->seatSubtotal, 0, ',', '.') }}đ</strong>
            </div>
        </aside>
    </div>
</main>
@endsection
