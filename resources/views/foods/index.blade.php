@extends('layouts.user')

@section('title', 'Thực đơn tại rạp - MovieMate')

@section('content')
<div class="user-page-shell max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between mb-8">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.3em] text-brand-start">Thực đơn rạp</p>
            <h1 class="mt-3 text-3xl sm:text-4xl font-bold app-text">Đặt đồ ăn khi đến rạp</h1>
            <p class="mt-3 max-w-2xl text-sm app-muted">Chọn món, thêm vào giỏ và nhận đồ ngay khi đến rạp. Thanh toán nhanh gọn bằng chức năng giống mua vé.</p>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
            <a href="{{ route('foods.cart') }}" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-brand-start px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-start/20 hover:shadow-brand-start/30 transition">
                <i class="ph-fill ph-shopping-bag"></i>
                Giỏ hàng ({{ array_sum(session('food_cart', [])) ?: 0 }})
            </a>
            <a href="{{ route('home') }}#home-showtime-calendar" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-white/10 bg-app-card px-5 py-3 text-sm font-semibold app-text hover:border-brand-start hover:text-brand-start transition">
                <i class="ph ph-film-strip"></i>
                Xem lịch chiếu
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-3xl border border-success/20 bg-success/10 p-4 text-sm text-success mb-6">{{ session('success') }}</div>
    @endif

    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        @forelse($foods as $food)
            <article class="cinema-card rounded-[2rem] p-5 transition hover:-translate-y-1 hover:border-brand-start/40">
                @if($food->image)
                    <div class="overflow-hidden rounded-[1.75rem] bg-slate-900 mb-4 h-56">
                        <img src="{{ asset('storage/' . $food->image) }}" alt="{{ $food->name }}" class="h-full w-full object-cover transition-transform duration-500 hover:scale-105" loading="lazy">
                    </div>
                @else
                    <div class="mb-4 flex h-56 items-center justify-center rounded-[1.75rem] bg-gradient-to-br from-brand-start/15 via-ai-start/10 to-dark-main text-brand-start"><i class="ph-fill ph-popcorn text-6xl"></i></div>
                @endif
                <div class="flex items-center justify-between gap-4 mb-3">
                    <h2 class="text-xl font-bold app-text">{{ $food->name }}</h2>
                    <span class="rounded-full bg-brand-start/10 px-3 py-1 text-sm font-semibold text-brand-start">{{ number_format($food->price,0,',','.') }}đ</span>
                </div>
                <p class="text-sm app-muted mb-5 min-h-[72px]">{{ $food->description ?: 'Chưa có mô tả.' }}</p>

                <form action="{{ route('foods.add') }}" method="POST" class="grid gap-3">
                    @csrf
                    <input type="hidden" name="food_id" value="{{ $food->id }}">
                    <div class="flex items-center gap-3">
                        <label class="text-sm font-medium app-text">Số lượng</label>
                        <input type="number" name="quantity" value="1" min="1" class="user-form-control !w-24 !py-2" aria-label="Số lượng {{ $food->name }}" />
                    </div>
                    <button type="submit" class="rounded-2xl bg-brand-start px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-start/20 hover:bg-brand-end transition">Thêm vào giỏ</button>
                </form>
            </article>
        @empty
            <div class="col-span-full cinema-card rounded-[2rem] p-10 text-center">
                <h2 class="text-2xl font-bold app-text">Hiện chưa có món ăn</h2>
                <p class="mt-3 text-sm app-muted">Vui lòng quay lại sau hoặc liên hệ quản trị viên để cập nhật thực đơn.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-10 text-center">
        {{ $foods->links() }}
    </div>
</div>
@endsection
