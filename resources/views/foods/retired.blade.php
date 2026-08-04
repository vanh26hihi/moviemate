@extends('layouts.user')

@section('title', 'Đặt đồ ăn cùng vé - MovieMate')

@section('content')
<div class="user-page-shell mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
    <div class="cinema-card rounded-[2rem] p-10 text-center">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-start/10 text-brand-start">
            <i class="ph-fill ph-film-strip text-3xl"></i>
        </div>
        <h1 class="mt-5 text-3xl font-bold app-text">Đồ ăn được đặt cùng vé xem phim</h1>
        <p class="mx-auto mt-3 max-w-xl text-sm leading-relaxed app-muted">MovieMate đã ngừng luồng đặt đồ ăn riêng. Vui lòng chọn phim, suất chiếu và ghế; bạn có thể thêm đồ ăn tại bước tiếp theo trước khi thanh toán.</p>
        <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
            <a href="{{ route('home') }}#home-showtime-calendar" class="inline-flex items-center justify-center rounded-2xl bg-brand-start px-6 py-3 text-sm font-semibold text-white">Chọn suất chiếu</a>
            <a href="{{ route('foods.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-white/10 bg-app-card px-6 py-3 text-sm font-semibold app-text">Xem thực đơn</a>
        </div>
    </div>
</div>
@endsection
