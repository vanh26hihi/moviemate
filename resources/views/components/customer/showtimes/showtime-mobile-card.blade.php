@props([
    'showtime',
    'availableSeats' => null,
    'totalSeats' => null,
    'selected' => false,
    'showPoster' => true,
    'showCinema' => true,
    'showRoom' => true,
    'showPrice' => true,
    'showSeats' => true,
    'showCountdown' => true,
])

@php
    use App\Support\ShowtimePresentation;

    $summary = ShowtimePresentation::compactSummary(
        $showtime
    );

    $status = ShowtimePresentation::statusMeta(
        $showtime
    );

    $canSelect = ShowtimePresentation::canSelect(
        $showtime,
        $availableSeats
    );

    $reason = ShowtimePresentation::selectionReason(
        $showtime,
        $availableSeats
    );

    $seatMeta = null;

    if (
        $availableSeats !== null
        && $totalSeats !== null
    ) {
        $seatMeta = ShowtimePresentation::seatAvailabilityMeta(
            (int) $availableSeats,
            (int) $totalSeats
        );
    }

    $movie = $showtime->movie;

    $movieTitle = $movie?->title
        ?: 'Phim đang cập nhật';

    $poster = $movie?->poster_url;

    $cinemaName = $summary['cinema'];

    $roomName = $summary['room'];

    $countdown = $summary['countdown'];

    $availableSeatCount = $availableSeats !== null
        ? max(0, (int) $availableSeats)
        : null;

    $totalSeatCount = $totalSeats !== null
        ? max(0, (int) $totalSeats)
        : null;

    $occupiedSeatCount = (
        $availableSeatCount !== null
        && $totalSeatCount !== null
    )
        ? max(
            0,
            $totalSeatCount - $availableSeatCount
        )
        : null;
@endphp


<article
    {{ $attributes->class([
        'relative overflow-hidden rounded-3xl border transition-all duration-200 md:hidden',
        'border-brand-start bg-brand-start/5 shadow-lg shadow-brand-start/10' => $selected,
        'app-border app-card' => ! $selected,
        'opacity-70' => ! $canSelect,
    ]) }}
    data-mobile-showtime-card="{{ $showtime->id }}"
    data-showtime-selectable="{{ $canSelect ? 'true' : 'false' }}"
>

    {{-- =====================================================
        TOP ACCENT
    ====================================================== --}}
    <div
        @class([
            'h-1 w-full',
            'bg-gradient-to-r from-brand-start to-brand-end' => $canSelect,
            'bg-slate-500/30' => ! $canSelect,
        ])
        aria-hidden="true"
    ></div>


    {{-- =====================================================
        SELECTED BADGE
    ====================================================== --}}
    @if($selected)

        <div
            class="absolute right-0 top-1 z-20 rounded-bl-2xl bg-brand-start px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-white"
        >
            <div class="flex items-center gap-1">

                <i
                    class="ph ph-check-circle"
                    aria-hidden="true"
                ></i>

                Đã chọn

            </div>
        </div>

    @endif


    {{-- =====================================================
        MOVIE AREA
    ====================================================== --}}
    <div class="p-4">

        <div class="flex gap-4">

            {{-- POSTER --}}
            @if($showPoster)

                <div
                    class="h-28 w-20 shrink-0 overflow-hidden rounded-2xl border app-border bg-slate-950"
                >

                    @if($poster)

                        <img
                            src="{{ $poster }}"
                            alt="Áp phích {{ $movieTitle }}"
                            class="h-full w-full object-cover"
                            loading="lazy"
                        >

                    @else

                        <div
                            class="flex h-full w-full items-center justify-center bg-gradient-to-br from-brand-start/10 to-ai-start/10"
                        >

                            <i
                                class="ph ph-film-slate text-2xl text-brand-start"
                                aria-hidden="true"
                            ></i>

                        </div>

                    @endif

                </div>

            @endif


            <div class="min-w-0 flex-1">

                <p
                    class="text-[10px] font-black uppercase tracking-wider app-muted"
                >
                    Suất chiếu
                </p>


                <h3
                    class="mt-1 line-clamp-2 text-base font-black app-heading"
                >
                    {{ $movieTitle }}
                </h3>


                <div
                    class="mt-2 flex flex-wrap gap-1.5"
                >

                    <span
                        class="inline-flex items-center gap-1 rounded-full border px-2 py-1 text-[10px] font-bold {{ $status['class'] }}"
                    >
                        <i
                            class="ph {{ $status['icon'] }}"
                            aria-hidden="true"
                        ></i>

                        {{ $status['label'] }}
                    </span>


                    @if($seatMeta)

                        <span
                            class="inline-flex items-center rounded-full border px-2 py-1 text-[10px] font-bold {{ $seatMeta['class'] }}"
                        >
                            {{ $seatMeta['label'] }}
                        </span>

                    @endif

                </div>


                {{-- TIME --}}
                <div
                    class="mt-3 flex items-end gap-2"
                >

                    <p
                        class="text-3xl font-black leading-none text-brand-start"
                    >
                        {{ $summary['time'] }}
                    </p>


                    <div
                        class="pb-0.5"
                    >

                        <p
                            class="text-[10px] font-bold uppercase tracking-wide app-muted"
                        >
                            {{ $summary['date'] }}
                        </p>

                        <p
                            class="mt-0.5 text-[10px] app-muted"
                        >
                            {{ $summary['range'] }}
                        </p>

                    </div>

                </div>

            </div>

        </div>


        {{-- =================================================
            COUNTDOWN
        ================================================== --}}
        @if(
            $showCountdown
            && $countdown
        )

            <div
                class="mt-4 flex items-center justify-between gap-3 rounded-2xl border border-warning/20 bg-warning/5 px-3 py-2.5"
            >

                <div
                    class="flex items-center gap-2"
                >

                    <span
                        class="flex h-8 w-8 items-center justify-center rounded-xl bg-warning/10 text-warning"
                    >
                        <i
                            class="ph ph-clock-countdown"
                            aria-hidden="true"
                        ></i>
                    </span>


                    <div>

                        <p
                            class="text-[9px] font-black uppercase tracking-wide text-warning"
                        >
                            Bắt đầu sau
                        </p>

                        <p
                            class="mt-0.5 text-xs font-black text-warning"
                        >
                            {{ $countdown }}
                        </p>

                    </div>

                </div>


                <i
                    class="ph ph-lightning text-warning"
                    aria-hidden="true"
                ></i>

            </div>

        @endif


        {{-- =================================================
            CINEMA / ROOM
        ================================================== --}}
        @if(
            $showCinema
            || $showRoom
        )

            <div
                class="mt-4 grid grid-cols-2 gap-2"
            >

                @if($showCinema)

                    <div
                        class="rounded-2xl border app-border app-card-soft p-3"
                    >

                        <div
                            class="flex items-center gap-2"
                        >

                            <span
                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-brand-start/10 text-brand-start"
                            >
                                <i
                                    class="ph ph-buildings text-xs"
                                    aria-hidden="true"
                                ></i>
                            </span>


                            <p
                                class="text-[9px] font-black uppercase tracking-wide app-muted"
                            >
                                Rạp
                            </p>

                        </div>


                        <p
                            class="mt-2 line-clamp-2 text-xs font-black app-text"
                        >
                            {{ $cinemaName }}
                        </p>

                    </div>

                @endif


                @if($showRoom)

                    <div
                        class="rounded-2xl border app-border app-card-soft p-3"
                    >

                        <div
                            class="flex items-center gap-2"
                        >

                            <span
                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-ai-start/10 text-ai-start"
                            >
                                <i
                                    class="ph ph-door-open text-xs"
                                    aria-hidden="true"
                                ></i>
                            </span>


                            <p
                                class="text-[9px] font-black uppercase tracking-wide app-muted"
                            >
                                Phòng
                            </p>

                        </div>


                        <p
                            class="mt-2 truncate text-xs font-black app-text"
                        >
                            {{ $roomName }}
                        </p>

                    </div>

                @endif

            </div>

        @endif


        {{-- =================================================
            DURATION + PRICE
        ================================================== --}}
        <div
            class="mt-3 grid grid-cols-2 gap-2"
        >

            <div
                class="rounded-2xl border app-border p-3"
            >

                <p
                    class="text-[9px] font-black uppercase tracking-wide app-muted"
                >
                    Thời lượng
                </p>


                <p
                    class="mt-1.5 flex items-center gap-1.5 text-xs font-black app-text"
                >
                    <i
                        class="ph ph-timer text-brand-start"
                        aria-hidden="true"
                    ></i>

                    {{ $summary['duration'] }}
                </p>

            </div>


            @if($showPrice)

                <div
                    class="rounded-2xl border app-border p-3"
                >

                    <p
                        class="text-[9px] font-black uppercase tracking-wide app-muted"
                    >
                        Giá từ
                    </p>


                    <p
                        class="mt-1.5 text-xs font-black text-brand-start"
                    >
                        {{ $summary['price'] }}
                    </p>

                </div>

            @endif

        </div>


        {{-- =================================================
            VIP PRICE
        ================================================== --}}
        @if(
            $showPrice
            && $summary['vip_price']
        )

            <div
                class="mt-3 flex items-center justify-between gap-3 rounded-2xl border border-warning/20 bg-warning/5 px-3 py-2.5"
            >

                <div
                    class="flex items-center gap-2"
                >

                    <span
                        class="flex h-8 w-8 items-center justify-center rounded-xl bg-warning/10 text-warning"
                    >
                        <i
                            class="ph ph-star"
                            aria-hidden="true"
                        ></i>
                    </span>


                    <div>

                        <p
                            class="text-[9px] font-black uppercase tracking-wide app-muted"
                        >
                            Ghế VIP
                        </p>

                        <p
                            class="mt-0.5 text-xs font-black app-text"
                        >
                            {{ $summary['vip_price'] }}
                        </p>

                    </div>

                </div>


                <span
                    class="rounded-full border border-warning/20 px-2 py-1 text-[9px] font-black text-warning"
                >
                    VIP
                </span>

            </div>

        @endif


        {{-- =================================================
            SEAT AVAILABILITY
        ================================================== --}}
        @if(
            $showSeats
            && $seatMeta
        )

            <div
                class="mt-4 rounded-2xl border app-border p-3"
            >

                <div
                    class="flex items-center justify-between gap-3"
                >

                    <div>

                        <p
                            class="text-[9px] font-black uppercase tracking-wide app-muted"
                        >
                            Ghế còn
                        </p>


                        <p
                            class="mt-1 text-lg font-black app-heading"
                        >
                            {{ number_format($availableSeatCount) }}

                            <span
                                class="text-[10px] font-bold app-muted"
                            >
                                / {{ number_format($totalSeatCount) }}
                            </span>
                        </p>

                    </div>


                    <div
                        class="text-right"
                    >

                        <p
                            class="text-lg font-black text-brand-start"
                        >
                            {{ $seatMeta['percentage'] }}%
                        </p>


                        <p
                            class="text-[9px] font-bold uppercase tracking-wide app-muted"
                        >
                            còn trống
                        </p>

                    </div>

                </div>


                <div
                    class="mt-3 h-2 overflow-hidden rounded-full bg-slate-500/10"
                >

                    <div
                        class="h-full rounded-full bg-gradient-to-r from-brand-start to-brand-end"
                        style="width: {{ $seatMeta['percentage'] }}%"
                    ></div>

                </div>


                <div
                    class="mt-3 grid grid-cols-2 gap-2"
                >

                    <div
                        class="rounded-xl bg-success/5 p-2.5 text-center"
                    >

                        <p
                            class="text-[9px] font-bold uppercase text-success"
                        >
                            Còn
                        </p>

                        <p
                            class="mt-1 text-sm font-black text-success"
                        >
                            {{ number_format($availableSeatCount) }}
                        </p>

                    </div>


                    <div
                        class="rounded-xl bg-warning/5 p-2.5 text-center"
                    >

                        <p
                            class="text-[9px] font-bold uppercase text-warning"
                        >
                            Đã dùng
                        </p>

                        <p
                            class="mt-1 text-sm font-black text-warning"
                        >
                            {{ number_format($occupiedSeatCount) }}
                        </p>

                    </div>

                </div>

            </div>

        @endif


        {{-- =================================================
            SELECTABLE INFO
        ================================================== --}}
        @if($canSelect)

            <div
                class="mt-4 flex items-start gap-2 rounded-2xl border border-success/20 bg-success/5 p-3"
            >

                <i
                    class="ph ph-check-circle mt-0.5 shrink-0 text-success"
                    aria-hidden="true"
                ></i>


                <p
                    class="text-[11px] leading-5 app-muted"
                >
                    Suất chiếu đang mở bán.
                    Nhấn chọn để tiếp tục xem sơ đồ ghế.
                </p>

            </div>


        @elseif($reason)

            <div
                class="mt-4 flex items-start gap-2 rounded-2xl border border-warning/20 bg-warning/5 p-3"
            >

                <i
                    class="ph ph-warning-circle mt-0.5 shrink-0 text-warning"
                    aria-hidden="true"
                ></i>


                <p
                    class="text-[11px] leading-5 app-muted"
                >
                    {{ $reason }}
                </p>

            </div>

        @endif

    </div>


    {{-- =====================================================
        ACTION BAR
    ====================================================== --}}
    <footer
        class="border-t app-border p-4"
    >

        @if($canSelect)

            <a
                href="{{ route('user.bookings.create', [
                    'showtime' => $showtime->id
                ]) }}"
                @class([
                    'group inline-flex w-full items-center justify-center gap-2 rounded-2xl px-4 py-3 text-sm font-black transition',
                    'bg-gradient-to-r from-brand-start to-brand-end text-white shadow-lg shadow-brand-start/10' => $selected,
                    'border app-border app-text hover:border-brand-start hover:text-brand-start' => ! $selected,
                ])
            >

                <i
                    class="ph ph-ticket"
                    aria-hidden="true"
                ></i>


                {{ $selected ? 'Tiếp tục đặt vé' : 'Chọn suất chiếu' }}


                <i
                    class="ph ph-arrow-right transition-transform group-hover:translate-x-1"
                    aria-hidden="true"
                ></i>

            </a>


        @else

            <button
                type="button"
                disabled
                class="inline-flex w-full cursor-not-allowed items-center justify-center gap-2 rounded-2xl border app-border px-4 py-3 text-sm font-bold app-muted opacity-50"
            >

                <i
                    class="ph ph-lock"
                    aria-hidden="true"
                ></i>

                Không khả dụng

            </button>

        @endif

    </footer>


    {{-- =====================================================
        ACCESSIBILITY
    ====================================================== --}}
    <div
        class="sr-only"
        role="status"
        aria-live="polite"
    >
        Suất chiếu phim
        {{ $movieTitle }},
        lúc
        {{ $summary['time'] }},
        ngày
        {{ $summary['date'] }}.

        Trạng thái:
        {{ $status['label'] }}.
    </div>

</article>