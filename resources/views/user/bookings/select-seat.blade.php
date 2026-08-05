@extends('layouts.user')

@section('title', 'Chọn ghế - MovieMate')

@section('content')
@php
    $cellMap = $layoutCells->keyBy(fn ($cell) => $cell->x_position.':'.$cell->y_position);
    $bookedSeatLookup = array_fill_keys($bookedSeatIds, true);
    $seatGroups = \App\Support\SeatPresentation::groups($seats);
    $seatGroupLookup = collect();
    foreach ($seatGroups as $group) {
        foreach ($group['seats'] as $member) {
            $seatGroupLookup->put($member->id, $group);
        }
    }
    $seatTypeLabels = ['normal' => 'Thường', 'vip' => 'VIP', 'couple' => 'Ghế đôi'];
@endphp

<main class="user-page-shell px-4 py-8 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl">
        <x-checkout-progress current="seat" class="mb-8" />

        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <a href="{{ $showtime->movie?->slug ? route('user.movies.show', $showtime->movie->slug).'#showtimes' : route('user.movies.index') }}" class="btn-secondary !px-4 !py-2 text-sm">
                <i class="ph-bold ph-arrow-left" aria-hidden="true"></i>
                Quay lại lịch chiếu
            </a>
            <p class="text-sm app-muted">Bước 1/4 · Chọn ghế phù hợp</p>
        </div>

        @if(session('error'))
            <div class="mb-5 rounded-2xl border border-error/30 bg-error/10 px-4 py-3 text-sm font-bold text-error" role="alert" aria-live="assertive">
                {{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:gap-8">
            <section class="cinema-card overflow-hidden rounded-3xl p-4 sm:p-6 lg:col-span-2" aria-labelledby="seat-picker-title">
                <header class="mb-6 text-center">
                    <p class="mb-2 text-xs font-extrabold uppercase tracking-[0.22em] text-brand-start">{{ $showtime->movie->title }}</p>
                    <h1 id="seat-picker-title" class="mb-1 text-lg font-bold app-text md:text-xl">Chọn ghế tại {{ $showtime->room->name }}</h1>
                    <p class="text-sm app-muted">
                        {{ $showtime->cinema->name }} · {{ $showtime->show_date->format('d/m/Y') }} · {{ \Carbon\Carbon::parse($showtime->show_time)->format('H:i') }}
                    </p>
                </header>

                <form id="seatForm" action="{{ route('user.bookings.checkout', $showtime) }}" method="GET" data-seat-picker>
                    <input type="hidden" name="selected_seats" id="selectedSeatsInput" value="">

                    <div class="overflow-x-auto overscroll-x-contain pb-4" tabindex="0" aria-label="Sơ đồ ghế, có thể cuộn ngang trên màn hình nhỏ">
                        @if($layout->screen_position === 'top')
                            <div class="mx-auto mb-8 h-2 min-w-[32rem] max-w-4xl rounded-t-[100%] bg-brand-start/50 shadow-[0_10px_30px_rgba(255,61,87,0.25)]" aria-hidden="true"></div>
                            <p class="-mt-6 mb-6 min-w-[32rem] text-center text-[0.65rem] font-bold uppercase tracking-[0.3em] app-muted">Màn hình</p>
                        @endif

                        <div class="mx-auto grid w-max gap-1.5 sm:gap-2" style="grid-template-columns: repeat({{ $layout->columns }}, 2.5rem)" role="group" aria-label="Sơ đồ ghế động">
                            @for($y = 1; $y <= $layout->rows; $y++)
                                @for($x = 1; $x <= $layout->columns; $x++)
                                    @php
                                        $cell = $cellMap->get($x.':'.$y);
                                        $seat = $cell?->seat;
                                        $seatGroup = $seat ? $seatGroupLookup->get($seat->id) : null;
                                        $groupSeats = $seatGroup['seats'] ?? collect([$seat]);
                                        $isMergedCouple = (bool) ($seatGroup['is_couple'] ?? false) && (bool) ($seatGroup['is_valid'] ?? false);
                                        $primarySeatId = $isMergedCouple ? $groupSeats->sortBy('x_position')->first()?->id : $seat?->id;
                                    @endphp

                                    @if(!$cell)
                                        <span class="h-10 w-10" aria-hidden="true"></span>
                                    @elseif($cell->cell_type === 'aisle')
                                        <span class="flex h-10 w-10 items-center justify-center text-xs app-muted opacity-50" aria-label="Lối đi"><i class="ph ph-arrows-down-up" aria-hidden="true"></i></span>
                                    @elseif($isMergedCouple && $seat->id !== $primarySeatId)
                                        @continue
                                    @else
                                        @php
                                            $booked = $groupSeats->contains(fn ($member) => isset($bookedSeatLookup[$member->id]));
                                            $maintenance = $groupSeats->contains(fn ($member) => $member->status !== 'active');
                                            $invalidPair = (bool) ($seatGroup['is_couple'] ?? false) && ! (bool) ($seatGroup['is_valid'] ?? false);
                                            $disabled = $booked || $maintenance || $invalidPair;
                                            $price = $showtime->priceForSeatType($seatGroup['type'] ?? $seat->type);
                                            $seatClass = match(true) {
                                                $booked => 'border-slate-500 bg-slate-600/50 text-slate-300 cursor-not-allowed',
                                                $maintenance => 'border-dashed border-warning/60 bg-warning/10 text-warning cursor-not-allowed',
                                                $invalidPair => 'border-dashed border-error/70 bg-error/10 text-error cursor-not-allowed',
                                                $seat->type === 'vip' => 'border-ai-start/60 bg-ai-start/10 text-ai-start hover:bg-ai-start/20',
                                                $seat->type === 'couple' => 'border-warning/60 bg-warning/10 text-warning hover:bg-warning/20',
                                                default => 'app-input app-muted app-border hover:border-brand-start hover:text-brand-start',
                                            };
                                            $availability = match(true) {
                                                $booked => 'đã có người giữ',
                                                $maintenance => 'đang bảo trì',
                                                $invalidPair => 'dữ liệu cặp ghế không hợp lệ, không khả dụng',
                                                default => 'còn trống',
                                            };
                                            $type = $seatGroup['type'] ?? $seat->type;
                                            $typeLabel = $seatTypeLabels[$type] ?? \App\Support\StatusLabel::for('seat_type', $type);
                                            $seatCode = $seatGroup['seat_code'] ?? $seat->seat_code;
                                            $seatIds = implode(',', $seatGroup['seat_ids'] ?? [$seat->id]);
                                        @endphp
                                        <button
                                            type="button"
                                            class="checkout-seat seat-button flex items-center justify-center rounded-lg border px-1 text-[10px] font-extrabold transition {{ $isMergedCouple ? 'checkout-seat-couple col-span-2' : '' }} {{ $seatClass }}"
                                            data-seat-ids="{{ $seatIds }}"
                                            data-seat-code="{{ $seatCode }}"
                                            data-seat-type="{{ $type }}"
                                            data-pair-code="{{ $seat->pair_code }}"
                                            data-price="{{ $price }}"
                                            aria-label="{{ $isMergedCouple ? 'Ghế đôi '.$seatCode : 'Ghế '.$seatCode }}, loại {{ $typeLabel }}, {{ $availability }}, {{ number_format($price, 0, ',', '.') }} VNĐ"
                                            aria-pressed="false"
                                            @disabled($disabled)
                                        >{{ $seatCode }}</button>
                                    @endif
                                @endfor
                            @endfor
                        </div>

                        @if($layout->screen_position === 'bottom')
                            <p class="mb-2 mt-6 min-w-[32rem] text-center text-[0.65rem] font-bold uppercase tracking-[0.3em] app-muted">Màn hình</p>
                            <div class="mx-auto h-2 min-w-[32rem] max-w-4xl rounded-b-[100%] bg-brand-start/50" aria-hidden="true"></div>
                        @endif
                    </div>
                </form>

                <div class="mt-5 grid grid-cols-2 gap-3 border-t pt-5 text-xs app-muted app-border sm:grid-cols-4 lg:grid-cols-7" aria-label="Chú thích loại ghế">
                    <span class="flex items-center gap-2"><i class="h-4 w-4 rounded border app-border app-input" aria-hidden="true"></i>Thường</span>
                    <span class="flex items-center gap-2 text-ai-start"><i class="h-4 w-4 rounded border border-ai-start/60 bg-ai-start/10" aria-hidden="true"></i>VIP</span>
                    <span class="flex items-center gap-2 text-warning"><i class="h-4 w-4 rounded border border-warning/60 bg-warning/10" aria-hidden="true"></i>Ghế đôi</span>
                    <span class="flex items-center gap-2 text-brand-start"><i class="h-4 w-4 rounded bg-brand-start" aria-hidden="true"></i>Đang chọn</span>
                    <span class="flex items-center gap-2"><i class="h-4 w-4 rounded bg-slate-600/60" aria-hidden="true"></i>Đã giữ</span>
                    <span class="flex items-center gap-2 text-warning"><i class="h-4 w-4 rounded border border-dashed border-warning/60" aria-hidden="true"></i>Bảo trì</span>
                    <span class="flex items-center gap-2"><i class="ph ph-arrows-down-up" aria-hidden="true"></i>Lối đi</span>
                </div>
            </section>

            <aside class="lg:col-span-1" aria-labelledby="booking-estimate-title">
                <div class="cinema-card sticky top-24 overflow-hidden rounded-3xl p-5 sm:p-6">
                    <p class="text-xs font-extrabold uppercase tracking-[0.2em] text-brand-start">Tóm tắt lựa chọn</p>
                    <h2 id="booking-estimate-title" class="mt-2 text-lg font-bold app-text">Ghế của bạn</h2>
                    <dl class="mt-5 space-y-3 text-sm">
                        <div class="flex justify-between gap-4"><dt class="app-muted">Phòng</dt><dd class="font-bold app-text">{{ $showtime->room->name }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="app-muted">Ghế</dt><dd id="selectedSeatsDisplay" class="text-right font-bold app-text" aria-live="polite">Chưa chọn</dd></div>
                        <div class="flex justify-between gap-4 border-t pt-4 app-border"><dt class="app-muted">Tạm tính tiền ghế</dt><dd id="totalAmountDisplay" class="text-2xl font-extrabold text-brand-start" aria-live="polite">0 VNĐ</dd></div>
                    </dl>
                    <button id="continueBookingButton" type="submit" form="seatForm" disabled class="btn-primary mt-5 w-full">Tiếp tục chọn đồ ăn</button>
                    <p id="seatSelectionHint" class="mt-3 text-xs leading-relaxed app-muted">Ghế đôi sẽ được chọn hoặc bỏ chọn cả cặp. Giá và tình trạng ghế sẽ được máy chủ kiểm tra lại.</p>
                </div>
            </aside>
        </div>
    </div>
</main>
@endsection
