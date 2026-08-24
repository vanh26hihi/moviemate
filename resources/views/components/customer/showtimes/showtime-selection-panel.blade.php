@props([
    'showtime' => null,
    'availableSeats' => null,
    'totalSeats' => null,
    'sticky' => true,
    'showPoster' => true,
    'showSeatInfo' => true,
    'showCountdown' => true,
    'showPriceBreakdown' => true,
])

@php
    use App\Support\ShowtimePresentation;

    $hasShowtime = $showtime !== null;

    $summary = $hasShowtime
        ? ShowtimePresentation::compactSummary($showtime)
        : null;

    $status = $hasShowtime
        ? ShowtimePresentation::statusMeta($showtime)
        : null;

    $canSelect = $hasShowtime
        ? ShowtimePresentation::canSelect(
            $showtime,
            $availableSeats
        )
        : false;

    $reason = $hasShowtime
        ? ShowtimePresentation::selectionReason(
            $showtime,
            $availableSeats
        )
        : null;

    $seatMeta = null;

    if (
        $hasShowtime
        && $availableSeats !== null
        && $totalSeats !== null
    ) {
        $seatMeta = ShowtimePresentation::seatAvailabilityMeta(
            (int) $availableSeats,
            (int) $totalSeats
        );
    }

    $movie = $showtime?->movie;

    $movieTitle = $movie?->title
        ?: 'Phim đang cập nhật';

    $poster = $movie?->poster_url;

    $cinema = $showtime?->cinema;

    $room = $showtime?->room;

    $countdown = $summary['countdown'] ?? null;

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


<aside
    {{ $attributes->class([
        'overflow-hidden rounded-3xl border app-border app-card shadow-sm',
        'lg:sticky lg:top-24' => $sticky,
    ]) }}
    data-showtime-selection-panel
>

    {{-- =====================================================
        PANEL HEADER
    ====================================================== --}}
    <div
        class="relative overflow-hidden border-b app-border p-5"
    >

        <div
            class="pointer-events-none absolute inset-0 bg-gradient-to-br from-brand-start/10 via-transparent to-ai-start/10"
            aria-hidden="true"
        ></div>


        <div
            class="relative flex items-start gap-3"
        >

            <span
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-brand-start/10 text-brand-start"
            >
                <i
                    class="ph ph-ticket text-xl"
                    aria-hidden="true"
                ></i>
            </span>


            <div>

                <p
                    class="text-[10px] font-black uppercase tracking-[0.18em] app-muted"
                >
                    Lựa chọn của bạn
                </p>


                <h3
                    class="mt-1 text-lg font-black app-heading"
                >
                    Suất chiếu đang chọn
                </h3>


                <p
                    class="mt-1 text-xs leading-5 app-muted"
                >
                    Kiểm tra lại thông tin trước khi tiếp tục chọn ghế.
                </p>

            </div>

        </div>

    </div>


    {{-- =====================================================
        NO SHOWTIME SELECTED
    ====================================================== --}}
    @if(! $hasShowtime)

        <div
            class="p-6 text-center"
        >

            <div
                class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-brand-start/10 text-brand-start"
            >

                <i
                    class="ph ph-cursor-click text-3xl"
                    aria-hidden="true"
                ></i>

            </div>


            <h4
                class="mt-4 font-black app-heading"
            >
                Chưa chọn suất chiếu
            </h4>


            <p
                class="mx-auto mt-2 max-w-xs text-sm leading-6 app-muted"
            >
                Chọn một suất chiếu trong danh sách để xem thông tin chi tiết tại đây.
            </p>


            <div
                class="mt-5 rounded-2xl border app-border app-card-soft p-4 text-left"
            >

                <div
                    class="flex items-start gap-3"
                >

                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-warning/10 text-warning"
                    >
                        <i
                            class="ph ph-lightbulb"
                            aria-hidden="true"
                        ></i>
                    </span>


                    <div>

                        <p
                            class="text-xs font-extrabold app-text"
                        >
                            Mẹo nhỏ
                        </p>


                        <p
                            class="mt-1 text-xs leading-5 app-muted"
                        >
                            Hãy ưu tiên suất còn nhiều ghế để dễ chọn vị trí đẹp hơn.
                        </p>

                    </div>

                </div>

            </div>

        </div>


    @else

        {{-- =================================================
            MOVIE
        ================================================== --}}
        <div
            class="p-5"
        >

            <div
                class="flex gap-4"
            >

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


                <div
                    class="min-w-0 flex-1"
                >

                    <p
                        class="text-[10px] font-black uppercase tracking-wider app-muted"
                    >
                        Phim
                    </p>


                    <h4
                        class="mt-1 line-clamp-2 text-lg font-black app-heading"
                    >
                        {{ $movieTitle }}
                    </h4>


                    <div
                        class="mt-3 flex flex-wrap gap-2"
                    >

                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-bold {{ $status['class'] }}"
                        >
                            <i
                                class="ph {{ $status['icon'] }}"
                                aria-hidden="true"
                            ></i>

                            {{ $status['label'] }}
                        </span>


                        @if($canSelect)

                            <span
                                class="inline-flex items-center gap-1 rounded-full border border-success/20 bg-success/5 px-2.5 py-1 text-[11px] font-bold text-success"
                            >
                                <i
                                    class="ph ph-check-circle"
                                    aria-hidden="true"
                                ></i>

                                Có thể đặt
                            </span>

                        @endif

                    </div>

                </div>

            </div>


            {{-- =================================================
                TIME HERO
            ================================================== --}}
            <div
                class="mt-5 overflow-hidden rounded-3xl border border-brand-start/20 bg-gradient-to-br from-brand-start/10 via-brand-start/5 to-transparent p-4"
            >

                <div
                    class="flex items-center justify-between gap-4"
                >

                    <div>

                        <p
                            class="text-[10px] font-black uppercase tracking-wider text-brand-start"
                        >
                            Giờ bắt đầu
                        </p>


                        <p
                            class="mt-1 text-3xl font-black text-brand-start"
                        >
                            {{ $summary['time'] }}
                        </p>


                        <p
                            class="mt-1 text-xs app-muted"
                        >
                            {{ $summary['date'] }}
                        </p>

                    </div>


                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-start/10 text-brand-start"
                    >

                        <i
                            class="ph ph-clock text-2xl"
                            aria-hidden="true"
                        ></i>

                    </div>

                </div>


                <div
                    class="mt-4 border-t border-brand-start/10 pt-3"
                >

                    <div
                        class="flex items-center justify-between gap-3 text-xs"
                    >

                        <span class="app-muted">
                            Khung giờ
                        </span>


                        <strong class="app-text">
                            {{ $summary['range'] }}
                        </strong>

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
                    class="mt-4 rounded-2xl border border-warning/20 bg-warning/5 p-4"
                >

                    <div
                        class="flex items-center gap-3"
                    >

                        <span
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-warning/10 text-warning"
                        >

                            <i
                                class="ph ph-clock-countdown"
                                aria-hidden="true"
                            ></i>

                        </span>


                        <div>

                            <p
                                class="text-[10px] font-black uppercase tracking-wide text-warning"
                            >
                                Thời gian còn lại
                            </p>


                            <p
                                class="mt-1 font-black text-warning"
                            >
                                {{ $countdown }}
                            </p>

                        </div>

                    </div>

                </div>

            @endif


            {{-- =================================================
                CINEMA AND ROOM
            ================================================== --}}
            <div
                class="mt-4 grid grid-cols-1 gap-3"
            >

                <div
                    class="rounded-2xl border app-border app-card-soft p-4"
                >

                    <div
                        class="flex items-start gap-3"
                    >

                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-ai-start/10 text-ai-start"
                        >

                            <i
                                class="ph ph-buildings"
                                aria-hidden="true"
                            ></i>

                        </span>


                        <div
                            class="min-w-0"
                        >

                            <p
                                class="text-[10px] font-black uppercase tracking-wide app-muted"
                            >
                                Rạp
                            </p>


                            <p
                                class="mt-1 truncate text-sm font-black app-text"
                            >
                                {{ $summary['cinema'] }}
                            </p>


                            @if($cinema?->address)

                                <p
                                    class="mt-1 line-clamp-2 text-xs leading-5 app-muted"
                                >
                                    {{ $cinema->address }}
                                </p>

                            @endif

                        </div>

                    </div>

                </div>


                <div
                    class="rounded-2xl border app-border app-card-soft p-4"
                >

                    <div
                        class="flex items-start gap-3"
                    >

                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-warning/10 text-warning"
                        >

                            <i
                                class="ph ph-door-open"
                                aria-hidden="true"
                            ></i>

                        </span>


                        <div>

                            <p
                                class="text-[10px] font-black uppercase tracking-wide app-muted"
                            >
                                Phòng chiếu
                            </p>


                            <p
                                class="mt-1 text-sm font-black app-text"
                            >
                                {{ $summary['room'] }}
                            </p>


                            @if($room?->code)

                                <p
                                    class="mt-1 text-xs app-muted"
                                >
                                    Mã phòng:
                                    {{ $room->code }}
                                </p>

                            @endif

                        </div>

                    </div>

                </div>

            </div>


            {{-- =================================================
                MOVIE DETAIL
            ================================================== --}}
            <div
                class="mt-4 grid grid-cols-2 gap-3"
            >

                <div
                    class="rounded-2xl border app-border p-3"
                >

                    <p
                        class="text-[10px] font-black uppercase tracking-wide app-muted"
                    >
                        Thời lượng
                    </p>


                    <p
                        class="mt-1 flex items-center gap-1.5 text-sm font-black app-text"
                    >

                        <i
                            class="ph ph-timer text-brand-start"
                            aria-hidden="true"
                        ></i>

                        {{ $summary['duration'] }}

                    </p>

                </div>


                <div
                    class="rounded-2xl border app-border p-3"
                >

                    <p
                        class="text-[10px] font-black uppercase tracking-wide app-muted"
                    >
                        Ngày chiếu
                    </p>


                    <p
                        class="mt-1 flex items-center gap-1.5 text-sm font-black app-text"
                    >

                        <i
                            class="ph ph-calendar text-brand-start"
                            aria-hidden="true"
                        ></i>

                        {{ $summary['date'] }}

                    </p>

                </div>

            </div>


            {{-- =================================================
                PRICE BREAKDOWN
            ================================================== --}}
            @if($showPriceBreakdown)

                <div
                    class="mt-4 rounded-3xl border app-border p-4"
                >

                    <div
                        class="flex items-center justify-between gap-3"
                    >

                        <div
                            class="flex items-center gap-2"
                        >

                            <i
                                class="ph ph-currency-circle-dollar text-brand-start"
                                aria-hidden="true"
                            ></i>


                            <p
                                class="text-xs font-black uppercase tracking-wide app-muted"
                            >
                                Giá vé
                            </p>

                        </div>


                        <span
                            class="text-[10px] font-bold app-muted"
                        >
                            / ghế
                        </span>

                    </div>


                    <div
                        class="mt-4 space-y-3"
                    >

                        <div
                            class="flex items-center justify-between gap-3"
                        >

                            <span
                                class="inline-flex items-center gap-2 text-sm app-muted"
                            >

                                <span
                                    class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-start/10 text-brand-start"
                                >
                                    <i
                                        class="ph ph-armchair"
                                        aria-hidden="true"
                                    ></i>
                                </span>

                                Ghế thường

                            </span>


                            <strong
                                class="text-base font-black text-brand-start"
                            >
                                {{ $summary['price'] }}
                            </strong>

                        </div>


                        @if($summary['vip_price'])

                            <div
                                class="flex items-center justify-between gap-3 border-t app-border pt-3"
                            >

                                <span
                                    class="inline-flex items-center gap-2 text-sm app-muted"
                                >

                                    <span
                                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-warning/10 text-warning"
                                    >
                                        <i
                                            class="ph ph-star"
                                            aria-hidden="true"
                                        ></i>
                                    </span>

                                    Ghế VIP

                                </span>


                                <strong
                                    class="text-base font-black app-text"
                                >
                                    {{ $summary['vip_price'] }}
                                </strong>

                            </div>

                        @endif

                    </div>

                </div>

            @endif


            {{-- =================================================
                SEAT INFORMATION
            ================================================== --}}
            @if(
                $showSeatInfo
                && $seatMeta
            )

                <div
                    class="mt-4 rounded-3xl border app-border p-4"
                >

                    <div
                        class="flex items-center justify-between gap-3"
                    >

                        <div>

                            <p
                                class="text-[10px] font-black uppercase tracking-wide app-muted"
                            >
                                Ghế còn trống
                            </p>


                            <div
                                class="mt-1 flex items-center gap-2"
                            >

                                <span
                                    class="rounded-full border px-2.5 py-1 text-[11px] font-bold {{ $seatMeta['class'] }}"
                                >
                                    {{ $seatMeta['label'] }}
                                </span>


                                <span
                                    class="text-xs app-muted"
                                >
                                    {{ $seatMeta['percentage'] }}%
                                </span>

                            </div>

                        </div>


                        <div
                            class="text-right"
                        >

                            <p
                                class="text-2xl font-black app-heading"
                            >
                                {{ number_format($availableSeatCount) }}
                            </p>


                            <p
                                class="text-[10px] font-bold uppercase tracking-wide app-muted"
                            >
                                / {{ number_format($totalSeatCount) }}
                                ghế
                            </p>

                        </div>

                    </div>


                    <div
                        class="mt-4 h-2.5 overflow-hidden rounded-full bg-slate-500/10"
                    >

                        <div
                            class="h-full rounded-full bg-gradient-to-r from-brand-start to-brand-end"
                            style="width: {{ $seatMeta['percentage'] }}%"
                        ></div>

                    </div>


                    <div
                        class="mt-4 grid grid-cols-2 gap-2"
                    >

                        <div
                            class="rounded-xl bg-success/5 p-3 text-center"
                        >

                            <p
                                class="text-[10px] font-bold uppercase text-success"
                            >
                                Còn trống
                            </p>


                            <p
                                class="mt-1 font-black text-success"
                            >
                                {{ number_format($availableSeatCount) }}
                            </p>

                        </div>


                        <div
                            class="rounded-xl bg-warning/5 p-3 text-center"
                        >

                            <p
                                class="text-[10px] font-bold uppercase text-warning"
                            >
                                Đã giữ/đặt
                            </p>


                            <p
                                class="mt-1 font-black text-warning"
                            >
                                {{ number_format($occupiedSeatCount) }}
                            </p>

                        </div>

                    </div>

                </div>

            @endif


            {{-- =================================================
                DISABLED STATE
            ================================================== --}}
            @if(
                ! $canSelect
                && $reason
            )

                <div
                    class="mt-4 rounded-2xl border border-warning/20 bg-warning/5 p-4"
                >

                    <div
                        class="flex items-start gap-3"
                    >

                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-warning/10 text-warning"
                        >

                            <i
                                class="ph ph-warning-circle"
                                aria-hidden="true"
                            ></i>

                        </span>


                        <div>

                            <p
                                class="text-xs font-black text-warning"
                            >
                                Không thể tiếp tục
                            </p>


                            <p
                                class="mt-1 text-xs leading-5 app-muted"
                            >
                                {{ $reason }}
                            </p>

                        </div>

                    </div>

                </div>

            @endif

        </div>


        {{-- =================================================
            ACTION
        ================================================== --}}
        <footer
            class="border-t app-border p-5"
        >

            @if($canSelect)

                <a
                    href="{{ route('user.bookings.create', [
                        'showtime' => $showtime->id
                    ]) }}"
                    class="group inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-brand-start to-brand-end px-4 py-3.5 text-sm font-black text-white transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-brand-start/20"
                >

                    <i
                        class="ph ph-armchair"
                        aria-hidden="true"
                    ></i>

                    Tiếp tục chọn ghế

                    <i
                        class="ph ph-arrow-right transition-transform group-hover:translate-x-1"
                        aria-hidden="true"
                    ></i>

                </a>


                <p
                    class="mt-3 text-center text-[10px] leading-4 app-muted"
                >
                    Việc chọn suất chưa giữ ghế.
                    Ghế chỉ được giữ khi bạn tiếp tục sang bước chọn chỗ.
                </p>


            @else

                <button
                    type="button"
                    disabled
                    class="inline-flex w-full cursor-not-allowed items-center justify-center gap-2 rounded-2xl border app-border px-4 py-3.5 text-sm font-bold app-muted opacity-50"
                >

                    <i
                        class="ph ph-lock"
                        aria-hidden="true"
                    ></i>

                    Suất chiếu không khả dụng

                </button>

            @endif

        </footer>

    @endif


    {{-- =====================================================
        ACCESSIBILITY
    ====================================================== --}}
    @if($hasShowtime)

        <div
            class="sr-only"
            role="status"
            aria-live="polite"
        >
            Đã chọn suất chiếu
            {{ $summary['time'] }}
            ngày
            {{ $summary['date'] }},
            tại
            {{ $summary['cinema'] }},
            phòng
            {{ $summary['room'] }}.
        </div>

    @endif

</aside>