@props([
    'dates' => [],
    'selectedDate' => null,
    'dateParam' => 'date',
    'showShowtimeCount' => true,
    'showTodayShortcut' => true,
    'showNavigation' => true,
    'preserveQuery' => true,
])

@php
    use Carbon\Carbon;

    $today = now()->startOfDay();

    $selected = $selectedDate
        ? Carbon::parse($selectedDate)->startOfDay()
        : $today->copy();

    $dateCollection = collect($dates)
        ->map(function ($item) use ($today) {

            if (is_array($item)) {
                $dateValue = $item['date'] ?? null;

                $showtimeCount = (int) (
                    $item['showtime_count']
                    ?? $item['count']
                    ?? 0
                );
            } else {
                $dateValue = $item;
                $showtimeCount = 0;
            }

            if (!$dateValue) {
                return null;
            }

            $date = Carbon::parse(
                $dateValue
            )->startOfDay();

            $diff = $today->diffInDays(
                $date,
                false
            );

            $relativeLabel = match ($diff) {
                0 => 'Hôm nay',
                1 => 'Ngày mai',
                2 => 'Ngày kia',
                default => null,
            };

            $dayName = match ($date->dayOfWeek) {
                Carbon::MONDAY => 'Thứ 2',
                Carbon::TUESDAY => 'Thứ 3',
                Carbon::WEDNESDAY => 'Thứ 4',
                Carbon::THURSDAY => 'Thứ 5',
                Carbon::FRIDAY => 'Thứ 6',
                Carbon::SATURDAY => 'Thứ 7',
                Carbon::SUNDAY => 'Chủ nhật',
            };

            return [
                'date' => $date,
                'value' => $date->format('Y-m-d'),
                'day' => $date->format('d'),
                'month' => $date->format('m'),
                'year' => $date->format('Y'),
                'day_name' => $dayName,
                'relative_label' => $relativeLabel,
                'showtime_count' => $showtimeCount,
                'is_today' => $date->isSameDay($today),
                'is_past' => $date->lt($today),
                'has_showtimes' => $showtimeCount > 0,
            ];
        })
        ->filter()
        ->values();

    $buildDateUrl = function ($dateValue) use (
        $dateParam,
        $preserveQuery
    ) {
        $query = $preserveQuery
            ? request()->query()
            : [];

        $query[$dateParam] = $dateValue;

        unset($query['page']);

        return request()->url()
            . '?'
            . http_build_query($query);
    };

    $previousDate = $selected
        ->copy()
        ->subDay();

    $nextDate = $selected
        ->copy()
        ->addDay();

    $canGoPrevious = $previousDate
        ->gte($today);

    $selectedItem = $dateCollection
        ->first(
            fn ($item) =>
                $item['date']->isSameDay($selected)
        );

    $totalShowtimes = $dateCollection
        ->sum('showtime_count');
@endphp


<section
    {{ $attributes->class([
        'overflow-hidden rounded-3xl border app-border app-card',
    ]) }}
    data-showtime-date-navigation
>

    {{-- HEADER --}}
    <header
        class="relative overflow-hidden border-b app-border p-5 sm:p-6"
    >

        <div
            class="pointer-events-none absolute inset-0 bg-gradient-to-r from-brand-start/5 via-transparent to-ai-start/5"
            aria-hidden="true"
        ></div>


        <div
            class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >

            <div
                class="flex items-start gap-3"
            >

                <span
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-brand-start/10 text-brand-start"
                >
                    <i
                        class="ph ph-calendar-dots text-xl"
                        aria-hidden="true"
                    ></i>
                </span>


                <div>

                    <p
                        class="text-[10px] font-black uppercase tracking-[0.18em] app-muted"
                    >
                        Lịch chiếu
                    </p>


                    <h3
                        class="mt-1 text-xl font-black app-heading"
                    >
                        Chọn ngày xem phim
                    </h3>


                    <p
                        class="mt-1 text-xs leading-5 app-muted"
                    >
                        Chọn ngày để xem các suất chiếu đang khả dụng.
                    </p>

                </div>

            </div>


            @if($selectedItem)

                <div
                    class="rounded-2xl border app-border app-card-soft px-4 py-3"
                >

                    <p
                        class="text-[9px] font-black uppercase tracking-wide app-muted"
                    >
                        Đang xem
                    </p>


                    <p
                        class="mt-1 text-sm font-black app-heading"
                    >
                        {{ $selectedItem['relative_label']
                            ?: $selectedItem['day_name'] }}
                    </p>


                    <p
                        class="mt-0.5 text-xs app-muted"
                    >
                        {{ $selected->format('d/m/Y') }}
                    </p>

                </div>

            @endif

        </div>

    </header>


    {{-- SHORTCUTS --}}
    @if($showTodayShortcut)

        <div
            class="border-b app-border px-5 py-4 sm:px-6"
        >

            <div
                class="flex flex-wrap items-center gap-2"
            >

                <span
                    class="mr-1 text-[10px] font-black uppercase tracking-wide app-muted"
                >
                    Chọn nhanh
                </span>


                <a
                    href="{{ $buildDateUrl(
                        $today->format('Y-m-d')
                    ) }}"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-xl border px-3 py-2 text-xs font-black transition',
                        'border-brand-start bg-brand-start text-white' => $selected->isSameDay($today),
                        'app-border app-card-soft app-text hover:border-brand-start/40 hover:text-brand-start' => !$selected->isSameDay($today),
                    ])
                >
                    <i
                        class="ph ph-calendar-check"
                        aria-hidden="true"
                    ></i>

                    Hôm nay
                </a>


                <a
                    href="{{ $buildDateUrl(
                        $today->copy()->addDay()->format('Y-m-d')
                    ) }}"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-xl border px-3 py-2 text-xs font-black transition',
                        'border-brand-start bg-brand-start text-white' => $selected->isSameDay($today->copy()->addDay()),
                        'app-border app-card-soft app-text hover:border-brand-start/40 hover:text-brand-start' => !$selected->isSameDay($today->copy()->addDay()),
                    ])
                >
                    <i
                        class="ph ph-arrow-right"
                        aria-hidden="true"
                    ></i>

                    Ngày mai
                </a>


                <a
                    href="{{ $buildDateUrl(
                        $today->copy()->addDays(2)->format('Y-m-d')
                    ) }}"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-xl border px-3 py-2 text-xs font-black transition',
                        'border-brand-start bg-brand-start text-white' => $selected->isSameDay($today->copy()->addDays(2)),
                        'app-border app-card-soft app-text hover:border-brand-start/40 hover:text-brand-start' => !$selected->isSameDay($today->copy()->addDays(2)),
                    ])
                >
                    <i
                        class="ph ph-calendar-plus"
                        aria-hidden="true"
                    ></i>

                    Ngày kia
                </a>

            </div>

        </div>

    @endif


    {{-- DATE RAIL --}}
    <div
        class="p-4 sm:p-5"
    >

        @if($dateCollection->isNotEmpty())

            <div
                class="flex gap-3 overflow-x-auto pb-2"
                role="list"
                aria-label="Danh sách ngày chiếu"
            >

                @foreach($dateCollection as $item)

                    @php
                        $isSelected =
                            $item['date']->isSameDay(
                                $selected
                            );

                        $disabled =
                            $item['is_past'];
                    @endphp


                    @if($disabled)

                        <div
                            class="min-w-[104px] cursor-not-allowed rounded-2xl border app-border p-3 text-center opacity-40"
                            role="listitem"
                            aria-disabled="true"
                        >

                            <p
                                class="text-[10px] font-bold uppercase app-muted"
                            >
                                {{ $item['day_name'] }}
                            </p>


                            <p
                                class="mt-2 text-2xl font-black app-heading"
                            >
                                {{ $item['day'] }}
                            </p>


                            <p
                                class="text-[10px] app-muted"
                            >
                                Tháng {{ $item['month'] }}
                            </p>

                        </div>


                    @else

                        <a
                            href="{{ $buildDateUrl(
                                $item['value']
                            ) }}"
                            role="listitem"
                            aria-current="{{ $isSelected ? 'date' : 'false' }}"
                            @class([
                                'group relative min-w-[104px] overflow-hidden rounded-2xl border p-3 text-center transition duration-200',
                                'border-brand-start bg-brand-start text-white shadow-lg shadow-brand-start/20' => $isSelected,
                                'app-border app-card-soft hover:-translate-y-0.5 hover:border-brand-start/40 hover:shadow-md' => !$isSelected,
                            ])
                        >

                            @if($item['is_today'])

                                <span
                                    @class([
                                        'absolute right-2 top-2 h-2 w-2 rounded-full',
                                        'bg-white' => $isSelected,
                                        'bg-success' => !$isSelected,
                                    ])
                                    title="Hôm nay"
                                ></span>

                            @endif


                            <p
                                @class([
                                    'text-[10px] font-black uppercase tracking-wide',
                                    'text-white/80' => $isSelected,
                                    'app-muted' => !$isSelected,
                                ])
                            >
                                {{ $item['relative_label']
                                    ?: $item['day_name'] }}
                            </p>


                            <p
                                @class([
                                    'mt-2 text-3xl font-black',
                                    'text-white' => $isSelected,
                                    'app-heading' => !$isSelected,
                                ])
                            >
                                {{ $item['day'] }}
                            </p>


                            <p
                                @class([
                                    'text-[10px] font-bold',
                                    'text-white/70' => $isSelected,
                                    'app-muted' => !$isSelected,
                                ])
                            >
                                Tháng {{ $item['month'] }}
                            </p>


                            @if($showShowtimeCount)

                                <div
                                    @class([
                                        'mt-3 rounded-xl px-2 py-1.5',
                                        'bg-white/15' => $isSelected,
                                        'bg-slate-500/5' => !$isSelected,
                                    ])
                                >

                                    @if($item['has_showtimes'])

                                        <p
                                            @class([
                                                'text-[10px] font-black',
                                                'text-white' => $isSelected,
                                                'text-success' => !$isSelected,
                                            ])
                                        >
                                            {{ $item['showtime_count'] }}
                                            suất
                                        </p>

                                    @else

                                        <p
                                            @class([
                                                'text-[10px] font-bold',
                                                'text-white/60' => $isSelected,
                                                'app-muted' => !$isSelected,
                                            ])
                                        >
                                            Chưa có suất
                                        </p>

                                    @endif

                                </div>

                            @endif

                        </a>

                    @endif

                @endforeach

            </div>


        @else

            <div
                class="rounded-3xl border border-dashed app-border p-8 text-center"
            >

                <span
                    class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-500/10 app-muted"
                >
                    <i
                        class="ph ph-calendar-x text-2xl"
                        aria-hidden="true"
                    ></i>
                </span>


                <h4
                    class="mt-4 font-black app-heading"
                >
                    Chưa có lịch chiếu
                </h4>


                <p
                    class="mx-auto mt-2 max-w-sm text-xs leading-5 app-muted"
                >
                    Hiện chưa có dữ liệu ngày chiếu
                    để hiển thị trong khoảng thời gian này.
                </p>

            </div>

        @endif

    </div>


    {{-- PREVIOUS / NEXT --}}
    @if($showNavigation)

        <div
            class="border-t app-border px-5 py-4 sm:px-6"
        >

            <div
                class="flex items-center justify-between gap-3"
            >

                @if($canGoPrevious)

                    <a
                        href="{{ $buildDateUrl(
                            $previousDate->format('Y-m-d')
                        ) }}"
                        class="group inline-flex items-center gap-2 rounded-xl border app-border px-3 py-2 text-xs font-bold app-text transition hover:border-brand-start/40 hover:text-brand-start"
                    >
                        <i
                            class="ph ph-arrow-left transition-transform group-hover:-translate-x-0.5"
                            aria-hidden="true"
                        ></i>

                        Ngày trước
                    </a>


                @else

                    <span
                        class="inline-flex cursor-not-allowed items-center gap-2 rounded-xl border app-border px-3 py-2 text-xs font-bold app-muted opacity-40"
                    >
                        <i
                            class="ph ph-arrow-left"
                            aria-hidden="true"
                        ></i>

                        Ngày trước
                    </span>

                @endif


                <div
                    class="hidden text-center sm:block"
                >

                    <p
                        class="text-[9px] font-black uppercase tracking-wide app-muted"
                    >
                        Ngày đã chọn
                    </p>


                    <p
                        class="mt-0.5 text-xs font-black app-text"
                    >
                        {{ $selected->format('d/m/Y') }}
                    </p>

                </div>


                <a
                    href="{{ $buildDateUrl(
                        $nextDate->format('Y-m-d')
                    ) }}"
                    class="group inline-flex items-center gap-2 rounded-xl border app-border px-3 py-2 text-xs font-bold app-text transition hover:border-brand-start/40 hover:text-brand-start"
                >
                    Ngày sau

                    <i
                        class="ph ph-arrow-right transition-transform group-hover:translate-x-0.5"
                        aria-hidden="true"
                    ></i>
                </a>

            </div>

        </div>

    @endif


    {{-- SUMMARY --}}
    @if($showShowtimeCount)

        <footer
            class="border-t app-border bg-slate-500/5 px-5 py-4 sm:px-6"
        >

            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >

                <p
                    class="flex items-center gap-2 text-xs app-muted"
                >
                    <i
                        class="ph ph-film-strip text-brand-start"
                        aria-hidden="true"
                    ></i>

                    Tổng cộng
                    <strong class="app-text">
                        {{ number_format($totalShowtimes) }}
                    </strong>
                    suất trong danh sách ngày.
                </p>


                <p
                    class="text-[10px] font-black uppercase tracking-wide app-muted"
                >
                    {{ $dateCollection->count() }}
                    ngày
                </p>

            </div>

        </footer>

    @endif


    {{-- ACCESSIBILITY --}}
    <div
        class="sr-only"
        role="status"
        aria-live="polite"
    >
        Ngày chiếu đang chọn là
        {{ $selected->format('d/m/Y') }}.

        @if($selectedItem)
            Có
            {{ $selectedItem['showtime_count'] }}
            suất chiếu.
        @endif
    </div>

</section>