@extends('layouts.staff')

@section('title', 'Chọn ghế tại quầy - MovieMate')
@section('page-title', 'Chọn ghế tại quầy')

@section('content')
@php
    $cellMap = $layoutCells->keyBy(fn($cell) => $cell->x_position.':'.$cell->y_position);
    $unavailable = array_fill_keys($unavailableSeatIds, true);
@endphp
<div class="space-y-6">
    <header><a class="text-sm font-bold text-brand-start" href="{{ route('staff.counter.index') }}">← Chọn suất chiếu khác</a><h1 class="mt-3 text-3xl font-extrabold app-heading">{{ $showtime->movie->title }}</h1><p class="mt-2 app-muted">{{ $showtime->cinema->name }} · {{ $showtime->room->name }} · {{ $showtime->show_date->format('d/m/Y') }} {{ \Carbon\Carbon::parse($showtime->show_time)->format('H:i') }}</p></header>

    <form method="POST" action="{{ route('staff.counter.hold', $showtime) }}" class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        @csrf
        <input type="hidden" name="checkout_token" value="{{ $checkoutToken }}">
        <section class="cinema-card overflow-x-auto p-5">
            <p class="mb-6 text-center text-xs font-extrabold uppercase tracking-[.3em] app-muted">Màn hình</p>
            <div class="mx-auto grid w-max gap-2" style="grid-template-columns:repeat({{ $layout->columns }},2.75rem)">
                @for($y=1;$y<=$layout->rows;$y++)
                    @for($x=1;$x<=$layout->columns;$x++)
                        @php $cell=$cellMap->get($x.':'.$y); $seat=$cell?->seat; $disabled=$seat && isset($unavailable[$seat->id]); @endphp
                        @if(!$cell)<span class="h-11 w-11"></span>
                        @elseif($cell->cell_type==='aisle')<span class="flex h-11 w-11 items-center justify-center app-muted">↕</span>
                        @else
                            <label class="relative flex h-11 w-11 cursor-pointer items-center justify-center rounded-lg border app-border text-xs font-extrabold has-[:checked]:border-brand-start has-[:checked]:bg-brand-start has-[:checked]:text-white {{ $disabled ? 'cursor-not-allowed opacity-35' : '' }}" title="{{ $seat->seat_code }} · {{ number_format($seatPrices[$seat->type]->finalAmount,0,',','.') }} VNĐ">
                                <input class="sr-only counter-seat" type="checkbox" name="seat_ids[]" value="{{ $seat->id }}" data-pair="{{ $seat->pair_code }}" @disabled($disabled)>
                                {{ $seat->seat_code }}
                            </label>
                        @endif
                    @endfor
                @endfor
            </div>
            <p class="mt-5 text-center text-sm app-muted">Máy chủ sẽ kiểm tra lại ghế giữ, ghế đôi, khoảng trống một ghế và giá chính thức.</p>
        </section>

        <aside class="space-y-4">
            <section class="cinema-card p-5"><h2 class="font-extrabold app-heading">Thông tin khách (không bắt buộc)</h2>
                <label class="cinema-label mt-4 block">Tên khách<input class="cinema-input mt-1" name="customer_name" maxlength="120" value="{{ old('customer_name') }}"></label>
                <label class="cinema-label mt-3 block">Điện thoại<input class="cinema-input mt-1" name="customer_phone" maxlength="30" value="{{ old('customer_phone') }}"></label>
                <label class="cinema-label mt-3 block">Email<input class="cinema-input mt-1" type="email" name="customer_email" maxlength="255" value="{{ old('customer_email') }}"><span class="mt-1 block text-xs app-muted">Chỉ gửi tài liệu nhận vé khi có email.</span></label>
            </section>
            <section class="cinema-card p-5"><h2 class="font-extrabold app-heading">Bảng giá</h2>@foreach($seatPrices as $type=>$price)<div class="mt-2 flex justify-between text-sm"><span>{{ \App\Support\StatusLabel::for('seat_type',$type) }}</span><strong>{{ number_format($price->finalAmount,0,',','.') }} VNĐ</strong></div>@endforeach<button class="btn-primary mt-5 w-full" type="submit">Giữ ghế tại quầy</button></section>
        </aside>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.counter-seat[data-pair]').forEach(function (seat) {
    seat.addEventListener('change', function () {
        if (!seat.dataset.pair) return;
        document.querySelectorAll('.counter-seat[data-pair="' + CSS.escape(seat.dataset.pair) + '"]').forEach(function (member) { if (!member.disabled) member.checked = seat.checked; });
    });
});
</script>
@endpush
