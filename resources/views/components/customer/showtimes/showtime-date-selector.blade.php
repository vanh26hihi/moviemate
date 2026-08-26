@props([
    'selectedDate' => null,
    'days' => 7,
    'queryKey' => 'date',
    'showHeader' => true,
    'showTodayButton' => true,
    'showNavigation' => true,
    'showLegend' => true,
])

@php
    use Carbon\Carbon;

    try {
        $currentDate = $selectedDate
            ? Carbon::parse($selectedDate)->startOfDay()
            : now()->startOfDay();
    } catch (\Throwable) {
        $currentDate = now()->startOfDay();
    }

    $today = now()->startOfDay();

    $days = max(3, min((int) $days, 14));

    $dates = collect(range(0, $days - 1))
        ->map(fn (int $offset) => $today->copy()->addDays($offset));

    $weekdayLabels = [
        0 => 'CN',
        1 => 'T2',
        2 => 'T3',
        3 => 'T4',
        4 => 'T5',
        5 => 'T6',
        6 => 'T7',
    ];

    $weekdayFullLabels = [
        0 => 'Chủ nhật',
        1 => 'Thứ hai',
        2 => 'Thứ ba',
        3 => 'Thứ tư',
        4 => 'Thứ năm',
        5 => 'Thứ sáu',
        6 => 'Thứ bảy',
    ];

    $selectedLabel = $currentDate->isToday()
        ? 'Hôm nay'
        : ($weekdayFullLabels[$currentDate->dayOfWeek] ?? '');

    $selectedFormatted = $currentDate->format('d/m/Y');

    $todayUrl = request()->fullUrlWithQuery([
        $queryKey => $today->format('Y-m-d'),
    ]);

    $previousDate = $currentDate
        ->copy()
        ->subDay();

    $nextDate = $currentDate
        ->copy()
        ->addDay();

    $previousUrl = request()->fullUrlWithQuery([
        $queryKey => $previousDate->format('Y-m-d'),
    ]);

    $nextUrl = request()->fullUrlWithQuery([
        $queryKey => $nextDate->format('Y-m-d'),
    ]);

    $canGoPrevious = $previousDate->gte($today);
@endphp


<section
    {{ $attributes->class([
        'relative overflow-hidden rounded-3xl border app-border app-card',
    ]) }}
    data-showtime-date-selector
    data-selected-date="{{ $currentDate->format('Y-m-d') }}"
>

    {{-- =====================================================
        DECORATIVE HEADER LINE
    ====================================================== --}}
    <div
        class="h-1 w-full bg-gradient-to-r from-brand-start via-ai-start to-brand-end"
        aria-hidden="true"
    ></div>


    <div class="p-5 sm:p-6">

        {{-- =================================================
            HEADER
        ================================================== --}}
        @if($showHeader)

            <header
                class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
            >

                <div class="flex items-start gap-3">

                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-brand-start/10 text-brand-start"
                    >
                        <i
                            class="ph ph-calendar-dots text-xl"
                            aria-hidden="true"
                        ></i>
                    </div>


                    <div>

                        <p
                            class="text-[10px] font-black uppercase tracking-[0.18em] app-muted"
                        >
                            Lịch chiếu
                        </p>

                        <h3
                            class="mt-1 text-xl font-black app-heading sm:text-2xl"
                        >
                            Chọn ngày xem phim
                        </h3>

                        <p
                            class="mt-1 max-w-xl text-sm leading-6 app-muted"
                        >
                            Chọn ngày phù hợp để xem các suất chiếu đang mở bán.
                        </p>

                    </div>

                </div>


                {{-- CURRENT DATE --}}
                <div
                    class="flex items-center gap-3 rounded-2xl border app-border app-card-soft px-4 py-3"
                >

                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-ai-start/10 text-ai-start"
                    >
                        <i
                            class="ph ph-calendar-check"
                            aria-hidden="true"
                        ></i>
                    </div>


                    <div>

                        <p
                            class="text-[10px] font-bold uppercase tracking-wide app-muted"
                        >
                            Đang chọn
                        </p>

                        <p
                            class="mt-0.5 text-sm font-black app-text"
                        >
                            {{ $selectedLabel }},
                            {{ $selectedFormatted }}
                        </p>

                    </div>

                </div>

            </header>

        @endif


        {{-- =================================================
            TOOLBAR
        ================================================== --}}
        <div
            class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >

            <div
                class="flex flex-wrap items-center gap-2"
            >

                @if($showTodayButton)

                    <a
                        href="{{ $todayUrl }}"
                        @class([
                            'inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-extrabold transition',
                            'border-brand-start bg-brand-start text-white' => $currentDate->isToday(),
                            'app-border app-card-soft app-text hover:border-brand-start/50' => ! $currentDate->isToday(),
                        ])
                    >
                        <i
                            class="ph ph-calendar-check"
                            aria-hidden="true"
                        ></i>

                        Hôm nay
                    </a>

                @endif


                <span
                    class="inline-flex items-center gap-2 rounded-xl border app-border px-3 py-2 text-xs font-bold app-muted"
                >
                    <i
                        class="ph ph-clock"
                        aria-hidden="true"
                    ></i>

                    Giờ địa phương
                </span>

            </div>


            {{-- NAVIGATION --}}
            @if($showNavigation)

                <div
                    class="flex items-center gap-2"
                >

                    @if($canGoPrevious)

                        <a
                            href="{{ $previousUrl }}"
                            class="flex h-10 w-10 items-center justify-center rounded-xl border app-border app-card-soft app-text transition hover:border-brand-start hover:text-brand-start"
                            aria-label="Ngày trước"
                            title="Ngày trước"
                        >
                            <i
                                class="ph ph-caret-left"
                                aria-hidden="true"
                            ></i>
                        </a>

                    @else

                        <button
                            type="button"
                            disabled
                            class="flex h-10 w-10 cursor-not-allowed items-center justify-center rounded-xl border app-border opacity-40"
                            aria-label="Không thể chọn ngày trước"
                        >
                            <i
                                class="ph ph-caret-left"
                                aria-hidden="true"
                            ></i>
                        </button>

                    @endif


                    <a
                        href="{{ $nextUrl }}"
                        class="flex h-10 w-10 items-center justify-center rounded-xl border app-border app-card-soft app-text transition hover:border-brand-start hover:text-brand-start"
                        aria-label="Ngày tiếp theo"
                        title="Ngày tiếp theo"
                    >
                        <i
                            class="ph ph-caret-right"
                            aria-hidden="true"
                        ></i>
                    </a>

                </div>

            @endif

        </div>


        {{-- =================================================
            DATE RAIL
        ================================================== --}}
        <div
            class="relative mt-5"
        >

            <div
                class="flex gap-3 overflow-x-auto pb-2"
                role="list"
                aria-label="Danh sách ngày chiếu"
            >

                @foreach($dates as $date)

                    @php
                        $isSelected = $date->isSameDay(
                            $currentDate
                        );

                        $isToday = $date->isToday();

                        $isWeekend = in_array(
                            $date->dayOfWeek,
                            [0, 6],
                            true
                        );

                        $url = request()->fullUrlWithQuery([
                            $queryKey => $date->format('Y-m-d'),
                        ]);
                    @endphp


                    <a
                        href="{{ $url }}"
                        role="listitem"
                        @class([
                            'group relative min-w-[92px] flex-1 rounded-2xl border px-3 py-4 text-center transition-all duration-200',
                            'border-brand-start bg-brand-start text-white shadow-lg shadow-brand-start/10 -translate-y-0.5' => $isSelected,
                            'app-border app-card-soft hover:-translate-y-0.5 hover:border-brand-start/50 hover:shadow-md' => ! $isSelected,
                        ])
                        aria-current="{{ $isSelected ? 'date' : 'false' }}"
                    >

                        {{-- TODAY BADGE --}}
                        @if($isToday)

                            <span
                                @class([
                                    'absolute -top-2 left-1/2 -translate-x-1/2 rounded-full px-2 py-0.5 text-[9px] font-black uppercase tracking-wide',
                                    'bg-white text-brand-start' => $isSelected,
                                    'bg-brand-start text-white' => ! $isSelected,
                                ])
                            >
                                Hôm nay
                            </span>

                        @endif


                        <p
                            @class([
                                'text-[10px] font-black uppercase tracking-wider',
                                'text-white/80' => $isSelected,
                                'text-error' => ! $isSelected && $isWeekend,
                                'app-muted' => ! $isSelected && ! $isWeekend,
                            ])
                        >
                            {{ $weekdayLabels[$date->dayOfWeek] ?? '' }}
                        </p>


                        <p
                            @class([
                                'mt-1 text-2xl font-black',
                                'text-white' => $isSelected,
                                'app-heading group-hover:text-brand-start' => ! $isSelected,
                            ])
                        >
                            {{ $date->format('d') }}
                        </p>


                        <p
                            @class([
                                'mt-0.5 text-[11px] font-bold',
                                'text-white/80' => $isSelected,
                                'app-muted' => ! $isSelected,
                            ])
                        >
                            Tháng {{ $date->format('m') }}
                        </p>


                        {{-- SELECTED INDICATOR --}}
                        @if($isSelected)

                            <div
                                class="mx-auto mt-2 flex h-5 w-5 items-center justify-center rounded-full bg-white/20"
                            >
                                <i
                                    class="ph ph-check text-xs text-white"
                                    aria-hidden="true"
                                ></i>
                            </div>

                        @else

                            <div
                                class="mx-auto mt-2 h-5 w-5"
                                aria-hidden="true"
                            ></div>

                        @endif

                    </a>

                @endforeach

            </div>

        </div>


        {{-- =================================================
            SELECTED DATE DETAIL
        ================================================== --}}
        <div
            class="mt-5 grid gap-3 md:grid-cols-3"
        >

            <div
                class="rounded-2xl border app-border app-card-soft p-4"
            >

                <div class="flex items-center gap-3">

                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-start/10 text-brand-start"
                    >
                        <i
                            class="ph ph-calendar"
                            aria-hidden="true"
                        ></i>
                    </span>


                    <div>

                        <p
                            class="text-[10px] font-black uppercase tracking-wide app-muted"
                        >
                            Ngày đã chọn
                        </p>

                        <p
                            class="mt-1 text-sm font-black app-text"
                        >
                            {{ $selectedFormatted }}
                        </p>

                    </div>

                </div>

            </div>


            <div
                class="rounded-2xl border app-border app-card-soft p-4"
            >

                <div class="flex items-center gap-3">

                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-ai-start/10 text-ai-start"
                    >
                        <i
                            class="ph ph-calendar-dots"
                            aria-hidden="true"
                        ></i>
                    </span>


                    <div>

                        <p
                            class="text-[10px] font-black uppercase tracking-wide app-muted"
                        >
                            Thứ
                        </p>

                        <p
                            class="mt-1 text-sm font-black app-text"
                        >
                            {{ $weekdayFullLabels[$currentDate->dayOfWeek] ?? '' }}
                        </p>

                    </div>

                </div>

            </div>


            <div
                class="rounded-2xl border app-border app-card-soft p-4"
            >

                <div class="flex items-center gap-3">

                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-success/10 text-success"
                    >
                        <i
                            class="ph ph-ticket"
                            aria-hidden="true"
                        ></i>
                    </span>


                    <div>

                        <p
                            class="text-[10px] font-black uppercase tracking-wide app-muted"
                        >
                            Tiếp theo
                        </p>

                        <p
                            class="mt-1 text-sm font-black app-text"
                        >
                            Chọn suất chiếu
                        </p>

                    </div>

                </div>

            </div>

        </div>


        {{-- =================================================
            USER TIP
        ================================================== --}}
        <div
            class="mt-5 flex items-start gap-3 rounded-2xl border border-brand-start/10 bg-brand-start/5 p-4"
        >

            <span
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-start/10 text-brand-start"
            >
                <i
                    class="ph ph-lightbulb"
                    aria-hidden="true"
                ></i>
            </span>


            <div>

                <p
                    class="text-sm font-extrabold app-text"
                >
                    Mẹo chọn lịch chiếu
                </p>

                <p
                    class="mt-1 text-xs leading-5 app-muted"
                >
                    Các suất chiếu buổi tối và cuối tuần thường được đặt nhanh hơn.
                    Bạn nên chọn giờ chiếu và ghế sớm để có vị trí phù hợp.
                </p>

            </div>

        </div>


        {{-- =================================================
            LEGEND
        ================================================== --}}
        @if($showLegend)

            <footer
                class="mt-5 flex flex-col gap-3 border-t app-border pt-4 sm:flex-row sm:items-center sm:justify-between"
            >

                <div
                    class="flex flex-wrap items-center gap-x-4 gap-y-2"
                >

                    <span
                        class="inline-flex items-center gap-1.5 text-[11px] font-bold app-muted"
                    >
                        <span
                            class="h-2.5 w-2.5 rounded-full bg-brand-start"
                        ></span>

                        Ngày đang chọn
                    </span>


                    <span
                        class="inline-flex items-center gap-1.5 text-[11px] font-bold app-muted"
                    >
                        <span
                            class="h-2.5 w-2.5 rounded-full border border-error bg-error/10"
                        ></span>

                        Cuối tuần
                    </span>


                    <span
                        class="inline-flex items-center gap-1.5 text-[11px] font-bold app-muted"
                    >
                        <i
                            class="ph ph-calendar-check text-brand-start"
                            aria-hidden="true"
                        ></i>

                        Hôm nay
                    </span>

                </div>


                <p
                    class="text-[10px] font-bold uppercase tracking-wide app-muted"
                >
                    Hiển thị {{ $days }} ngày gần nhất
                </p>

            </footer>

        @endif

    </div>


    {{-- =====================================================
        ACCESSIBILITY
    ====================================================== --}}
    <div
        class="sr-only"
        role="status"
        aria-live="polite"
    >
        Ngày chiếu đang chọn:
        {{ $weekdayFullLabels[$currentDate->dayOfWeek] ?? '' }},
        {{ $selectedFormatted }}.
    </div>

</section>