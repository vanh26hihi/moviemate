@props([
    'showtime',
    'availableSeats' => null,
    'totalSeats' => null,
    'showCountdown' => true,
    'showSeats' => true,
    'showReason' => true,
    'showDetails' => true,
    'showTimeline' => true,
    'showLegend' => true,
    'size' => 'normal',
])

@php
    use App\Support\ShowtimePresentation;

    $status = ShowtimePresentation::statusMeta($showtime);

    $canSelect = ShowtimePresentation::canSelect(
        $showtime,
        $availableSeats
    );

    $reason = ShowtimePresentation::selectionReason(
        $showtime,
        $availableSeats
    );

    $countdown = ShowtimePresentation::countdownLabel(
        $showtime
    );

    $summary = ShowtimePresentation::compactSummary(
        $showtime
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

    $isCompact = $size === 'compact';
    $isLarge = $size === 'large';

    $wrapperPadding = match ($size) {
        'compact' => 'p-3',
        'large' => 'p-6',
        default => 'p-5',
    };

    $statusPadding = match ($size) {
        'compact' => 'px-2 py-1 text-[11px]',
        'large' => 'px-4 py-2 text-sm',
        default => 'px-3 py-1.5 text-xs',
    };

    $percentage = $seatMeta['percentage'] ?? null;

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
        ? max(0, $totalSeatCount - $availableSeatCount)
        : null;

    $occupiedPercentage = (
        $percentage !== null
    )
        ? max(0, 100 - $percentage)
        : null;

    $statusAccent = match ($status['key']) {
        'available' => 'border-success/20 bg-success/5',
        'starting_soon' => 'border-warning/20 bg-warning/5',
        'cancelled' => 'border-error/20 bg-error/5',
        'finished' => 'border-slate-500/20 bg-slate-500/5',
        'started' => 'border-slate-500/20 bg-slate-500/5',
        default => 'border-brand-start/20 bg-brand-start/5',
    };

    $statusIconWrapper = match ($status['key']) {
        'available' => 'bg-success/10 text-success',
        'starting_soon' => 'bg-warning/10 text-warning',
        'cancelled' => 'bg-error/10 text-error',
        'finished' => 'bg-slate-500/10 text-slate-500',
        'started' => 'bg-slate-500/10 text-slate-500',
        default => 'bg-brand-start/10 text-brand-start',
    };
@endphp


<section
    {{ $attributes->class([
        'relative overflow-hidden rounded-3xl border app-border app-card shadow-sm transition-all duration-300',
        'hover:shadow-lg hover:shadow-black/5' => $canSelect,
        'opacity-80' => ! $canSelect,
    ]) }}
    data-showtime-state="{{ $status['key'] }}"
    data-showtime-selectable="{{ $canSelect ? 'true' : 'false' }}"
>

    {{-- =====================================================
        DECORATIVE BACKGROUND
    ====================================================== --}}
    <div
        class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-brand-start via-ai-start to-brand-end"
        aria-hidden="true"
    ></div>


    {{-- =====================================================
        MAIN WRAPPER
    ====================================================== --}}
    <div class="{{ $wrapperPadding }}">

        {{-- =================================================
            TOP HEADER
        ================================================== --}}
        <div
            class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between"
        >

            <div class="min-w-0 flex-1">

                <div class="flex items-start gap-3">

                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl {{ $statusIconWrapper }}"
                    >
                        <i
                            class="ph {{ $status['icon'] }} text-2xl"
                            aria-hidden="true"
                        ></i>
                    </div>


                    <div class="min-w-0">

                        <p
                            class="text-[11px] font-extrabold uppercase tracking-[0.18em] app-muted"
                        >
                            Trạng thái suất chiếu
                        </p>


                        <div
                            class="mt-2 flex flex-wrap items-center gap-2"
                        >

                            <span
                                class="inline-flex items-center gap-1.5 rounded-full border font-extrabold {{ $statusPadding }} {{ $status['class'] }}"
                            >
                                <i
                                    class="ph {{ $status['icon'] }}"
                                    aria-hidden="true"
                                ></i>

                                {{ $status['label'] }}
                            </span>


                            @if($canSelect)

                                <span
                                    class="inline-flex items-center gap-1 rounded-full border border-success/20 bg-success/5 px-2.5 py-1 text-[11px] font-extrabold text-success"
                                >
                                    <i
                                        class="ph ph-check-circle"
                                        aria-hidden="true"
                                    ></i>

                                    Có thể đặt vé
                                </span>

                            @else

                                <span
                                    class="inline-flex items-center gap-1 rounded-full border border-slate-500/20 bg-slate-500/5 px-2.5 py-1 text-[11px] font-bold app-muted"
                                >
                                    <i
                                        class="ph ph-lock-key"
                                        aria-hidden="true"
                                    ></i>

                                    Không khả dụng
                                </span>

                            @endif

                        </div>

                    </div>

                </div>


                @unless($isCompact)

                    <p
                        class="mt-4 max-w-3xl text-sm leading-6 app-muted"
                    >
                        {{ $status['description'] }}
                    </p>

                @endunless

            </div>


            {{-- COUNTDOWN --}}
            @if(
                $showCountdown
                && $countdown
            )

                <div
                    class="rounded-2xl border border-warning/20 bg-warning/5 px-4 py-3"
                >

                    <div class="flex items-start gap-3">

                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-warning/10 text-warning"
                        >
                            <i
                                class="ph ph-clock-countdown text-lg"
                                aria-hidden="true"
                            ></i>
                        </div>


                        <div>

                            <p
                                class="text-[10px] font-extrabold uppercase tracking-wider text-warning"
                            >
                                Thời gian còn lại
                            </p>

                            <p
                                class="mt-1 text-sm font-black text-warning"
                            >
                                {{ $countdown }}
                            </p>

                        </div>

                    </div>

                </div>

            @endif

        </div>


        {{-- =================================================
            QUICK SUMMARY
        ================================================== --}}
        @if($showDetails)

            <div
                class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4"
            >

                <div
                    class="rounded-2xl border app-border app-card-soft p-3.5"
                >

                    <div class="flex items-center gap-2">

                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-start/10 text-brand-start"
                        >
                            <i
                                class="ph ph-clock"
                                aria-hidden="true"
                            ></i>
                        </span>

                        <p
                            class="text-[10px] font-bold uppercase tracking-wide app-muted"
                        >
                            Giờ chiếu
                        </p>

                    </div>

                    <p
                        class="mt-2 text-lg font-black app-heading"
                    >
                        {{ $summary['time'] }}
                    </p>

                </div>


                <div
                    class="rounded-2xl border app-border app-card-soft p-3.5"
                >

                    <div class="flex items-center gap-2">

                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-ai-start/10 text-ai-start"
                        >
                            <i
                                class="ph ph-calendar-blank"
                                aria-hidden="true"
                            ></i>
                        </span>

                        <p
                            class="text-[10px] font-bold uppercase tracking-wide app-muted"
                        >
                            Ngày chiếu
                        </p>

                    </div>

                    <p
                        class="mt-2 font-extrabold app-text"
                    >
                        {{ $summary['date'] }}
                    </p>

                </div>


                <div
                    class="rounded-2xl border app-border app-card-soft p-3.5"
                >

                    <div class="flex items-center gap-2">

                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-warning/10 text-warning"
                        >
                            <i
                                class="ph ph-door-open"
                                aria-hidden="true"
                            ></i>
                        </span>

                        <p
                            class="text-[10px] font-bold uppercase tracking-wide app-muted"
                        >
                            Phòng
                        </p>

                    </div>

                    <p
                        class="mt-2 truncate font-extrabold app-text"
                    >
                        {{ $summary['room'] }}
                    </p>

                </div>


                <div
                    class="rounded-2xl border app-border app-card-soft p-3.5"
                >

                    <div class="flex items-center gap-2">

                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-success/10 text-success"
                        >
                            <i
                                class="ph ph-ticket"
                                aria-hidden="true"
                            ></i>
                        </span>

                        <p
                            class="text-[10px] font-bold uppercase tracking-wide app-muted"
                        >
                            Giá vé
                        </p>

                    </div>

                    <p
                        class="mt-2 font-black text-brand-start"
                    >
                        {{ $summary['price'] }}
                    </p>

                </div>

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
                class="mt-5 rounded-2xl border app-border app-card-soft p-4"
            >

                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                >

                    <div class="min-w-0">

                        <div
                            class="flex flex-wrap items-center gap-2"
                        >

                            <p
                                class="text-xs font-extrabold uppercase tracking-wider app-muted"
                            >
                                Tình trạng chỗ ngồi
                            </p>


                            <span
                                class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-bold {{ $seatMeta['class'] }}"
                            >
                                {{ $seatMeta['label'] }}
                            </span>

                        </div>


                        <p
                            class="mt-2 text-sm app-muted"
                        >
                            Còn

                            <strong class="font-black app-text">
                                {{ number_format($availableSeatCount) }}
                            </strong>

                            trên tổng số

                            <strong class="font-black app-text">
                                {{ number_format($totalSeatCount) }}
                            </strong>

                            ghế.
                        </p>

                    </div>


                    <div
                        class="flex items-center gap-4"
                    >

                        <div class="text-right">

                            <p
                                class="text-3xl font-black app-heading"
                            >
                                {{ $percentage }}%
                            </p>

                            <p
                                class="text-[10px] font-extrabold uppercase tracking-wide app-muted"
                            >
                                ghế còn trống
                            </p>

                        </div>


                        <div
                            class="flex h-14 w-14 items-center justify-center rounded-2xl border app-border"
                        >
                            <i
                                class="ph ph-armchair text-2xl text-brand-start"
                                aria-hidden="true"
                            ></i>
                        </div>

                    </div>

                </div>


                {{-- PROGRESS --}}
                <div
                    class="mt-4"
                >

                    <div
                        class="mb-2 flex items-center justify-between text-[10px] font-bold uppercase tracking-wide app-muted"
                    >
                        <span>
                            Đã đặt {{ $occupiedPercentage }}%
                        </span>

                        <span>
                            Còn {{ $percentage }}%
                        </span>
                    </div>


                    <div
                        class="h-3 overflow-hidden rounded-full bg-slate-500/10"
                        role="progressbar"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-valuenow="{{ $percentage }}"
                    >

                        <div
                            class="h-full rounded-full bg-gradient-to-r from-brand-start to-brand-end transition-all duration-500"
                            style="width: {{ $percentage }}%"
                        ></div>

                    </div>

                </div>


                {{-- LARGE MODE DETAILS --}}
                @if($isLarge)

                    <div
                        class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3"
                    >

                        <div
                            class="rounded-xl border app-border p-3"
                        >

                            <p
                                class="text-[10px] font-bold uppercase tracking-wide app-muted"
                            >
                                Ghế còn
                            </p>

                            <p
                                class="mt-1 text-xl font-black text-success"
                            >
                                {{ number_format($availableSeatCount) }}
                            </p>

                        </div>


                        <div
                            class="rounded-xl border app-border p-3"
                        >

                            <p
                                class="text-[10px] font-bold uppercase tracking-wide app-muted"
                            >
                                Ghế đã đặt
                            </p>

                            <p
                                class="mt-1 text-xl font-black text-warning"
                            >
                                {{ number_format($occupiedSeatCount) }}
                            </p>

                        </div>


                        <div
                            class="rounded-xl border app-border p-3"
                        >

                            <p
                                class="text-[10px] font-bold uppercase tracking-wide app-muted"
                            >
                                Tổng sức chứa
                            </p>

                            <p
                                class="mt-1 text-xl font-black app-text"
                            >
                                {{ number_format($totalSeatCount) }}
                            </p>

                        </div>

                    </div>

                @endif

            </div>

        @endif


        {{-- =================================================
            TIMELINE
        ================================================== --}}
        @if(
            $showTimeline
            && ! $isCompact
        )

            <div
                class="mt-5 rounded-2xl border app-border p-4"
            >

                <div class="flex items-center gap-2">

                    <i
                        class="ph ph-path text-brand-start"
                        aria-hidden="true"
                    ></i>

                    <p
                        class="text-xs font-extrabold uppercase tracking-wider app-muted"
                    >
                        Tiến trình suất chiếu
                    </p>

                </div>


                <div
                    class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3"
                >

                    <div
                        class="rounded-xl border app-border p-3"
                    >

                        <div
                            class="flex items-center gap-2"
                        >

                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-start/10 text-brand-start"
                            >
                                1
                            </span>

                            <p
                                class="text-xs font-bold app-text"
                            >
                                Chọn suất
                            </p>

                        </div>

                        <p
                            class="mt-2 text-xs leading-5 app-muted"
                        >
                            Chọn đúng ngày, giờ và phòng chiếu mong muốn.
                        </p>

                    </div>


                    <div
                        class="rounded-xl border app-border p-3"
                    >

                        <div
                            class="flex items-center gap-2"
                        >

                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-ai-start/10 text-ai-start"
                            >
                                2
                            </span>

                            <p
                                class="text-xs font-bold app-text"
                            >
                                Chọn ghế
                            </p>

                        </div>

                        <p
                            class="mt-2 text-xs leading-5 app-muted"
                        >
                            Kiểm tra sơ đồ ghế và chọn vị trí phù hợp.
                        </p>

                    </div>


                    <div
                        class="rounded-xl border app-border p-3"
                    >

                        <div
                            class="flex items-center gap-2"
                        >

                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-success/10 text-success"
                            >
                                3
                            </span>

                            <p
                                class="text-xs font-bold app-text"
                            >
                                Thanh toán
                            </p>

                        </div>

                        <p
                            class="mt-2 text-xs leading-5 app-muted"
                        >
                            Hoàn tất thanh toán để xác nhận vé.
                        </p>

                    </div>

                </div>

            </div>

        @endif


        {{-- =================================================
            AVAILABILITY MESSAGE
        ================================================== --}}
        @if($canSelect)

            <div
                class="mt-5 rounded-2xl border border-success/20 bg-success/5 p-4"
            >

                <div class="flex items-start gap-3">

                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-success/10 text-success"
                    >
                        <i
                            class="ph ph-ticket text-lg"
                            aria-hidden="true"
                        ></i>
                    </div>


                    <div>

                        <p
                            class="text-sm font-extrabold text-success"
                        >
                            Suất chiếu đang nhận đặt vé
                        </p>

                        <p
                            class="mt-1 text-xs leading-5 app-muted"
                        >
                            Suất chiếu hiện hợp lệ và vẫn còn khả năng nhận đặt vé.
                        </p>

                    </div>

                </div>

            </div>


        @elseif(
            $showReason
            && $reason
        )

            <div
                class="mt-5 rounded-2xl border border-warning/20 bg-warning/5 p-4"
            >

                <div class="flex items-start gap-3">

                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-warning/10 text-warning"
                    >
                        <i
                            class="ph ph-warning-circle text-lg"
                            aria-hidden="true"
                        ></i>
                    </div>


                    <div>

                        <p
                            class="text-sm font-extrabold text-warning"
                        >
                            Suất chiếu không thể đặt
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


        {{-- =================================================
            SPECIAL STATUS AREA
        ================================================== --}}
        <div
            class="mt-5 rounded-2xl border p-4 {{ $statusAccent }}"
        >

            @if($status['key'] === 'starting_soon')

                <div class="flex items-start gap-3">

                    <i
                        class="ph ph-lightning mt-0.5 text-xl text-warning"
                        aria-hidden="true"
                    ></i>

                    <div>

                        <p
                            class="font-extrabold text-warning"
                        >
                            Suất chiếu sắp bắt đầu
                        </p>

                        <p
                            class="mt-1 text-xs leading-5 app-muted"
                        >
                            Thời gian còn lại không nhiều. Khách hàng nên hoàn tất chọn ghế và thanh toán sớm.
                        </p>

                    </div>

                </div>


            @elseif($status['key'] === 'started')

                <div class="flex items-start gap-3">

                    <i
                        class="ph ph-play-circle mt-0.5 text-xl app-muted"
                        aria-hidden="true"
                    ></i>

                    <div>

                        <p
                            class="font-extrabold app-text"
                        >
                            Suất chiếu đã bắt đầu
                        </p>

                        <p
                            class="mt-1 text-xs leading-5 app-muted"
                        >
                            Hệ thống không còn nhận đặt vé mới cho suất chiếu này.
                        </p>

                    </div>

                </div>


            @elseif($status['key'] === 'cancelled')

                <div class="flex items-start gap-3">

                    <i
                        class="ph ph-x-circle mt-0.5 text-xl text-error"
                        aria-hidden="true"
                    ></i>

                    <div>

                        <p
                            class="font-extrabold text-error"
                        >
                            Suất chiếu đã bị hủy
                        </p>

                        <p
                            class="mt-1 text-xs leading-5 app-muted"
                        >
                            Khách hàng không thể tiếp tục đặt vé cho suất chiếu này.
                        </p>

                    </div>

                </div>


            @elseif($status['key'] === 'finished')

                <div class="flex items-start gap-3">

                    <i
                        class="ph ph-check-circle mt-0.5 text-xl app-muted"
                        aria-hidden="true"
                    ></i>

                    <div>

                        <p
                            class="font-extrabold app-text"
                        >
                            Suất chiếu đã hoàn tất
                        </p>

                        <p
                            class="mt-1 text-xs leading-5 app-muted"
                        >
                            Đây là suất chiếu đã kết thúc và chỉ còn phục vụ mục đích tra cứu.
                        </p>

                    </div>

                </div>


            @else

                <div class="flex items-start gap-3">

                    <i
                        class="ph ph-info mt-0.5 text-xl text-brand-start"
                        aria-hidden="true"
                    ></i>

                    <div>

                        <p
                            class="font-extrabold app-text"
                        >
                            Thông tin trạng thái
                        </p>

                        <p
                            class="mt-1 text-xs leading-5 app-muted"
                        >
                            {{ $status['description'] }}
                        </p>

                    </div>

                </div>

            @endif

        </div>


        {{-- =================================================
            LEGEND
        ================================================== --}}
        @if(
            $showLegend
            && $isLarge
        )

            <div
                class="mt-5 border-t app-border pt-4"
            >

                <p
                    class="text-[10px] font-extrabold uppercase tracking-wider app-muted"
                >
                    Chú thích trạng thái
                </p>


                <div
                    class="mt-3 flex flex-wrap gap-2"
                >

                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border border-success/20 bg-success/5 px-2.5 py-1 text-[11px] font-bold text-success"
                    >
                        <i class="ph ph-check-circle"></i>
                        Đang mở bán
                    </span>


                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border border-warning/20 bg-warning/5 px-2.5 py-1 text-[11px] font-bold text-warning"
                    >
                        <i class="ph ph-clock-countdown"></i>
                        Sắp bắt đầu
                    </span>


                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border border-error/20 bg-error/5 px-2.5 py-1 text-[11px] font-bold text-error"
                    >
                        <i class="ph ph-x-circle"></i>
                        Đã hủy
                    </span>


                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border border-slate-500/20 bg-slate-500/5 px-2.5 py-1 text-[11px] font-bold app-muted"
                    >
                        <i class="ph ph-check"></i>
                        Đã kết thúc
                    </span>

                </div>

            </div>

        @endif


        {{-- =================================================
            ACCESSIBILITY / MACHINE DATA
        ================================================== --}}
        <div
            class="sr-only"
            aria-live="polite"
        >
            Trạng thái suất chiếu:
            {{ $status['label'] }}.

            @if($availableSeatCount !== null)
                Còn {{ $availableSeatCount }} ghế.
            @endif
        </div>


        <input
            type="hidden"
            value="{{ $status['key'] }}"
            data-showtime-status-value
        >

        <input
            type="hidden"
            value="{{ $canSelect ? '1' : '0' }}"
            data-showtime-selectable-value
        >

        @if($availableSeatCount !== null)

            <input
                type="hidden"
                value="{{ $availableSeatCount }}"
                data-showtime-available-seats
            >

        @endif

    </div>

</section>