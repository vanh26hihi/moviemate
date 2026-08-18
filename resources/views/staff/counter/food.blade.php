@extends('layouts.staff')

@section('title', 'Đồ ăn tại quầy - MovieMate')
@section('page-title', 'Đồ ăn tùy chọn')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <header><p class="text-sm font-bold text-brand-start">Đơn {{ $booking->booking_code }}</p><h1 class="mt-2 text-3xl font-extrabold app-heading">Chọn đồ ăn (tùy chọn)</h1><p class="mt-2 app-muted">Ghế {{ $booking->seat_codes }} đang được giữ đến {{ $booking->expires_at?->format('H:i:s d/m/Y') }}.</p></header>
    <form method="POST" action="{{ route('staff.counter.food.update', $booking) }}" class="space-y-5">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach($foods as $index=>$food)
                <article class="cinema-card p-5"><input type="hidden" name="food_items[{{ $index }}][food_id]" value="{{ $food->id }}"><h2 class="font-extrabold app-heading">{{ $food->name }}</h2><p class="mt-1 text-sm app-muted">{{ number_format((int)$food->price,0,',','.') }} VNĐ</p><label class="cinema-label mt-4 block">Số lượng<input class="cinema-input mt-1" type="number" min="0" max="20" name="food_items[{{ $index }}][quantity]" value="0"></label></article>
            @endforeach
        </div>
        <div class="flex justify-end"><button class="btn-primary" type="submit">Cập nhật và xem lại</button></div>
    </form>
</div>
@endsection
