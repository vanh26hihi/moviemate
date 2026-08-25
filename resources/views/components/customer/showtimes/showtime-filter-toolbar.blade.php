@props([
    'cinemas' => [],
    'selectedCinemaId' => null,
    'selectedPeriod' => null,
    'selectedPrice' => null,
    'selectedStatus' => null,
    'selectedRoom' => null,
    'rooms' => [],
    'showCinemaFilter' => true,
    'showPeriodFilter' => true,
    'showPriceFilter' => true,
    'showStatusFilter' => true,
    'showRoomFilter' => true,
    'showResetButton' => true,
    'showSummary' => true,
])

@php
    $cinemaCollection = collect($cinemas ?? []);

    $roomCollection = collect($rooms ?? []);

    $periodOptions = [
        'morning' => [
            'label' => 'Buổi sáng',
            'description' => 'Trước 12:00',
            'icon' => 'ph-sun-horizon',
        ],

        'afternoon' => [
            'label' => 'Buổi chiều',
            'description' => '12:00 - 17:59',
            'icon' => 'ph-sun',
        ],

        'evening' => [
            'label' => 'Buổi tối',
            'description' => '18:00 - 21:59',
            'icon' => 'ph-moon-stars',
        ],

        'late' => [
            'label' => 'Suất muộn',
            'description' => 'Từ 22:00',
            'icon' => 'ph-moon',
        ],
    ];

    $priceOptions = [
        'low' => [
            'label' => 'Giá thấp',
            'description' => 'Ưu tiên suất có giá thấp',
        ],

        'medium' => [
            'label' => 'Giá trung bình',
            'description' => 'Khung giá phổ biến',
        ],

        'high' => [
            'label' => 'Giá cao',
            'description' => 'Suất đặc biệt hoặc giờ đẹp',
        ],
    ];

    $statusOptions = [
        'available' => [
            'label' => 'Đang mở bán',
            'icon' => 'ph-ticket',
            'class' => 'text-success',
        ],

        'starting_soon' => [
            'label' => 'Sắp bắt đầu',
            'icon' => 'ph-clock-countdown',
            'class' => 'text-warning',
        ],

        'sold_out' => [
            'label' => 'Hết ghế',
            'icon' => 'ph-armchair',
            'class' => 'text-error',
        ],
    ];

    $selectedCinema = $cinemaCollection->first(
        fn ($cinema) =>
            (string) $cinema->id
            === (string) $selectedCinemaId
    );

    $selectedRoomModel = $roomCollection->first(
        fn ($room) =>
            (string) $room->id
            === (string) $selectedRoom
    );

    $activeFilters = collect([
        $selectedCinemaId
            ? [
                'key' => 'cinema',
                'label' => 'Rạp',
                'value' => $selectedCinema?->name
                    ?? '#'.$selectedCinemaId,
                'icon' => 'ph-buildings',
            ]
            : null,

        $selectedRoom
            ? [
                'key' => 'room',
                'label' => 'Phòng',
                'value' => $selectedRoomModel?->name
                    ?? $selectedRoomModel?->code
                    ?? '#'.$selectedRoom,
                'icon' => 'ph-door-open',
            ]
            : null,

        $selectedPeriod
            ? [
                'key' => 'period',
                'label' => 'Khung giờ',
                'value' => $periodOptions[$selectedPeriod]['label']
                    ?? $selectedPeriod,
                'icon' => $periodOptions[$selectedPeriod]['icon']
                    ?? 'ph-clock',
            ]
            : null,

        $selectedPrice
            ? [
                'key' => 'price',
                'label' => 'Mức giá',
                'value' => $priceOptions[$selectedPrice]['label']
                    ?? $selectedPrice,
                'icon' => 'ph-currency-circle-dollar',
            ]
            : null,

        $selectedStatus
            ? [
                'key' => 'status',
                'label' => 'Trạng thái',
                'value' => $statusOptions[$selectedStatus]['label']
                    ?? $selectedStatus,
                'icon' => $statusOptions[$selectedStatus]['icon']
                    ?? 'ph-info',
            ]
            : null,
    ])->filter();

    $activeFilterCount = $activeFilters->count();
@endphp


<section
    {{ $attributes->class([
        'overflow-hidden rounded-3xl border app-border app-card',
    ]) }}
    data-showtime-filter-toolbar
>

    {{-- =====================================================
        HEADER
    ====================================================== --}}
    <div
        class="relative overflow-hidden border-b app-border p-5 sm:p-6"
    >

        <div
            class="pointer-events-none absolute inset-0 bg-gradient-to-r from-brand-start/5 via-transparent to-ai-start/5"
            aria-hidden="true"
        ></div>


        <div
            class="relative flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
        >

            <div
                class="flex items-start gap-3"
            >

                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-brand-start/10 text-brand-start"
                >
                    <i
                        class="ph ph-funnel text-xl"
                        aria-hidden="true"
                    ></i>
                </div>


                <div>

                    <p
                        class="text-[10px] font-black uppercase tracking-[0.18em] app-muted"
                    >
                        Bộ lọc suất chiếu
                    </p>

                    <h3
                        class="mt-1 text-xl font-black app-heading"
                    >
                        Tìm suất chiếu phù hợp
                    </h3>

                    <p
                        class="mt-1 max-w-2xl text-sm leading-6 app-muted"
                    >
                        Lọc nhanh theo rạp, phòng, thời gian,
                        mức giá và trạng thái mở bán.
                    </p>

                </div>

            </div>


            <div
                class="flex flex-wrap items-center gap-2"
            >

                <span
                    class="inline-flex items-center gap-2 rounded-xl border app-border app-card-soft px-3 py-2 text-xs font-bold app-muted"
                >
                    <i
                        class="ph ph-sliders-horizontal"
                        aria-hidden="true"
                    ></i>

                    {{ $activeFilterCount }}
                    bộ lọc đang dùng
                </span>


                @if(
                    $showResetButton
                    && $activeFilterCount > 0
                )

                    <a
                        href="{{ request()->url() }}"
                        class="inline-flex items-center gap-2 rounded-xl border border-error/20 bg-error/5 px-3 py-2 text-xs font-black text-error transition hover:bg-error/10"
                    >
                        <i
                            class="ph ph-arrow-counter-clockwise"
                            aria-hidden="true"
                        ></i>

                        Xóa bộ lọc
                    </a>

                @endif

            </div>

        </div>

    </div>


    {{-- =====================================================
        FILTER FORM
    ====================================================== --}}
    <form
        method="GET"
        action="{{ request()->url() }}"
        class="p-5 sm:p-6"
    >

        @foreach(request()->except([
            'cinema_id',
            'room_id',
            'period',
            'price_range',
            'status',
            'page',
        ]) as $queryKey => $queryValue)

            @if(is_scalar($queryValue))

                <input
                    type="hidden"
                    name="{{ $queryKey }}"
                    value="{{ $queryValue }}"
                >

            @endif

        @endforeach


        <div
            class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3"
        >

            {{-- =================================================
                CINEMA FILTER
            ================================================== --}}
            @if($showCinemaFilter)

                <div>

                    <label
                        for="showtime-filter-cinema"
                        class="mb-2 flex items-center gap-2 text-xs font-black uppercase tracking-wider app-muted"
                    >
                        <i
                            class="ph ph-buildings text-brand-start"
                            aria-hidden="true"
                        ></i>

                        Rạp
                    </label>


                    <div
                        class="relative"
                    >

                        <select
                            id="showtime-filter-cinema"
                            name="cinema_id"
                            class="cinema-input appearance-none pr-10"
                        >

                            <option value="">
                                Tất cả rạp
                            </option>


                            @foreach($cinemaCollection as $cinema)

                                <option
                                    value="{{ $cinema->id }}"
                                    @selected(
                                        (string) $selectedCinemaId
                                        === (string) $cinema->id
                                    )
                                >
                                    {{ $cinema->name }}
                                </option>

                            @endforeach

                        </select>


                        <i
                            class="ph ph-caret-down pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 app-muted"
                            aria-hidden="true"
                        ></i>

                    </div>

                </div>

            @endif


            {{-- =================================================
                ROOM FILTER
            ================================================== --}}
            @if($showRoomFilter)

                <div>

                    <label
                        for="showtime-filter-room"
                        class="mb-2 flex items-center gap-2 text-xs font-black uppercase tracking-wider app-muted"
                    >
                        <i
                            class="ph ph-door-open text-ai-start"
                            aria-hidden="true"
                        ></i>

                        Phòng
                    </label>


                    <div
                        class="relative"
                    >

                        <select
                            id="showtime-filter-room"
                            name="room_id"
                            class="cinema-input appearance-none pr-10"
                        >

                            <option value="">
                                Tất cả phòng
                            </option>


                            @foreach($roomCollection as $room)

                                <option
                                    value="{{ $room->id }}"
                                    @selected(
                                        (string) $selectedRoom
                                        === (string) $room->id
                                    )
                                >
                                    {{ $room->name ?: $room->code }}
                                </option>

                            @endforeach

                        </select>


                        <i
                            class="ph ph-caret-down pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 app-muted"
                            aria-hidden="true"
                        ></i>

                    </div>

                </div>

            @endif


            {{-- =================================================
                PERIOD FILTER
            ================================================== --}}
            @if($showPeriodFilter)

                <div>

                    <label
                        for="showtime-filter-period"
                        class="mb-2 flex items-center gap-2 text-xs font-black uppercase tracking-wider app-muted"
                    >
                        <i
                            class="ph ph-clock text-warning"
                            aria-hidden="true"
                        ></i>

                        Khung giờ
                    </label>


                    <div
                        class="relative"
                    >

                        <select
                            id="showtime-filter-period"
                            name="period"
                            class="cinema-input appearance-none pr-10"
                        >

                            <option value="">
                                Tất cả khung giờ
                            </option>


                            @foreach($periodOptions as $periodKey => $period)

                                <option
                                    value="{{ $periodKey }}"
                                    @selected(
                                        $selectedPeriod === $periodKey
                                    )
                                >
                                    {{ $period['label'] }}
                                    — {{ $period['description'] }}
                                </option>

                            @endforeach

                        </select>


                        <i
                            class="ph ph-caret-down pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 app-muted"
                            aria-hidden="true"
                        ></i>

                    </div>

                </div>

            @endif


            {{-- =================================================
                PRICE FILTER
            ================================================== --}}
            @if($showPriceFilter)

                <div>

                    <label
                        for="showtime-filter-price"
                        class="mb-2 flex items-center gap-2 text-xs font-black uppercase tracking-wider app-muted"
                    >
                        <i
                            class="ph ph-currency-circle-dollar text-success"
                            aria-hidden="true"
                        ></i>

                        Mức giá
                    </label>


                    <div
                        class="relative"
                    >

                        <select
                            id="showtime-filter-price"
                            name="price_range"
                            class="cinema-input appearance-none pr-10"
                        >

                            <option value="">
                                Tất cả mức giá
                            </option>


                            @foreach($priceOptions as $priceKey => $price)

                                <option
                                    value="{{ $priceKey }}"
                                    @selected(
                                        $selectedPrice === $priceKey
                                    )
                                >
                                    {{ $price['label'] }}
                                </option>

                            @endforeach

                        </select>


                        <i
                            class="ph ph-caret-down pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 app-muted"
                            aria-hidden="true"
                        ></i>

                    </div>

                </div>

            @endif


            {{-- =================================================
                STATUS FILTER
            ================================================== --}}
            @if($showStatusFilter)

                <div>

                    <label
                        for="showtime-filter-status"
                        class="mb-2 flex items-center gap-2 text-xs font-black uppercase tracking-wider app-muted"
                    >
                        <i
                            class="ph ph-info text-brand-start"
                            aria-hidden="true"
                        ></i>

                        Trạng thái
                    </label>


                    <div
                        class="relative"
                    >

                        <select
                            id="showtime-filter-status"
                            name="status"
                            class="cinema-input appearance-none pr-10"
                        >

                            <option value="">
                                Tất cả trạng thái
                            </option>


                            @foreach($statusOptions as $statusKey => $status)

                                <option
                                    value="{{ $statusKey }}"
                                    @selected(
                                        $selectedStatus === $statusKey
                                    )
                                >
                                    {{ $status['label'] }}
                                </option>

                            @endforeach

                        </select>


                        <i
                            class="ph ph-caret-down pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 app-muted"
                            aria-hidden="true"
                        ></i>

                    </div>

                </div>

            @endif


            {{-- =================================================
                SUBMIT
            ================================================== --}}
            <div
                class="flex items-end"
            >

                <button
                    type="submit"
                    class="group inline-flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-brand-start to-brand-end px-4 py-3 text-sm font-black text-white transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-brand-start/20"
                >

                    <i
                        class="ph ph-magnifying-glass"
                        aria-hidden="true"
                    ></i>

                    Áp dụng bộ lọc

                    <i
                        class="ph ph-arrow-right transition-transform group-hover:translate-x-1"
                        aria-hidden="true"
                    ></i>

                </button>

            </div>

        </div>


        {{-- =================================================
            QUICK PERIOD BUTTONS
        ================================================== --}}
        @if($showPeriodFilter)

            <div
                class="mt-6 border-t app-border pt-5"
            >

                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >

                    <div>

                        <p
                            class="text-xs font-black uppercase tracking-wider app-muted"
                        >
                            Chọn nhanh theo thời gian
                        </p>

                        <p
                            class="mt-1 text-xs app-muted"
                        >
                            Chuyển nhanh sang khung giờ bạn muốn xem.
                        </p>

                    </div>

                </div>


                <div
                    class="mt-4 grid grid-cols-2 gap-2 lg:grid-cols-4"
                >

                    @foreach($periodOptions as $periodKey => $period)

                        <button
                            type="submit"
                            name="period"
                            value="{{ $periodKey }}"
                            @class([
                                'group rounded-2xl border p-4 text-left transition duration-200',
                                'border-brand-start bg-brand-start/5' => $selectedPeriod === $periodKey,
                                'app-border app-card-soft hover:-translate-y-0.5 hover:border-brand-start/40 hover:shadow-md' => $selectedPeriod !== $periodKey,
                            ])
                        >

                            <div
                                class="flex items-start justify-between gap-3"
                            >

                                <span
                                    @class([
                                        'flex h-10 w-10 items-center justify-center rounded-xl',
                                        'bg-brand-start text-white' => $selectedPeriod === $periodKey,
                                        'bg-brand-start/10 text-brand-start' => $selectedPeriod !== $periodKey,
                                    ])
                                >
                                    <i
                                        class="ph {{ $period['icon'] }}"
                                        aria-hidden="true"
                                    ></i>
                                </span>


                                @if($selectedPeriod === $periodKey)

                                    <i
                                        class="ph ph-check-circle text-brand-start"
                                        aria-hidden="true"
                                    ></i>

                                @endif

                            </div>


                            <p
                                class="mt-3 text-sm font-black app-text"
                            >
                                {{ $period['label'] }}
                            </p>


                            <p
                                class="mt-1 text-xs app-muted"
                            >
                                {{ $period['description'] }}
                            </p>

                        </button>

                    @endforeach

                </div>

            </div>

        @endif

    </form>


    {{-- =====================================================
        ACTIVE FILTER SUMMARY
    ====================================================== --}}
    @if(
        $showSummary
        && $activeFilterCount > 0
    )

        <div
            class="border-t app-border bg-slate-500/5 px-5 py-4 sm:px-6"
        >

            <div
                class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
            >

                <div>

                    <div
                        class="flex items-center gap-2"
                    >
                        <i
                            class="ph ph-check-circle text-success"
                            aria-hidden="true"
                        ></i>

                        <p
                            class="text-xs font-black uppercase tracking-wider app-muted"
                        >
                            Đang áp dụng
                        </p>
                    </div>

                </div>


                <div
                    class="flex flex-wrap gap-2"
                >

                    @foreach($activeFilters as $filter)

                        <span
                            class="inline-flex items-center gap-2 rounded-full border border-brand-start/20 bg-brand-start/5 px-3 py-1.5 text-xs font-bold text-brand-start"
                        >

                            <i
                                class="ph {{ $filter['icon'] }}"
                                aria-hidden="true"
                            ></i>

                            <span class="app-muted">
                                {{ $filter['label'] }}:
                            </span>

                            <strong>
                                {{ $filter['value'] }}
                            </strong>

                        </span>

                    @endforeach

                </div>

            </div>

        </div>

    @endif


    {{-- =====================================================
        FILTER HELP
    ====================================================== --}}
    <footer
        class="border-t app-border px-5 py-4 sm:px-6"
    >

        <div
            class="grid gap-3 md:grid-cols-3"
        >

            <div
                class="flex items-start gap-2"
            >
                <i
                    class="ph ph-buildings mt-0.5 text-brand-start"
                    aria-hidden="true"
                ></i>

                <div>
                    <p class="text-xs font-bold app-text">
                        Chọn rạp
                    </p>

                    <p class="mt-1 text-[11px] leading-5 app-muted">
                        Giới hạn kết quả theo địa điểm bạn muốn xem.
                    </p>
                </div>
            </div>


            <div
                class="flex items-start gap-2"
            >
                <i
                    class="ph ph-clock mt-0.5 text-warning"
                    aria-hidden="true"
                ></i>

                <div>
                    <p class="text-xs font-bold app-text">
                        Chọn khung giờ
                    </p>

                    <p class="mt-1 text-[11px] leading-5 app-muted">
                        Lọc nhanh theo sáng, chiều, tối hoặc suất muộn.
                    </p>
                </div>
            </div>


            <div
                class="flex items-start gap-2"
            >
                <i
                    class="ph ph-ticket mt-0.5 text-success"
                    aria-hidden="true"
                ></i>

                <div>
                    <p class="text-xs font-bold app-text">
                        Chọn trạng thái
                    </p>

                    <p class="mt-1 text-[11px] leading-5 app-muted">
                        Ưu tiên suất còn khả năng nhận đặt vé.
                    </p>
                </div>
            </div>

        </div>

    </footer>


    {{-- =====================================================
        ACCESSIBILITY
    ====================================================== --}}
    <div
        class="sr-only"
        role="status"
        aria-live="polite"
    >
        Có {{ $activeFilterCount }} bộ lọc suất chiếu đang được áp dụng.
    </div>

</section>