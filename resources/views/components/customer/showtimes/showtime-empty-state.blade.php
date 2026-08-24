@props([
    'type' => 'no_showtimes',
    'movie' => null,
    'cinema' => null,
    'selectedDate' => null,
    'resetUrl' => null,
    'moviesUrl' => null,
    'cinemasUrl' => null,
    'showSuggestions' => true,
    'showQuickDates' => true,
    'showHelp' => true,
])

@php
    $states = [

        'no_showtimes' => [
            'eyebrow' => 'Chưa có lịch chiếu',
            'title' => 'Hiện chưa có suất chiếu phù hợp',
            'description' => 'Phim bạn đang xem chưa có suất chiếu đáp ứng điều kiện lựa chọn hiện tại.',
            'icon' => 'ph-calendar-x',
            'iconClass' => 'bg-slate-500/10 text-slate-400',
            'borderClass' => 'border-slate-500/20',
            'backgroundClass' => 'bg-slate-500/5',
            'accentClass' => 'from-slate-500/20 via-slate-500/5 to-transparent',
        ],

        'no_showtimes_today' => [
            'eyebrow' => 'Không có suất hôm nay',
            'title' => 'Hôm nay chưa có lịch chiếu',
            'description' => 'Bạn có thể chuyển sang ngày kế tiếp để xem các suất chiếu đang mở bán.',
            'icon' => 'ph-calendar-blank',
            'iconClass' => 'bg-warning/10 text-warning',
            'borderClass' => 'border-warning/20',
            'backgroundClass' => 'bg-warning/5',
            'accentClass' => 'from-warning/20 via-warning/5 to-transparent',
        ],

        'sold_out' => [
            'eyebrow' => 'Hết chỗ',
            'title' => 'Các suất chiếu hiện đã hết ghế',
            'description' => 'Các suất phù hợp với lựa chọn hiện tại không còn ghế trống để đặt.',
            'icon' => 'ph-armchair',
            'iconClass' => 'bg-error/10 text-error',
            'borderClass' => 'border-error/20',
            'backgroundClass' => 'bg-error/5',
            'accentClass' => 'from-error/20 via-error/5 to-transparent',
        ],

        'finished' => [
            'eyebrow' => 'Đã kết thúc',
            'title' => 'Các suất chiếu trong ngày đã kết thúc',
            'description' => 'Các suất hôm nay không còn nhận đặt vé. Hãy chọn ngày khác để tiếp tục.',
            'icon' => 'ph-clock-counter-clockwise',
            'iconClass' => 'bg-slate-500/10 text-slate-400',
            'borderClass' => 'border-slate-500/20',
            'backgroundClass' => 'bg-slate-500/5',
            'accentClass' => 'from-slate-500/20 via-slate-500/5 to-transparent',
        ],

        'filter_empty' => [
            'eyebrow' => 'Không có kết quả',
            'title' => 'Không tìm thấy suất chiếu theo bộ lọc',
            'description' => 'Không có suất chiếu nào khớp với các điều kiện lọc bạn đang sử dụng.',
            'icon' => 'ph-funnel-x',
            'iconClass' => 'bg-warning/10 text-warning',
            'borderClass' => 'border-warning/20',
            'backgroundClass' => 'bg-warning/5',
            'accentClass' => 'from-warning/20 via-warning/5 to-transparent',
        ],

        'cinema_empty' => [
            'eyebrow' => 'Rạp chưa có lịch',
            'title' => 'Rạp này chưa có suất chiếu phù hợp',
            'description' => 'Bạn có thể đổi sang một rạp khác hoặc chọn ngày khác để tìm lịch phù hợp.',
            'icon' => 'ph-buildings',
            'iconClass' => 'bg-brand-start/10 text-brand-start',
            'borderClass' => 'border-brand-start/20',
            'backgroundClass' => 'bg-brand-start/5',
            'accentClass' => 'from-brand-start/20 via-brand-start/5 to-transparent',
        ],

        'loading_failed' => [
            'eyebrow' => 'Không tải được dữ liệu',
            'title' => 'Có lỗi khi tải danh sách suất chiếu',
            'description' => 'Lịch chiếu chưa thể tải thành công. Bạn có thể thử tải lại trang.',
            'icon' => 'ph-warning-circle',
            'iconClass' => 'bg-error/10 text-error',
            'borderClass' => 'border-error/20',
            'backgroundClass' => 'bg-error/5',
            'accentClass' => 'from-error/20 via-error/5 to-transparent',
        ],

    ];

    $state = $states[$type]
        ?? $states['no_showtimes'];

    $movieTitle = $movie?->title;

    $cinemaName = $cinema?->name;

    $cinemaAddress = $cinema?->address;

    $poster = $movie?->poster_url;

    $dateLabel = null;

    $parsedDate = null;

    if ($selectedDate) {
        try {
            $parsedDate = \Carbon\Carbon::parse(
                $selectedDate
            );

            $dateLabel = $parsedDate->format(
                'd/m/Y'
            );
        } catch (\Throwable) {
            $dateLabel = (string) $selectedDate;
        }
    }

    $resetUrl = $resetUrl
        ?: request()->url();

    $moviesUrl = $moviesUrl
        ?: route('user.movies.index');

    $cinemasUrl = $cinemasUrl
        ?: route('user.cinemas.index');

    $quickDates = collect(
        range(1, 5)
    )->map(
        fn (int $offset) =>
            now()
                ->addDays($offset)
                ->startOfDay()
    );

    $weekdayLabels = [
        0 => 'CN',
        1 => 'T2',
        2 => 'T3',
        3 => 'T4',
        4 => 'T5',
        5 => 'T6',
        6 => 'T7',
    ];
@endphp


<section
    {{ $attributes->class([
        'relative overflow-hidden rounded-[2rem] border',
        $state['borderClass'],
        $state['backgroundClass'],
    ]) }}
    data-showtime-empty-state="{{ $type }}"
>

    {{-- =====================================================
        BACKGROUND DECORATION
    ====================================================== --}}
    <div
        class="pointer-events-none absolute inset-x-0 top-0 h-40 bg-gradient-to-b {{ $state['accentClass'] }}"
        aria-hidden="true"
    ></div>


    <div
        class="pointer-events-none absolute -right-20 -top-20 h-56 w-56 rounded-full bg-brand-start/10 blur-3xl"
        aria-hidden="true"
    ></div>


    <div
        class="pointer-events-none absolute -bottom-24 -left-20 h-64 w-64 rounded-full bg-ai-start/10 blur-3xl"
        aria-hidden="true"
    ></div>


    <div
        class="pointer-events-none absolute right-[18%] top-[35%] h-32 w-32 rounded-full bg-warning/5 blur-3xl"
        aria-hidden="true"
    ></div>


    {{-- =====================================================
        MAIN CONTENT
    ====================================================== --}}
    <div
        class="relative p-6 sm:p-8 lg:p-10"
    >

        <div
            class="mx-auto max-w-5xl"
        >

            {{-- =================================================
                HERO
            ================================================== --}}
            <div
                class="grid gap-6 lg:grid-cols-[1fr_auto] lg:items-center"
            >

                <div
                    class="flex flex-col items-center text-center lg:items-start lg:text-left"
                >

                    <div
                        class="flex h-20 w-20 items-center justify-center rounded-[1.75rem] border app-border bg-white/5 shadow-xl backdrop-blur {{ $state['iconClass'] }}"
                    >

                        <i
                            class="ph {{ $state['icon'] }} text-4xl"
                            aria-hidden="true"
                        ></i>

                    </div>


                    <p
                        class="mt-5 text-xs font-black uppercase tracking-[0.24em] text-brand-start"
                    >
                        {{ $state['eyebrow'] }}
                    </p>


                    <h3
                        class="mt-2 max-w-3xl text-2xl font-black app-heading sm:text-3xl lg:text-4xl"
                    >
                        {{ $state['title'] }}
                    </h3>


                    <p
                        class="mt-3 max-w-2xl text-sm leading-7 app-muted sm:text-base"
                    >
                        {{ $state['description'] }}
                    </p>

                </div>


                {{-- MOVIE MINI POSTER --}}
                @if($movieTitle)

                    <div
                        class="hidden lg:block"
                    >

                        <div
                            class="w-36 overflow-hidden rounded-3xl border app-border bg-slate-950 shadow-2xl"
                        >

                            @if($poster)

                                <img
                                    src="{{ $poster }}"
                                    alt="Áp phích {{ $movieTitle }}"
                                    class="aspect-[2/3] w-full object-cover"
                                    loading="lazy"
                                >

                            @else

                                <div
                                    class="flex aspect-[2/3] w-full items-center justify-center bg-gradient-to-br from-brand-start/20 to-ai-start/20"
                                >

                                    <div
                                        class="text-center"
                                    >

                                        <i
                                            class="ph ph-film-slate text-4xl text-brand-start"
                                        ></i>

                                        <p
                                            class="mt-2 text-xs font-black app-muted"
                                        >
                                            MovieMate
                                        </p>

                                    </div>

                                </div>

                            @endif

                        </div>

                    </div>

                @endif

            </div>


            {{-- =================================================
                CURRENT SELECTION CONTEXT
            ================================================== --}}
            @if(
                $movieTitle
                || $cinemaName
                || $dateLabel
            )

                <div
                    class="mt-8"
                >

                    <div
                        class="mb-3 flex items-center gap-2"
                    >

                        <i
                            class="ph ph-info text-brand-start"
                            aria-hidden="true"
                        ></i>

                        <p
                            class="text-xs font-black uppercase tracking-wider app-muted"
                        >
                            Lựa chọn hiện tại
                        </p>

                    </div>


                    <div
                        class="grid grid-cols-1 gap-3 md:grid-cols-3"
                    >

                        {{-- MOVIE --}}
                        <article
                            class="group rounded-2xl border app-border app-card p-4 transition hover:border-brand-start/40"
                        >

                            <div
                                class="flex items-start gap-3"
                            >

                                <span
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-start/10 text-brand-start transition group-hover:scale-105"
                                >

                                    <i
                                        class="ph ph-film-slate text-lg"
                                        aria-hidden="true"
                                    ></i>

                                </span>


                                <div
                                    class="min-w-0"
                                >

                                    <p
                                        class="text-[10px] font-black uppercase tracking-wider app-muted"
                                    >
                                        Phim
                                    </p>

                                    <p
                                        class="mt-1 truncate font-extrabold app-text"
                                    >
                                        {{ $movieTitle ?: 'Chưa chọn phim' }}
                                    </p>

                                </div>

                            </div>

                        </article>


                        {{-- CINEMA --}}
                        <article
                            class="group rounded-2xl border app-border app-card p-4 transition hover:border-ai-start/40"
                        >

                            <div
                                class="flex items-start gap-3"
                            >

                                <span
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-ai-start/10 text-ai-start transition group-hover:scale-105"
                                >

                                    <i
                                        class="ph ph-buildings text-lg"
                                        aria-hidden="true"
                                    ></i>

                                </span>


                                <div
                                    class="min-w-0"
                                >

                                    <p
                                        class="text-[10px] font-black uppercase tracking-wider app-muted"
                                    >
                                        Rạp
                                    </p>

                                    <p
                                        class="mt-1 truncate font-extrabold app-text"
                                    >
                                        {{ $cinemaName ?: 'Tất cả rạp' }}
                                    </p>

                                    @if($cinemaAddress)

                                        <p
                                            class="mt-1 truncate text-xs app-muted"
                                        >
                                            {{ $cinemaAddress }}
                                        </p>

                                    @endif

                                </div>

                            </div>

                        </article>


                        {{-- DATE --}}
                        <article
                            class="group rounded-2xl border app-border app-card p-4 transition hover:border-warning/40"
                        >

                            <div
                                class="flex items-start gap-3"
                            >

                                <span
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-warning/10 text-warning transition group-hover:scale-105"
                                >

                                    <i
                                        class="ph ph-calendar text-lg"
                                        aria-hidden="true"
                                    ></i>

                                </span>


                                <div
                                    class="min-w-0"
                                >

                                    <p
                                        class="text-[10px] font-black uppercase tracking-wider app-muted"
                                    >
                                        Ngày chiếu
                                    </p>

                                    <p
                                        class="mt-1 font-extrabold app-text"
                                    >
                                        {{ $dateLabel ?: 'Chưa chọn ngày' }}
                                    </p>

                                    @if($parsedDate)

                                        <p
                                            class="mt-1 text-xs app-muted"
                                        >
                                            {{ $weekdayLabels[$parsedDate->dayOfWeek] ?? '' }}
                                        </p>

                                    @endif

                                </div>

                            </div>

                        </article>

                    </div>

                </div>

            @endif


            {{-- =================================================
                QUICK DATE SUGGESTIONS
            ================================================== --}}
            @if(
                $showQuickDates
                && in_array(
                    $type,
                    [
                        'no_showtimes',
                        'no_showtimes_today',
                        'finished',
                        'sold_out',
                    ],
                    true
                )
            )

                <div
                    class="mt-8 rounded-3xl border app-border bg-white/5 p-5"
                >

                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >

                        <div>

                            <div
                                class="flex items-center gap-2"
                            >

                                <span
                                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-start/10 text-brand-start"
                                >

                                    <i
                                        class="ph ph-calendar-plus"
                                        aria-hidden="true"
                                    ></i>

                                </span>


                                <div>

                                    <p
                                        class="font-extrabold app-text"
                                    >
                                        Thử ngày khác
                                    </p>

                                    <p
                                        class="mt-0.5 text-xs app-muted"
                                    >
                                        Lịch chiếu có thể có thêm suất ở các ngày tiếp theo.
                                    </p>

                                </div>

                            </div>

                        </div>

                    </div>


                    <div
                        class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-5"
                    >

                        @foreach($quickDates as $quickDate)

                            <a
                                href="{{ request()->fullUrlWithQuery([
                                    'date' => $quickDate->format('Y-m-d')
                                ]) }}"
                                class="group rounded-2xl border app-border px-3 py-3 text-center transition hover:-translate-y-0.5 hover:border-brand-start hover:bg-brand-start/5"
                            >

                                <p
                                    class="text-[10px] font-black uppercase tracking-wide app-muted"
                                >
                                    {{ $weekdayLabels[$quickDate->dayOfWeek] ?? '' }}
                                </p>

                                <p
                                    class="mt-1 text-lg font-black app-heading group-hover:text-brand-start"
                                >
                                    {{ $quickDate->format('d') }}
                                </p>

                                <p
                                    class="text-xs app-muted"
                                >
                                    Tháng {{ $quickDate->format('m') }}
                                </p>

                            </a>

                        @endforeach

                    </div>

                </div>

            @endif


            {{-- =================================================
                PRIMARY ACTIONS
            ================================================== --}}
            <div
                class="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap"
            >

                @if(
                    $type === 'filter_empty'
                    || $type === 'cinema_empty'
                )

                    <a
                        href="{{ $resetUrl }}"
                        class="btn-primary justify-center"
                    >

                        <i
                            class="ph ph-arrow-counter-clockwise"
                            aria-hidden="true"
                        ></i>

                        Xóa bộ lọc

                    </a>

                @endif


                @if(
                    in_array(
                        $type,
                        [
                            'no_showtimes',
                            'sold_out',
                            'finished',
                        ],
                        true
                    )
                )

                    <a
                        href="{{ $moviesUrl }}"
                        class="btn-primary justify-center"
                    >

                        <i
                            class="ph ph-film-slate"
                            aria-hidden="true"
                        ></i>

                        Xem phim khác

                    </a>

                @endif


                @if(
                    in_array(
                        $type,
                        [
                            'cinema_empty',
                            'sold_out',
                            'no_showtimes_today',
                        ],
                        true
                    )
                )

                    <a
                        href="{{ $cinemasUrl }}"
                        class="btn-secondary justify-center"
                    >

                        <i
                            class="ph ph-buildings"
                            aria-hidden="true"
                        ></i>

                        Chọn rạp khác

                    </a>

                @endif


                @if($type === 'loading_failed')

                    <button
                        type="button"
                        class="btn-primary justify-center"
                        onclick="window.location.reload()"
                    >

                        <i
                            class="ph ph-arrow-clockwise"
                            aria-hidden="true"
                        ></i>

                        Tải lại lịch chiếu

                    </button>

                @endif

            </div>


            {{-- =================================================
                SUGGESTIONS
            ================================================== --}}
            @if($showSuggestions)

                <div
                    class="mt-9 border-t app-border pt-7"
                >

                    <div
                        class="flex items-center gap-2"
                    >

                        <i
                            class="ph ph-lightbulb text-warning"
                            aria-hidden="true"
                        ></i>

                        <p
                            class="text-xs font-black uppercase tracking-wider app-muted"
                        >
                            Gợi ý cho bạn
                        </p>

                    </div>


                    <div
                        class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3"
                    >

                        {{-- DATE --}}
                        <article
                            class="group rounded-3xl border app-border app-card p-5 transition duration-200 hover:-translate-y-1 hover:border-brand-start/40 hover:shadow-lg"
                        >

                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-2xl bg-brand-start/10 text-brand-start"
                            >

                                <i
                                    class="ph ph-calendar-plus text-xl"
                                ></i>

                            </div>


                            <h4
                                class="mt-4 font-black app-heading"
                            >
                                Chọn ngày chiếu khác
                            </h4>


                            <p
                                class="mt-2 text-sm leading-6 app-muted"
                            >
                                Lịch chiếu thay đổi theo từng ngày và có thể được bổ sung thêm suất.
                            </p>


                            <div
                                class="mt-4 flex items-center gap-1 text-xs font-bold text-brand-start"
                            >
                                Xem ngày khác
                                <i class="ph ph-arrow-right"></i>
                            </div>

                        </article>


                        {{-- CINEMA --}}
                        <article
                            class="group rounded-3xl border app-border app-card p-5 transition duration-200 hover:-translate-y-1 hover:border-ai-start/40 hover:shadow-lg"
                        >

                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-2xl bg-ai-start/10 text-ai-start"
                            >

                                <i
                                    class="ph ph-map-pin text-xl"
                                ></i>

                            </div>


                            <h4
                                class="mt-4 font-black app-heading"
                            >
                                Thử một rạp khác
                            </h4>


                            <p
                                class="mt-2 text-sm leading-6 app-muted"
                            >
                                Một rạp khác có thể còn nhiều khung giờ và vị trí ghế phù hợp hơn.
                            </p>


                            <a
                                href="{{ $cinemasUrl }}"
                                class="mt-4 flex items-center gap-1 text-xs font-bold text-ai-start"
                            >
                                Xem danh sách rạp
                                <i class="ph ph-arrow-right"></i>
                            </a>

                        </article>


                        {{-- MOVIE --}}
                        <article
                            class="group rounded-3xl border app-border app-card p-5 transition duration-200 hover:-translate-y-1 hover:border-success/40 hover:shadow-lg"
                        >

                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-2xl bg-success/10 text-success"
                            >

                                <i
                                    class="ph ph-film-strip text-xl"
                                ></i>

                            </div>


                            <h4
                                class="mt-4 font-black app-heading"
                            >
                                Khám phá phim khác
                            </h4>


                            <p
                                class="mt-2 text-sm leading-6 app-muted"
                            >
                                Xem thêm những bộ phim đang có lịch chiếu và mở bán vé.
                            </p>


                            <a
                                href="{{ $moviesUrl }}"
                                class="mt-4 flex items-center gap-1 text-xs font-bold text-success"
                            >
                                Xem phim đang chiếu
                                <i class="ph ph-arrow-right"></i>
                            </a>

                        </article>

                    </div>

                </div>

            @endif


            {{-- =================================================
                HOW IT WORKS
            ================================================== --}}
            @if($showHelp)

                <div
                    class="mt-8 rounded-3xl border app-border bg-white/5 p-5 sm:p-6"
                >

                    <div
                        class="flex items-center gap-3"
                    >

                        <span
                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-start/10 text-brand-start"
                        >

                            <i
                                class="ph ph-question"
                                aria-hidden="true"
                            ></i>

                        </span>


                        <div>

                            <p
                                class="font-black app-text"
                            >
                                Làm sao để tìm được suất phù hợp?
                            </p>

                            <p
                                class="mt-1 text-xs app-muted"
                            >
                                Bạn có thể thử theo thứ tự dưới đây.
                            </p>

                        </div>

                    </div>


                    <div
                        class="mt-5 grid gap-3 md:grid-cols-3"
                    >

                        <div
                            class="rounded-2xl border app-border p-4"
                        >

                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-start text-xs font-black text-white"
                            >
                                1
                            </span>

                            <p
                                class="mt-3 text-sm font-black app-text"
                            >
                                Đổi ngày
                            </p>

                            <p
                                class="mt-1 text-xs leading-5 app-muted"
                            >
                                Chọn ngày tiếp theo để kiểm tra lịch chiếu mới.
                            </p>

                        </div>


                        <div
                            class="rounded-2xl border app-border p-4"
                        >

                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-ai-start text-xs font-black text-white"
                            >
                                2
                            </span>

                            <p
                                class="mt-3 text-sm font-black app-text"
                            >
                                Đổi rạp
                            </p>

                            <p
                                class="mt-1 text-xs leading-5 app-muted"
                            >
                                Kiểm tra rạp khác nếu rạp hiện tại không còn suất.
                            </p>

                        </div>


                        <div
                            class="rounded-2xl border app-border p-4"
                        >

                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-success text-xs font-black text-white"
                            >
                                3
                            </span>

                            <p
                                class="mt-3 text-sm font-black app-text"
                            >
                                Chọn phim khác
                            </p>

                            <p
                                class="mt-1 text-xs leading-5 app-muted"
                            >
                                Khám phá phim khác đang có nhiều suất mở bán.
                            </p>

                        </div>

                    </div>

                </div>

            @endif


            {{-- =================================================
                NOTICE
            ================================================== --}}
            <div
                class="mt-6 flex items-start gap-3 rounded-2xl border border-brand-start/10 bg-brand-start/5 p-4"
            >

                <div
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-start/10 text-brand-start"
                >

                    <i
                        class="ph ph-info"
                        aria-hidden="true"
                    ></i>

                </div>


                <div>

                    <p
                        class="text-sm font-extrabold app-text"
                    >
                        Lịch chiếu được cập nhật thường xuyên
                    </p>

                    <p
                        class="mt-1 text-xs leading-5 app-muted"
                    >
                        Rạp có thể bổ sung thêm suất chiếu trong ngày.
                        Hãy quay lại kiểm tra nếu chưa tìm được khung giờ phù hợp.
                    </p>

                </div>

            </div>

        </div>

    </div>


    {{-- =====================================================
        ACCESSIBILITY STATUS
    ====================================================== --}}
    <div
        class="sr-only"
        role="status"
        aria-live="polite"
    >
        {{ $state['title'] }}.
        {{ $state['description'] }}

        @if($movieTitle)
            Phim {{ $movieTitle }}.
        @endif

        @if($cinemaName)
            Rạp {{ $cinemaName }}.
        @endif

        @if($dateLabel)
            Ngày {{ $dateLabel }}.
        @endif
    </div>

</section>