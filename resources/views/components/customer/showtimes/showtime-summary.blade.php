@props([
    'showtime',
    'availableSeats' => null,
    'totalSeats' => null,
    'selected' => false,
    'compact' => false,
])

@php
    $summary = \App\Support\ShowtimePresentation::compactSummary($showtime);

    $status = $summary['status'];

    $canSelect = \App\Support\ShowtimePresentation::canSelect(
        $showtime,
        $availableSeats
    );

    $selectionReason = \App\Support\ShowtimePresentation::selectionReason(
        $showtime,
        $availableSeats
    );

    $seatMeta = null;

    if (
        $availableSeats !== null
        && $totalSeats !== null
    ) {
        $seatMeta = \App\Support\ShowtimePresentation::seatAvailabilityMeta(
            (int) $availableSeats,
            (int) $totalSeats
        );
    }

    $movieTitle =
        $showtime->movie?->title
        ?? 'Phim đang cập nhật';

    $poster =
        $showtime->movie?->poster_url;

    $room =
        $summary['room'];

    $cinema =
        $summary['cinema'];

    $countdown =
        $summary['countdown'];

    $vipPrice =
        $summary['vip_price'];
@endphp


<article
    {{ $attributes->class([
        'relative overflow-hidden rounded-2xl border transition-all duration-200',
        'border-brand-start bg-brand-start/5 shadow-lg shadow-brand-start/10' => $selected,
        'app-border app-card hover:border-brand-start/40' => ! $selected,
        'opacity-60' => ! $canSelect,
    ]) }}
>

    {{-- =========================================================
        SELECTED INDICATOR
    ========================================================== --}}
    @if($selected)

        <div
            class="absolute right-0 top-0 z-10 rounded-bl-xl bg-brand-start px-3 py-1.5 text-xs font-extrabold text-white"
        >
            <div class="flex items-center gap-1.5">
                <i
                    class="ph ph-check-circle"
                    aria-hidden="true"
                ></i>

                Đã chọn
            </div>
        </div>

    @endif


    {{-- =========================================================
        MAIN CONTENT
    ========================================================== --}}
    <div class="{{ $compact ? 'p-4' : 'p-5' }}">

        <div class="flex gap-4">

            {{-- POSTER --}}
            @unless($compact)

                <div
                    class="h-28 w-20 shrink-0 overflow-hidden rounded-xl border app-border bg-slate-950"
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
                            class="admin-media-fallback flex h-full w-full items-center justify-center text-xs"
                        >
                            MM
                        </div>

                    @endif

                </div>

            @endunless


            {{-- DETAILS --}}
            <div class="min-w-0 flex-1">

                {{-- TITLE + STATUS --}}
                <div
                    class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                >

                    <div class="min-w-0">

                        <p
                            class="truncate font-extrabold app-heading {{ $compact ? 'text-base' : 'text-lg' }}"
                        >
                            {{ $movieTitle }}
                        </p>

                        <p class="mt-1 text-xs app-muted">
                            Suất #{{ $showtime->id }}
                        </p>

                    </div>


                    <span
                        class="inline-flex w-fit items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-bold {{ $status['class'] }}"
                        title="{{ $status['description'] }}"
                    >

                        <i
                            class="ph {{ $status['icon'] }}"
                            aria-hidden="true"
                        ></i>

                        {{ $status['label'] }}

                    </span>

                </div>


                {{-- TIME --}}
                <div
                    class="mt-4 flex flex-wrap items-center gap-2"
                >

                    <span
                        class="inline-flex items-center gap-2 rounded-xl border border-brand-start/20 bg-brand-start/5 px-3 py-2"
                    >

                        <i
                            class="ph ph-clock text-brand-start"
                            aria-hidden="true"
                        ></i>

                        <span
                            class="text-lg font-black text-brand-start"
                        >
                            {{ $summary['time'] }}
                        </span>

                    </span>


                    <span
                        class="inline-flex items-center gap-2 rounded-xl border app-border px-3 py-2 text-sm font-bold app-text"
                    >

                        <i
                            class="ph ph-calendar"
                            aria-hidden="true"
                        ></i>

                        {{ $summary['date'] }}

                    </span>


                    @if($countdown)

                        <span
                            class="inline-flex items-center gap-1.5 rounded-xl border border-warning/20 bg-warning/5 px-3 py-2 text-xs font-bold text-warning"
                        >

                            <i
                                class="ph ph-clock-countdown"
                                aria-hidden="true"
                            ></i>

                            {{ $countdown }}

                        </span>

                    @endif

                </div>


                {{-- META --}}
                <div
                    class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2"
                >

                    {{-- CINEMA --}}
                    <div
                        class="rounded-xl border app-border p-3"
                    >

                        <p
                            class="text-[11px] font-bold uppercase tracking-wider app-muted"
                        >
                            Rạp
                        </p>

                        <p
                            class="mt-1 flex items-center gap-1.5 text-sm font-bold app-text"
                        >

                            <i
                                class="ph ph-buildings text-brand-start"
                                aria-hidden="true"
                            ></i>

                            {{ $cinema }}

                        </p>

                    </div>


                    {{-- ROOM --}}
                    <div
                        class="rounded-xl border app-border p-3"
                    >

                        <p
                            class="text-[11px] font-bold uppercase tracking-wider app-muted"
                        >
                            Phòng
                        </p>

                        <p
                            class="mt-1 flex items-center gap-1.5 text-sm font-bold app-text"
                        >

                            <i
                                class="ph ph-door-open text-brand-start"
                                aria-hidden="true"
                            ></i>

                            {{ $room }}

                        </p>

                    </div>


                    {{-- DURATION --}}
                    <div
                        class="rounded-xl border app-border p-3"
                    >

                        <p
                            class="text-[11px] font-bold uppercase tracking-wider app-muted"
                        >
                            Thời lượng
                        </p>

                        <p
                            class="mt-1 flex items-center gap-1.5 text-sm font-bold app-text"
                        >

                            <i
                                class="ph ph-timer text-brand-start"
                                aria-hidden="true"
                            ></i>

                            {{ $summary['duration'] }}

                        </p>

                    </div>


                    {{-- TIME RANGE --}}
                    <div
                        class="rounded-xl border app-border p-3"
                    >

                        <p
                            class="text-[11px] font-bold uppercase tracking-wider app-muted"
                        >
                            Khung giờ
                        </p>

                        <p
                            class="mt-1 flex items-center gap-1.5 text-sm font-bold app-text"
                        >

                            <i
                                class="ph ph-clock-user text-brand-start"
                                aria-hidden="true"
                            ></i>

                            {{ $summary['range'] }}

                        </p>

                    </div>

                </div>


                {{-- PRICE --}}
                <div
                    class="mt-4 rounded-xl border app-border app-card-soft p-3"
                >

                    <div
                        class="flex flex-wrap items-end justify-between gap-3"
                    >

                        <div>

                            <p
                                class="text-[11px] font-bold uppercase tracking-wider app-muted"
                            >
                                Giá vé từ
                            </p>

                            <p
                                class="mt-1 text-lg font-black text-brand-start"
                            >
                                {{ $summary['price'] }}
                            </p>

                        </div>


                        @if($vipPrice)

                            <div class="text-right">

                                <p
                                    class="text-[11px] font-bold uppercase tracking-wider app-muted"
                                >
                                    Ghế VIP
                                </p>

                                <p
                                    class="mt-1 font-extrabold app-text"
                                >
                                    {{ $vipPrice }}
                                </p>

                            </div>

                        @endif

                    </div>

                </div>


                {{-- SEAT AVAILABILITY --}}
                @if($seatMeta)

                    <div
                        class="mt-4 rounded-xl border app-border p-3"
                    >

                        <div
                            class="flex items-center justify-between gap-3"
                        >

                            <div>

                                <p
                                    class="text-[11px] font-bold uppercase tracking-wider app-muted"
                                >
                                    Tình trạng ghế
                                </p>

                                <div
                                    class="mt-1 flex items-center gap-2"
                                >

                                    <span
                                        class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-bold {{ $seatMeta['class'] }}"
                                    >
                                        {{ $seatMeta['label'] }}
                                    </span>

                                    <span
                                        class="text-xs app-muted"
                                    >
                                        {{ number_format((int) $availableSeats) }}
                                        /
                                        {{ number_format((int) $totalSeats) }}
                                        ghế còn
                                    </span>

                                </div>

                            </div>


                            <span
                                class="text-lg font-black app-text"
                            >
                                {{ $seatMeta['percentage'] }}%
                            </span>

                        </div>


                        <div
                            class="mt-3 h-2 overflow-hidden rounded-full bg-slate-500/10"
                        >

                            <div
                                class="h-full rounded-full bg-brand-start transition-all"
                                style="width: {{ $seatMeta['percentage'] }}%"
                            ></div>

                        </div>

                    </div>

                @endif


                {{-- DISABLED REASON --}}
                @if(! $canSelect && $selectionReason)

                    <div
                        class="mt-4 rounded-xl border border-warning/20 bg-warning/5 p-3"
                    >

                        <div
                            class="flex items-start gap-2"
                        >

                            <i
                                class="ph ph-warning-circle mt-0.5 text-warning"
                                aria-hidden="true"
                            ></i>

                            <div>

                                <p
                                    class="text-xs font-bold text-warning"
                                >
                                    Không thể chọn suất này
                                </p>

                                <p
                                    class="mt-1 text-xs app-muted"
                                >
                                    {{ $selectionReason }}
                                </p>

                            </div>

                        </div>

                    </div>

                @endif

            </div>

        </div>

    </div>


    {{-- =========================================================
        ACTION AREA
    ========================================================== --}}
    <div
        class="border-t app-border px-5 py-4"
    >

        @if($canSelect)

            <a
                href="{{ route('user.bookings.create', ['showtime' => $showtime->id]) }}"
                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-brand-start to-brand-end px-4 py-3 text-sm font-extrabold text-white transition hover:shadow-lg"
            >

                <i
                    class="ph ph-ticket"
                    aria-hidden="true"
                ></i>

                {{ $selected ? 'Tiếp tục với suất này' : 'Chọn suất chiếu' }}

                <i
                    class="ph ph-arrow-right"
                    aria-hidden="true"
                ></i>

            </a>

        @else

            <button
                type="button"
                disabled
                class="inline-flex w-full cursor-not-allowed items-center justify-center gap-2 rounded-xl border app-border px-4 py-3 text-sm font-bold app-muted opacity-60"
            >

                <i
                    class="ph ph-lock"
                    aria-hidden="true"
                ></i>

                Không khả dụng

            </button>

        @endif

    </div>

</article>