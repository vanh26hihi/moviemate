@props([
    'cinema',
    'showtimes',
    'selectedShowtimeId' => null,
    'showAddress' => true,
    'showRoomName' => true,
    'showPrice' => true,
    'showStatus' => true,
    'showSeatHint' => true,
    'showEmptyState' => true,
])

@php
    use App\Support\ShowtimePresentation;

    $cinemaName = $cinema?->name
        ?: 'Rạp đang cập nhật';

    $cinemaAddress = $cinema?->address;

    $cinemaPhone = $cinema?->phone;

    $showtimeCollection = collect($showtimes ?? []);

    $groupedByRoom = $showtimeCollection
        ->groupBy(
            fn ($showtime) =>
                $showtime->room?->name
                ?: $showtime->room?->code
                ?: 'Phòng chưa xác định'
        );

    $totalShowtimes = $showtimeCollection->count();

    $availableShowtimes = $showtimeCollection
        ->filter(
            fn ($showtime) =>
                ShowtimePresentation::canSelect($showtime)
        )
        ->count();

    $startingSoonCount = $showtimeCollection
        ->filter(
            fn ($showtime) =>
                ShowtimePresentation::statusMeta($showtime)['key']
                === 'starting_soon'
        )
        ->count();

    $activeRoomCount = $groupedByRoom->count();
@endphp


<section
    {{ $attributes->class([
        'overflow-hidden rounded-3xl border app-border app-card',
    ]) }}
    data-cinema-showtime-group="{{ $cinema?->id }}"
>

    {{-- =====================================================
        CINEMA HEADER
    ====================================================== --}}
    <div
        class="relative overflow-hidden border-b app-border p-5 sm:p-6"
    >

        <div
            class="pointer-events-none absolute inset-0 bg-gradient-to-r from-brand-start/5 via-transparent to-ai-start/5"
            aria-hidden="true"
        ></div>


        <div
            class="relative flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between"
        >

            <div class="flex items-start gap-4">

                <div
                    class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-brand-start/10 text-brand-start"
                >
                    <i
                        class="ph ph-buildings text-2xl"
                        aria-hidden="true"
                    ></i>
                </div>


                <div class="min-w-0">

                    <p
                        class="text-[10px] font-black uppercase tracking-[0.18em] app-muted"
                    >
                        Rạp chiếu
                    </p>


                    <h3
                        class="mt-1 text-xl font-black app-heading sm:text-2xl"
                    >
                        {{ $cinemaName }}
                    </h3>


                    @if(
                        $showAddress
                        && $cinemaAddress
                    )

                        <p
                            class="mt-2 flex items-start gap-2 text-sm leading-6 app-muted"
                        >
                            <i
                                class="ph ph-map-pin mt-0.5 shrink-0 text-brand-start"
                                aria-hidden="true"
                            ></i>

                            <span>
                                {{ $cinemaAddress }}
                            </span>
                        </p>

                    @endif


                    @if($cinemaPhone)

                        <p
                            class="mt-1 flex items-center gap-2 text-xs app-muted"
                        >
                            <i
                                class="ph ph-phone text-brand-start"
                                aria-hidden="true"
                            ></i>

                            {{ $cinemaPhone }}
                        </p>

                    @endif

                </div>

            </div>


            {{-- =================================================
                CINEMA SUMMARY
            ================================================== --}}
            <div
                class="grid grid-cols-2 gap-2 sm:grid-cols-4 xl:min-w-[420px]"
            >

                <div
                    class="rounded-2xl border app-border app-card-soft p-3 text-center"
                >

                    <p
                        class="text-[10px] font-black uppercase tracking-wide app-muted"
                    >
                        Tổng suất
                    </p>

                    <p
                        class="mt-1 text-xl font-black app-heading"
                    >
                        {{ number_format($totalShowtimes) }}
                    </p>

                </div>


                <div
                    class="rounded-2xl border app-border app-card-soft p-3 text-center"
                >

                    <p
                        class="text-[10px] font-black uppercase tracking-wide app-muted"
                    >
                        Có thể đặt
                    </p>

                    <p
                        class="mt-1 text-xl font-black text-success"
                    >
                        {{ number_format($availableShowtimes) }}
                    </p>

                </div>


                <div
                    class="rounded-2xl border app-border app-card-soft p-3 text-center"
                >

                    <p
                        class="text-[10px] font-black uppercase tracking-wide app-muted"
                    >
                        Sắp bắt đầu
                    </p>

                    <p
                        class="mt-1 text-xl font-black text-warning"
                    >
                        {{ number_format($startingSoonCount) }}
                    </p>

                </div>


                <div
                    class="rounded-2xl border app-border app-card-soft p-3 text-center"
                >

                    <p
                        class="text-[10px] font-black uppercase tracking-wide app-muted"
                    >
                        Phòng
                    </p>

                    <p
                        class="mt-1 text-xl font-black text-brand-start"
                    >
                        {{ number_format($activeRoomCount) }}
                    </p>

                </div>

            </div>

        </div>

    </div>


    {{-- =====================================================
        ROOM GROUPS
    ====================================================== --}}
    <div class="space-y-5 p-5 sm:p-6">

        @forelse($groupedByRoom as $roomLabel => $roomShowtimes)

            @php
                $room = $roomShowtimes->first()?->room;

                $roomCapacity =
                    $room?->capacity
                    ?? null;

                $availableInRoom = $roomShowtimes
                    ->filter(
                        fn ($showtime) =>
                            ShowtimePresentation::canSelect(
                                $showtime
                            )
                    )
                    ->count();
            @endphp


            <article
                class="overflow-hidden rounded-3xl border app-border"
                data-showtime-room-group="{{ $room?->id }}"
            >

                {{-- =================================================
                    ROOM HEADER
                ================================================== --}}
                <header
                    class="flex flex-col gap-3 border-b app-border bg-slate-500/5 px-4 py-4 sm:flex-row sm:items-center sm:justify-between"
                >

                    <div
                        class="flex items-center gap-3"
                    >

                        <span
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-ai-start/10 text-ai-start"
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


                            <h4
                                class="mt-0.5 font-black app-text"
                            >
                                {{ $roomLabel }}
                            </h4>


                            @if(
                                $showRoomName
                                && $room?->code
                                && $room?->name !== $room?->code
                            )

                                <p
                                    class="mt-0.5 text-xs app-muted"
                                >
                                    Mã phòng: {{ $room->code }}
                                </p>

                            @endif

                        </div>

                    </div>


                    <div
                        class="flex flex-wrap items-center gap-2"
                    >

                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-success/20 bg-success/5 px-2.5 py-1 text-[11px] font-bold text-success"
                        >
                            <i
                                class="ph ph-ticket"
                                aria-hidden="true"
                            ></i>

                            {{ $availableInRoom }}
                            suất có thể đặt
                        </span>


                        @if($roomCapacity)

                            <span
                                class="inline-flex items-center gap-1.5 rounded-full border app-border px-2.5 py-1 text-[11px] font-bold app-muted"
                            >
                                <i
                                    class="ph ph-armchair"
                                    aria-hidden="true"
                                ></i>

                                {{ number_format($roomCapacity) }}
                                ghế
                            </span>

                        @endif

                    </div>

                </header>


                {{-- =================================================
                    SHOWTIME GRID
                ================================================== --}}
                <div
                    class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3"
                >

                    @foreach(
                        $roomShowtimes->sortBy(
                            fn ($showtime) =>
                                ShowtimePresentation::startAt($showtime)
                        )
                        as $showtime
                    )

                        @php
                            $status = ShowtimePresentation::statusMeta(
                                $showtime
                            );

                            $canSelect = ShowtimePresentation::canSelect(
                                $showtime
                            );

                            $summary = ShowtimePresentation::compactSummary(
                                $showtime
                            );

                            $isSelected =
                                (string) $selectedShowtimeId
                                === (string) $showtime->id;

                            $reason = ShowtimePresentation::selectionReason(
                                $showtime
                            );
                        @endphp


                        <div
                            @class([
                                'group relative overflow-hidden rounded-2xl border transition-all duration-200',
                                'border-brand-start bg-brand-start/5 shadow-md shadow-brand-start/10' => $isSelected,
                                'app-border app-card-soft hover:-translate-y-0.5 hover:border-brand-start/40 hover:shadow-md' => ! $isSelected && $canSelect,
                                'opacity-60' => ! $canSelect,
                            ])
                        >

                            {{-- SELECTED --}}
                            @if($isSelected)

                                <span
                                    class="absolute right-0 top-0 z-10 rounded-bl-xl bg-brand-start px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-white"
                                >
                                    Đã chọn
                                </span>

                            @endif


                            <div class="p-4">

                                {{-- =================================================
                                    TOP
                                ================================================== --}}
                                <div
                                    class="flex items-start justify-between gap-3"
                                >

                                    <div>

                                        <p
                                            class="text-[10px] font-black uppercase tracking-wider app-muted"
                                        >
                                            Giờ bắt đầu
                                        </p>

                                        <p
                                            class="mt-1 text-2xl font-black text-brand-start"
                                        >
                                            {{ $summary['time'] }}
                                        </p>

                                        <p
                                            class="mt-1 text-xs app-muted"
                                        >
                                            {{ $summary['range'] }}
                                        </p>

                                    </div>


                                    @if($showStatus)

                                        <span
                                            class="inline-flex items-center gap-1 rounded-full border px-2 py-1 text-[10px] font-bold {{ $status['class'] }}"
                                            title="{{ $status['description'] }}"
                                        >
                                            <i
                                                class="ph {{ $status['icon'] }}"
                                                aria-hidden="true"
                                            ></i>

                                            {{ $status['label'] }}
                                        </span>

                                    @endif

                                </div>


                                {{-- =================================================
                                    DETAILS
                                ================================================== --}}
                                <div
                                    class="mt-4 space-y-2"
                                >

                                    <div
                                        class="flex items-center justify-between gap-3 text-xs"
                                    >

                                        <span
                                            class="inline-flex items-center gap-1.5 app-muted"
                                        >
                                            <i
                                                class="ph ph-timer"
                                                aria-hidden="true"
                                            ></i>

                                            Thời lượng
                                        </span>

                                        <strong class="app-text">
                                            {{ $summary['duration'] }}
                                        </strong>

                                    </div>


                                    @if($showPrice)

                                        <div
                                            class="flex items-center justify-between gap-3 text-xs"
                                        >

                                            <span
                                                class="inline-flex items-center gap-1.5 app-muted"
                                            >
                                                <i
                                                    class="ph ph-ticket"
                                                    aria-hidden="true"
                                                ></i>

                                                Giá thường
                                            </span>

                                            <strong class="text-brand-start">
                                                {{ $summary['price'] }}
                                            </strong>

                                        </div>


                                        @if($summary['vip_price'])

                                            <div
                                                class="flex items-center justify-between gap-3 text-xs"
                                            >

                                                <span
                                                    class="inline-flex items-center gap-1.5 app-muted"
                                                >
                                                    <i
                                                        class="ph ph-star"
                                                        aria-hidden="true"
                                                    ></i>

                                                    Giá VIP
                                                </span>

                                                <strong class="app-text">
                                                    {{ $summary['vip_price'] }}
                                                </strong>

                                            </div>

                                        @endif

                                    @endif

                                </div>


                                {{-- =================================================
                                    COUNTDOWN
                                ================================================== --}}
                                @if($summary['countdown'])

                                    <div
                                        class="mt-4 flex items-center gap-2 rounded-xl border border-warning/20 bg-warning/5 p-2.5 text-xs font-bold text-warning"
                                    >
                                        <i
                                            class="ph ph-clock-countdown"
                                            aria-hidden="true"
                                        ></i>

                                        {{ $summary['countdown'] }}
                                    </div>

                                @endif


                                {{-- =================================================
                                    SEAT HINT
                                ================================================== --}}
                                @if(
                                    $showSeatHint
                                    && $canSelect
                                )

                                    <div
                                        class="mt-4 flex items-start gap-2 rounded-xl border border-success/20 bg-success/5 p-2.5"
                                    >

                                        <i
                                            class="ph ph-armchair mt-0.5 text-success"
                                            aria-hidden="true"
                                        ></i>

                                        <p
                                            class="text-[11px] leading-5 app-muted"
                                        >
                                            Suất chiếu đang mở bán.
                                            Bạn có thể tiếp tục để xem sơ đồ ghế.
                                        </p>

                                    </div>

                                @endif


                                {{-- =================================================
                                    DISABLED REASON
                                ================================================== --}}
                                @if(
                                    ! $canSelect
                                    && $reason
                                )

                                    <div
                                        class="mt-4 flex items-start gap-2 rounded-xl border border-warning/20 bg-warning/5 p-2.5"
                                    >

                                        <i
                                            class="ph ph-warning-circle mt-0.5 text-warning"
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


                            {{-- =================================================
                                ACTION
                            ================================================== --}}
                            <div
                                class="border-t app-border p-3"
                            >

                                @if($canSelect)

                                    <a
                                        href="{{ route('user.bookings.create', [
                                            'showtime' => $showtime->id
                                        ]) }}"
                                        @class([
                                            'inline-flex w-full items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-xs font-black transition',
                                            'bg-brand-start text-white' => $isSelected,
                                            'border app-border app-text hover:border-brand-start hover:text-brand-start' => ! $isSelected,
                                        ])
                                    >

                                        <i
                                            class="ph ph-ticket"
                                            aria-hidden="true"
                                        ></i>

                                        {{ $isSelected ? 'Tiếp tục đặt vé' : 'Chọn suất này' }}

                                        <i
                                            class="ph ph-arrow-right"
                                            aria-hidden="true"
                                        ></i>

                                    </a>

                                @else

                                    <button
                                        type="button"
                                        disabled
                                        class="inline-flex w-full cursor-not-allowed items-center justify-center gap-2 rounded-xl border app-border px-3 py-2.5 text-xs font-bold app-muted opacity-50"
                                    >

                                        <i
                                            class="ph ph-lock"
                                            aria-hidden="true"
                                        ></i>

                                        Không khả dụng

                                    </button>

                                @endif

                            </div>

                        </div>

                    @endforeach

                </div>

            </article>


        @empty

            @if($showEmptyState)

                <div
                    class="rounded-3xl border border-dashed app-border p-8 text-center"
                >

                    <div
                        class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-start/10 text-brand-start"
                    >
                        <i
                            class="ph ph-calendar-x text-3xl"
                            aria-hidden="true"
                        ></i>
                    </div>


                    <h4
                        class="mt-4 font-black app-heading"
                    >
                        Rạp chưa có suất chiếu
                    </h4>


                    <p
                        class="mx-auto mt-2 max-w-md text-sm leading-6 app-muted"
                    >
                        Hiện chưa có suất chiếu phù hợp tại rạp này.
                        Bạn có thể chọn ngày hoặc rạp khác.
                    </p>

                </div>

            @endif

        @endforelse

    </div>


    {{-- =====================================================
        CINEMA FOOTER
    ====================================================== --}}
    <footer
        class="border-t app-border bg-slate-500/5 px-5 py-4 sm:px-6"
    >

        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >

            <div
                class="flex items-center gap-2 text-xs app-muted"
            >
                <i
                    class="ph ph-info text-brand-start"
                    aria-hidden="true"
                ></i>

                Giá vé có thể thay đổi theo loại ghế và khung giờ.
            </div>


            @if($cinema?->id)

                <a
                    href="{{ route('user.cinemas.show', $cinema) }}"
                    class="inline-flex items-center gap-1.5 text-xs font-black text-brand-start hover:underline"
                >
                    Xem thông tin rạp

                    <i
                        class="ph ph-arrow-right"
                        aria-hidden="true"
                    ></i>
                </a>

            @endif

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
        Rạp {{ $cinemaName }} có
        {{ $totalShowtimes }} suất chiếu,
        trong đó {{ $availableShowtimes }}
        suất có thể đặt vé.
    </div>

</section>