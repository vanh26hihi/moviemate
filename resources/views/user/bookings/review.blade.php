@extends('layouts.user')

@section('title', 'Xác nhận đơn - MovieMate')

@section('content')
<main class="user-page-shell mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
    <nav class="mb-8 flex flex-wrap items-center gap-3 text-sm" aria-label="Tiến trình checkout">
        <span class="font-bold text-success">Ghế ✓</span><span class="app-muted">→</span>
        <span class="font-bold text-success">Đồ ăn ✓</span><span class="app-muted">→</span>
        <span class="font-bold text-brand-start" aria-current="step">Xác nhận</span><span class="app-muted">→</span>
        <span class="app-muted">Thanh toán</span>
    </nav>

    <section class="cinema-card rounded-3xl p-6 sm:p-8">
        <p class="text-xs font-bold uppercase tracking-[0.24em] text-brand-start">Bước 3</p>
        <h1 class="mt-2 text-3xl font-extrabold app-text">Xem lại đơn</h1>
        <p class="mt-2 app-muted">Đây là tổng tiền authoritative vừa được tính lại trên server.</p>

        <div class="mt-7 grid gap-6 lg:grid-cols-2">
            <div>
                <h2 class="font-bold app-text">Vé xem phim</h2>
                <p class="mt-1 text-sm app-muted">{{ $preview->showtime->movie->title }} · {{ $preview->showtime->room->name }}</p>
                <div class="mt-4 space-y-3">
                    @foreach($preview->seatSummaries() as $seat)
                        <div class="flex justify-between gap-3 text-sm"><span class="app-text">Ghế {{ $seat['seat_code'] }}</span><strong class="app-text">{{ number_format($seat['price'], 0, ',', '.') }}đ</strong></div>
                    @endforeach
                </div>
            </div>

            <div>
                <h2 class="font-bold app-text">Đồ ăn tại {{ $preview->showtime->cinema->name }}</h2>
                <div class="mt-4 space-y-3">
                    @forelse($preview->prices->foodLines as $line)
                        <div class="flex justify-between gap-3 text-sm">
                            <span class="app-text">{{ $line->snapshotName }} × {{ $line->quantity }}</span>
                            <strong class="app-text">{{ number_format($line->lineTotal, 0, ',', '.') }}đ</strong>
                        </div>
                    @empty
                        <p class="text-sm app-muted">Không chọn đồ ăn.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <dl class="mt-8 space-y-3 rounded-2xl app-secondary p-5">
            <div class="flex justify-between"><dt class="app-muted">Tiền ghế</dt><dd class="font-bold app-text">{{ number_format($preview->prices->seatSubtotal, 0, ',', '.') }}đ</dd></div>
            <div class="flex justify-between"><dt class="app-muted">Tiền đồ ăn</dt><dd class="font-bold app-text">{{ number_format($preview->prices->foodSubtotal, 0, ',', '.') }}đ</dd></div>
            <div class="flex justify-between border-t pt-3 app-border"><dt class="font-bold app-text">Tổng thanh toán</dt><dd class="text-2xl font-extrabold text-brand-start">{{ number_format($preview->prices->grandTotal, 0, ',', '.') }} VND</dd></div>
        </dl>

        <p class="mt-5 text-sm app-muted">Email nhận vé: <strong class="app-text">{{ $draft['customer_email'] }}</strong>. Booking và đơn đồ ăn chỉ được xác nhận sau callback/query ZaloPay hợp lệ.</p>

        <div class="mt-7 flex flex-col gap-3 sm:flex-row">
            <a href="{{ route('user.bookings.food') }}" class="btn-secondary flex-1 text-center">Quay lại đồ ăn</a>
            <form method="POST" action="{{ route('user.bookings.confirm') }}" class="flex-1">
                @csrf
                <button type="submit" class="btn-primary w-full">Xác nhận và thanh toán ZaloPay</button>
            </form>
        </div>
    </section>
</main>
@endsection
