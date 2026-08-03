@extends('layouts.user')

@section('title', 'Chọn ghế - MovieMate')
@section('content')
@php
    $cellMap = $layoutCells->keyBy(fn($cell) => $cell->x_position.':'.$cell->y_position);
    $unavailablePairs = $seats->filter(fn($seat) => $seat->type === 'couple' && ($seat->status !== 'active' || in_array($seat->id, $bookedSeatIds)))
        ->pluck('pair_code')->filter()->unique();
@endphp
<div class="user-page-shell mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="mb-5"><a href="{{ $showtime->movie?->slug ? route('user.movies.show', $showtime->movie->slug).'#showtimes' : route('user.movies.index') }}" class="btn-secondary !px-4 !py-2 text-sm">← Quay lại lịch chiếu</a></div>
    @if(session('error'))<div class="mb-5 rounded-2xl border border-error/30 bg-error/10 px-4 py-3 text-sm font-bold text-error">{{ session('error') }}</div>@endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:gap-8">
        <div class="cinema-card overflow-hidden rounded-3xl p-5 sm:p-6 lg:col-span-2">
            <div class="mb-6 text-center">
                <p class="mb-2 text-xs font-extrabold uppercase tracking-[0.22em] text-brand-start">{{ $showtime->movie->title }}</p>
                <h1 class="mb-1 text-lg font-bold app-text md:text-xl">{{ $showtime->cinema->name }} — {{ $showtime->room->name }}</h1>
                <p class="text-sm app-muted">{{ $showtime->show_date->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($showtime->show_time)->format('H:i') }} · Layout v{{ $layout->version }}</p>
            </div>

            <form id="seatForm" action="{{ route('user.bookings.checkout', $showtime) }}" method="GET">
                <input type="hidden" name="selected_seats" id="selectedSeatsInput">
                <div class="overflow-x-auto pb-4">
                    @if($layout->screen_position === 'top')
                        <div class="mx-auto mb-8 h-2 min-w-[480px] max-w-4xl rounded-t-[100%] bg-brand-start/50 shadow-[0_10px_30px_rgba(255,61,87,0.25)]" aria-label="Màn hình phía trên"></div>
                    @endif
                    <div class="mx-auto grid w-max gap-1.5" style="grid-template-columns: repeat({{ $layout->columns }}, 2.35rem)" aria-label="Sơ đồ ghế động">
                        @for($y=1; $y <= $layout->rows; $y++) @for($x=1; $x <= $layout->columns; $x++)
                            @php
                                $cell = $cellMap->get($x.':'.$y);
                                $seat = $cell?->seat;
                                $booked = $seat && in_array($seat->id, $bookedSeatIds);
                                $pairUnavailable = $seat?->pair_code && $unavailablePairs->contains($seat->pair_code);
                                $disabled = $seat && ($booked || $seat->status !== 'active' || $pairUnavailable);
                            @endphp
                            @if(!$cell)
                                <span class="h-9 w-9" aria-hidden="true"></span>
                            @elseif($cell->cell_type === 'aisle')
                                <span class="flex h-9 w-9 items-center justify-center rounded-lg border border-dashed app-border text-xs app-muted opacity-50" aria-label="Lối đi">│</span>
                            @else
                                @php
                                    $price = $showtime->priceForSeatType($seat->type);
                                    $seatClass = match(true) {
                                        $booked || $pairUnavailable => 'bg-dark-border border-dark-border opacity-40 cursor-not-allowed',
                                        $seat->status !== 'active' => 'bg-gray-300 border-gray-400 text-gray-600 opacity-60 cursor-not-allowed',
                                        $seat->type === 'vip' => 'bg-ai-start/10 border-ai-start/50 text-ai-start',
                                        $seat->type === 'couple' => 'bg-warning/10 border-warning/50 text-warning',
                                        default => 'app-input app-muted app-border',
                                    };
                                @endphp
                                <button type="button" class="seat-button flex h-9 w-9 items-center justify-center rounded-lg border text-[9px] font-bold transition-all {{ $seatClass }}"
                                    data-seat-id="{{ $seat->id }}" data-seat-code="{{ $seat->seat_code }}" data-seat-type="{{ $seat->type }}"
                                    data-pair-code="{{ $seat->pair_code }}" data-price="{{ $price }}"
                                    aria-label="Ghế {{ $seat->seat_code }}, {{ $seat->type }}, {{ $seat->status }}, {{ number_format($price, 0, ',', '.') }} đồng"
                                    aria-pressed="false" @disabled($disabled)>{{ $seat->seat_code }}</button>
                            @endif
                        @endfor @endfor
                    </div>
                    @if($layout->screen_position === 'bottom')
                        <div class="mx-auto mt-8 h-2 min-w-[480px] max-w-4xl rounded-b-[100%] bg-brand-start/50" aria-label="Màn hình phía dưới"></div>
                    @endif
                </div>
            </form>

            <div class="mt-6 flex flex-wrap justify-center gap-4 border-t app-border pt-6 text-xs app-muted">
                <span>□ Normal</span><span class="text-ai-start">□ VIP</span><span class="text-warning">□ Couple</span><span class="text-brand-start">■ Đang chọn</span><span>■ Đã đặt</span><span>▨ Maintenance</span><span>│ Aisle</span>
            </div>
        </div>

        <div class="lg:col-span-1">
            <div class="cinema-card sticky top-24 overflow-hidden rounded-3xl p-5">
                <h2 class="text-lg font-bold app-text">Thông tin đặt vé</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="app-muted">Phòng</dt><dd class="font-bold app-text">{{ $showtime->room->name }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="app-muted">Ghế</dt><dd id="selectedSeatsDisplay" class="text-right font-bold app-text">—</dd></div>
                    <div class="flex justify-between gap-4 border-t app-border pt-4"><dt class="app-muted">Tạm tính</dt><dd id="totalAmountDisplay" class="text-2xl font-extrabold text-brand-start">0đ</dd></div>
                </dl>
                <button id="continueBookingButton" type="submit" form="seatForm" disabled class="mt-5 w-full rounded-xl bg-gradient-to-r from-brand-start to-brand-end py-3.5 font-bold text-white disabled:cursor-not-allowed disabled:opacity-50">Tiếp tục thanh toán</button>
                <p class="mt-3 text-xs app-muted">Giá và tình trạng ghế sẽ được máy chủ kiểm tra lại ở bước tiếp theo.</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const buttons = Array.from(document.querySelectorAll('.seat-button:not(:disabled)'));
    const selected = new Map();
    const input = document.getElementById('selectedSeatsInput');
    const display = document.getElementById('selectedSeatsDisplay');
    const totalDisplay = document.getElementById('totalAmountDisplay');
    const continueButton = document.getElementById('continueBookingButton');

    function targetsFor(button) {
        if (button.dataset.seatType !== 'couple') return [button];
        return buttons.filter(item => item.dataset.pairCode && item.dataset.pairCode === button.dataset.pairCode);
    }
    function setSelected(button, value) {
        if (value) {
            selected.set(button.dataset.seatId, {id:button.dataset.seatId, code:button.dataset.seatCode, price:Number(button.dataset.price)});
            button.classList.add('!bg-brand-start','!border-brand-start','!text-white','shadow-lg');
        } else {
            selected.delete(button.dataset.seatId);
            button.classList.remove('!bg-brand-start','!border-brand-start','!text-white','shadow-lg');
        }
        button.setAttribute('aria-pressed', value ? 'true' : 'false');
    }
    function refresh() {
        const values = Array.from(selected.values());
        input.value = values.map(item => item.id).join(',');
        display.textContent = values.length ? values.map(item => item.code).join(', ') : '—';
        totalDisplay.textContent = values.reduce((sum,item) => sum + item.price, 0).toLocaleString('vi-VN') + 'đ';
        continueButton.disabled = values.length === 0;
    }
    buttons.forEach(button => button.addEventListener('click', () => {
        const targets = targetsFor(button);
        if (targets.length !== (button.dataset.seatType === 'couple' ? 2 : 1)) return;
        const shouldSelect = !targets.every(item => selected.has(item.dataset.seatId));
        targets.forEach(item => setSelected(item, shouldSelect)); refresh();
    }));
});
</script>
@endsection
