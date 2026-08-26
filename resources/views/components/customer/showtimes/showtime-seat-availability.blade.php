@props([
    'availableSeats' => 0,
    'totalSeats' => 0,
    'reservedSeats' => null,
    'heldSeats' => null,
    'showBreakdown' => true,
    'showProgress' => true,
    'showLegend' => true,
    'showHint' => true,
    'size' => 'normal',
])

@php
    $availableSeats = max(
        0,
        (int) $availableSeats
    );

    $totalSeats = max(
        0,
        (int) $totalSeats
    );

    $reservedSeats = $reservedSeats !== null
        ? max(0, (int) $reservedSeats)
        : null;

    $heldSeats = $heldSeats !== null
        ? max(0, (int) $heldSeats)
        : null;

    if (
        $reservedSeats === null
        && $totalSeats > 0
    ) {
        $reservedSeats = max(
            0,
            $totalSeats - $availableSeats
        );
    }

    $availablePercentage = $totalSeats > 0
        ? (int) round(
            ($availableSeats / $totalSeats) * 100
        )
        : 0;

    $occupiedPercentage = $totalSeats > 0
        ? max(
            0,
            100 - $availablePercentage
        )
        : 0;

    $state = match (true) {

        $totalSeats <= 0 => [
            'key' => 'unknown',
            'label' => 'Chưa có dữ liệu',
            'description' => 'Hệ thống chưa có dữ liệu sức chứa của phòng chiếu.',
            'icon' => 'ph-question',
            'badgeClass' => 'border-slate-500/20 bg-slate-500/5 text-slate-500',
            'accentClass' => 'bg-slate-500/10 text-slate-500',
            'progressClass' => 'bg-slate-500',
        ],

        $availableSeats === 0 => [
            'key' => 'sold_out',
            'label' => 'Hết ghế',
            'description' => 'Suất chiếu hiện không còn ghế trống để đặt.',
            'icon' => 'ph-x-circle',
            'badgeClass' => 'border-error/20 bg-error/5 text-error',
            'accentClass' => 'bg-error/10 text-error',
            'progressClass' => 'bg-error',
        ],

        $availablePercentage <= 10 => [
            'key' => 'critical',
            'label' => 'Gần hết ghế',
            'description' => 'Chỉ còn rất ít ghế trống. Nên đặt sớm.',
            'icon' => 'ph-warning',
            'badgeClass' => 'border-error/20 bg-error/5 text-error',
            'accentClass' => 'bg-error/10 text-error',
            'progressClass' => 'bg-error',
        ],

        $availablePercentage <= 30 => [
            'key' => 'limited',
            'label' => 'Còn ít ghế',
            'description' => 'Số lượng ghế trống đang giảm nhanh.',
            'icon' => 'ph-warning-circle',
            'badgeClass' => 'border-warning/20 bg-warning/5 text-warning',
            'accentClass' => 'bg-warning/10 text-warning',
            'progressClass' => 'bg-warning',
        ],

        $availablePercentage <= 60 => [
            'key' => 'normal',
            'label' => 'Còn ghế',
            'description' => 'Suất chiếu vẫn còn nhiều lựa chọn ghế.',
            'icon' => 'ph-armchair',
            'badgeClass' => 'border-brand-start/20 bg-brand-start/5 text-brand-start',
            'accentClass' => 'bg-brand-start/10 text-brand-start',
            'progressClass' => 'bg-brand-start',
        ],

        default => [
            'key' => 'good',
            'label' => 'Còn nhiều ghế',
            'description' => 'Bạn còn nhiều lựa chọn vị trí ngồi.',
            'icon' => 'ph-check-circle',
            'badgeClass' => 'border-success/20 bg-success/5 text-success',
            'accentClass' => 'bg-success/10 text-success',
            'progressClass' => 'bg-success',
        ],
    };

    $isCompact = $size === 'compact';

    $isLarge = $size === 'large';

    $wrapperPadding = match ($size) {
        'compact' => 'p-3',
        'large' => 'p-6',
        default => 'p-5',
    };
@endphp


<section
    {{ $attributes->class([
        'relative overflow-hidden rounded-3xl border app-border app-card',
        $wrapperPadding,
    ]) }}
    data-showtime-seat-availability
    data-seat-state="{{ $state['key'] }}"
    data-available-seats="{{ $availableSeats }}"
    data-total-seats="{{ $totalSeats }}"
>

    {{-- =====================================================
        DECORATION
    ====================================================== --}}
    <div
        class="pointer-events-none absolute -right-10 -top-10 h-28 w-28 rounded-full bg-brand-start/5 blur-3xl"
        aria-hidden="true"
    ></div>


    {{-- =====================================================
        HEADER
    ====================================================== --}}
    <div
        class="relative flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
    >

        <div
            class="flex items-start gap-3"
        >

            <span
                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl {{ $state['accentClass'] }}"
            >
                <i
                    class="ph {{ $state['icon'] }} text-xl"
                    aria-hidden="true"
                ></i>
            </span>


            <div>

                <p
                    class="text-[10px] font-black uppercase tracking-[0.18em] app-muted"
                >
                    Tình trạng ghế
                </p>


                <div
                    class="mt-1.5 flex flex-wrap items-center gap-2"
                >

                    <h3
                        class="text-lg font-black app-heading"
                    >
                        Ghế còn trống
                    </h3>


                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-black {{ $state['badgeClass'] }}"
                    >
                        <i
                            class="ph {{ $state['icon'] }}"
                            aria-hidden="true"
                        ></i>

                        {{ $state['label'] }}
                    </span>

                </div>


                @unless($isCompact)

                    <p
                        class="mt-2 max-w-xl text-sm leading-6 app-muted"
                    >
                        {{ $state['description'] }}
                    </p>

                @endunless

            </div>

        </div>


        {{-- BIG NUMBER --}}
        <div
            class="rounded-2xl border app-border app-card-soft px-4 py-3 text-right"
        >

            <p
                class="text-[10px] font-black uppercase tracking-wide app-muted"
            >
                Còn trống
            </p>


            <p
                class="mt-1 text-3xl font-black app-heading"
            >
                {{ number_format($availableSeats) }}
            </p>


            <p
                class="text-[10px] font-bold app-muted"
            >
                / {{ number_format($totalSeats) }} ghế
            </p>

        </div>

    </div>


    {{-- =====================================================
        PROGRESS
    ====================================================== --}}
    @if(
        $showProgress
        && $totalSeats > 0
    )

        <div
            class="relative mt-5"
        >

            <div
                class="mb-2 flex items-center justify-between gap-3"
            >

                <span
                    class="text-[10px] font-black uppercase tracking-wide app-muted"
                >
                    Tỷ lệ ghế còn trống
                </span>


                <span
                    class="text-sm font-black app-text"
                >
                    {{ $availablePercentage }}%
                </span>

            </div>


            <div
                class="h-3 overflow-hidden rounded-full bg-slate-500/10"
                role="progressbar"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="{{ $availablePercentage }}"
                aria-label="Tỷ lệ ghế còn trống"
            >

                <div
                    class="h-full rounded-full {{ $state['progressClass'] }} transition-all duration-500"
                    style="width: {{ $availablePercentage }}%"
                ></div>

            </div>


            <div
                class="mt-2 flex items-center justify-between text-[10px] font-bold app-muted"
            >

                <span>
                    Đã sử dụng {{ $occupiedPercentage }}%
                </span>

                <span>
                    Còn {{ $availablePercentage }}%
                </span>

            </div>

        </div>

    @endif


    {{-- =====================================================
        BREAKDOWN
    ====================================================== --}}
    @if(
        $showBreakdown
        && $totalSeats > 0
    )

        <div
            class="mt-5 grid grid-cols-2 gap-3 {{ $heldSeats !== null ? 'lg:grid-cols-4' : 'lg:grid-cols-3' }}"
        >

            {{-- AVAILABLE --}}
            <article
                class="rounded-2xl border border-success/20 bg-success/5 p-4"
            >

                <div
                    class="flex items-center justify-between gap-2"
                >

                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-success/10 text-success"
                    >
                        <i
                            class="ph ph-check-circle"
                            aria-hidden="true"
                        ></i>
                    </span>


                    <span
                        class="text-[10px] font-black uppercase tracking-wide text-success"
                    >
                        Còn
                    </span>

                </div>


                <p
                    class="mt-3 text-2xl font-black text-success"
                >
                    {{ number_format($availableSeats) }}
                </p>


                <p
                    class="mt-1 text-xs app-muted"
                >
                    Ghế còn trống
                </p>

            </article>


            {{-- RESERVED --}}
            <article
                class="rounded-2xl border border-warning/20 bg-warning/5 p-4"
            >

                <div
                    class="flex items-center justify-between gap-2"
                >

                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-warning/10 text-warning"
                    >
                        <i
                            class="ph ph-ticket"
                            aria-hidden="true"
                        ></i>
                    </span>


                    <span
                        class="text-[10px] font-black uppercase tracking-wide text-warning"
                    >
                        Đã đặt
                    </span>

                </div>


                <p
                    class="mt-3 text-2xl font-black text-warning"
                >
                    {{ number_format($reservedSeats ?? 0) }}
                </p>


                <p
                    class="mt-1 text-xs app-muted"
                >
                    Ghế đã được sử dụng
                </p>

            </article>


            {{-- TOTAL --}}
            <article
                class="rounded-2xl border app-border app-card-soft p-4"
            >

                <div
                    class="flex items-center justify-between gap-2"
                >

                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-start/10 text-brand-start"
                    >
                        <i
                            class="ph ph-armchair"
                            aria-hidden="true"
                        ></i>
                    </span>


                    <span
                        class="text-[10px] font-black uppercase tracking-wide app-muted"
                    >
                        Tổng
                    </span>

                </div>


                <p
                    class="mt-3 text-2xl font-black app-heading"
                >
                    {{ number_format($totalSeats) }}
                </p>


                <p
                    class="mt-1 text-xs app-muted"
                >
                    Sức chứa phòng
                </p>

            </article>


            {{-- HELD --}}
            @if($heldSeats !== null)

                <article
                    class="rounded-2xl border border-ai-start/20 bg-ai-start/5 p-4"
                >

                    <div
                        class="flex items-center justify-between gap-2"
                    >

                        <span
                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-ai-start/10 text-ai-start"
                        >
                            <i
                                class="ph ph-clock"
                                aria-hidden="true"
                            ></i>
                        </span>


                        <span
                            class="text-[10px] font-black uppercase tracking-wide text-ai-start"
                        >
                            Đang giữ
                        </span>

                    </div>


                    <p
                        class="mt-3 text-2xl font-black text-ai-start"
                    >
                        {{ number_format($heldSeats) }}
                    </p>


                    <p
                        class="mt-1 text-xs app-muted"
                    >
                        Ghế tạm giữ
                    </p>

                </article>

            @endif

        </div>

    @endif


    {{-- =====================================================
        VISUAL SEAT SCALE
    ====================================================== --}}
    @if(
        $isLarge
        && $totalSeats > 0
    )

        <div
            class="mt-5 rounded-3xl border app-border p-4"
        >

            <div
                class="flex items-center justify-between gap-3"
            >

                <div>

                    <p
                        class="text-xs font-black uppercase tracking-wider app-muted"
                    >
                        Mức độ lấp đầy
                    </p>


                    <p
                        class="mt-1 text-xs app-muted"
                    >
                        Minh họa nhanh mức độ sử dụng ghế.
                    </p>

                </div>


                <span
                    class="text-sm font-black app-text"
                >
                    {{ $occupiedPercentage }}%
                </span>

            </div>


            <div
                class="mt-4 grid grid-cols-10 gap-1.5"
                aria-hidden="true"
            >

                @for($seatIndex = 1; $seatIndex <= 50; $seatIndex++)

                    @php
                        $seatThreshold =
                            ($seatIndex / 50) * 100;

                        $seatOccupied =
                            $seatThreshold
                            <= $occupiedPercentage;
                    @endphp


                    <span
                        @class([
                            'aspect-square rounded-sm',
                            'bg-warning/70' => $seatOccupied,
                            'bg-success/20' => ! $seatOccupied,
                        ])
                    ></span>

                @endfor

            </div>


            <div
                class="mt-4 flex flex-wrap items-center gap-4"
            >

                <span
                    class="inline-flex items-center gap-1.5 text-[11px] font-bold app-muted"
                >
                    <span
                        class="h-2.5 w-2.5 rounded-sm bg-success/20"
                    ></span>

                    Ghế còn
                </span>


                <span
                    class="inline-flex items-center gap-1.5 text-[11px] font-bold app-muted"
                >
                    <span
                        class="h-2.5 w-2.5 rounded-sm bg-warning/70"
                    ></span>

                    Đã sử dụng
                </span>

            </div>

        </div>

    @endif


    {{-- =====================================================
        CUSTOMER HINT
    ====================================================== --}}
    @if($showHint)

        <div
            class="mt-5 rounded-2xl border p-4 {{ $state['badgeClass'] }}"
        >

            <div
                class="flex items-start gap-3"
            >

                <span
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $state['accentClass'] }}"
                >
                    <i
                        class="ph {{ $state['icon'] }}"
                        aria-hidden="true"
                    ></i>
                </span>


                <div>

                    @if($state['key'] === 'sold_out')

                        <p
                            class="text-sm font-black text-error"
                        >
                            Suất chiếu đã hết ghế
                        </p>


                        <p
                            class="mt-1 text-xs leading-5 app-muted"
                        >
                            Hãy thử chọn một khung giờ hoặc ngày chiếu khác.
                        </p>


                    @elseif($state['key'] === 'critical')

                        <p
                            class="text-sm font-black text-error"
                        >
                            Chỉ còn rất ít ghế
                        </p>


                        <p
                            class="mt-1 text-xs leading-5 app-muted"
                        >
                            Nên hoàn tất đặt vé sớm nếu bạn đã chọn được vị trí phù hợp.
                        </p>


                    @elseif($state['key'] === 'limited')

                        <p
                            class="text-sm font-black text-warning"
                        >
                            Ghế đẹp có thể sắp hết
                        </p>


                        <p
                            class="mt-1 text-xs leading-5 app-muted"
                        >
                            Suất chiếu đang có lượng đặt vé khá cao.
                        </p>


                    @elseif($state['key'] === 'good')

                        <p
                            class="text-sm font-black text-success"
                        >
                            Còn nhiều lựa chọn ghế
                        </p>


                        <p
                            class="mt-1 text-xs leading-5 app-muted"
                        >
                            Đây là thời điểm tốt để chọn vị trí ngồi phù hợp.
                        </p>


                    @else

                        <p
                            class="text-sm font-black app-text"
                        >
                            Tình trạng ghế
                        </p>


                        <p
                            class="mt-1 text-xs leading-5 app-muted"
                        >
                            {{ $state['description'] }}
                        </p>

                    @endif

                </div>

            </div>

        </div>

    @endif


    {{-- =====================================================
        LEGEND
    ====================================================== --}}
    @if($showLegend)

        <footer
            class="mt-5 border-t app-border pt-4"
        >

            <p
                class="text-[10px] font-black uppercase tracking-wider app-muted"
            >
                Chú thích
            </p>


            <div
                class="mt-3 flex flex-wrap gap-2"
            >

                <span
                    class="inline-flex items-center gap-1.5 rounded-full border border-success/20 bg-success/5 px-2.5 py-1 text-[11px] font-bold text-success"
                >
                    <i class="ph ph-check-circle"></i>
                    Còn nhiều ghế
                </span>


                <span
                    class="inline-flex items-center gap-1.5 rounded-full border border-warning/20 bg-warning/5 px-2.5 py-1 text-[11px] font-bold text-warning"
                >
                    <i class="ph ph-warning-circle"></i>
                    Còn ít ghế
                </span>


                <span
                    class="inline-flex items-center gap-1.5 rounded-full border border-error/20 bg-error/5 px-2.5 py-1 text-[11px] font-bold text-error"
                >
                    <i class="ph ph-x-circle"></i>
                    Hết ghế
                </span>

            </div>

        </footer>

    @endif


    {{-- =====================================================
        ACCESSIBILITY
    ====================================================== --}}
    <div
        class="sr-only"
        role="status"
        aria-live="polite"
    >
        Suất chiếu còn
        {{ $availableSeats }}
        trên tổng số
        {{ $totalSeats }}
        ghế.
        Tình trạng:
        {{ $state['label'] }}.
    </div>

</section>