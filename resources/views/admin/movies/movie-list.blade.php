@php
    $movieListStatusMeta = [
        'draft' => [
            'label' => 'Bản nháp',
            'description' => 'Phim đang được chuẩn bị nội dung.',
            'icon' => 'ph-note-pencil',
            'class' => 'bg-blue-500/10 text-blue-500 border-blue-500/20',
        ],

        'coming_soon' => [
            'label' => 'Sắp chiếu',
            'description' => 'Phim đã được công bố nhưng chưa bắt đầu chiếu.',
            'icon' => 'ph-calendar',
            'class' => 'bg-warning/10 text-warning border-warning/20',
        ],

        'now_showing' => [
            'label' => 'Đang chiếu',
            'description' => 'Phim đang hoạt động trong hệ thống.',
            'icon' => 'ph-play-circle',
            'class' => 'bg-success/10 text-success border-success/20',
        ],

        'inactive' => [
            'label' => 'Ngừng hoạt động',
            'description' => 'Phim đang tạm ngừng trên toàn hệ thống.',
            'icon' => 'ph-pause-circle',
            'class' => 'bg-slate-500/10 text-slate-500 border-slate-500/20',
        ],

        'archived' => [
            'label' => 'Đã lưu trữ',
            'description' => 'Phim đã được chuyển vào kho lưu trữ.',
            'icon' => 'ph-archive',
            'class' => 'bg-slate-700/20 text-slate-400 border-slate-600/20',
        ],
    ];

    $activeMovieFilters = collect([
        'search' => filled($search ?? null)
            ? 'Từ khóa: '.$search
            : null,

        'status' => filled($status ?? null)
            ? 'Trạng thái: '.(
                $movieListStatusMeta[$status]['label']
                ?? $status
            )
            : null,

        'genre' => filled($genreId ?? null)
            ? 'Thể loại: '.(
                optional(
                    collect($genres ?? [])->firstWhere(
                        'id',
                        (int) $genreId
                    )
                )->name
                ?? '#'.$genreId
            )
            : null,

        'country' => filled($country ?? null)
            ? 'Quốc gia: '.$country
            : null,
    ])->filter();

    $movieCount = $movies->total();

    $visibleMovieCount = $movies->count();

    $currentPage = $movies->currentPage();

    $lastPage = $movies->lastPage();

    $firstItem = $movies->firstItem();

    $lastItem = $movies->lastItem();
@endphp


<section
    class="space-y-5"
    aria-labelledby="movie-list-heading"
>

    {{-- =========================================================
        TỔNG QUAN DANH SÁCH
    ========================================================== --}}
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">

        <article class="cinema-card p-4">

            <div class="flex items-center justify-between gap-3">

                <div>
                    <p class="text-xs font-bold uppercase tracking-wider app-muted">
                        Tổng phim
                    </p>

                    <p class="mt-2 text-2xl font-black app-text">
                        {{ number_format($movieCount) }}
                    </p>
                </div>

                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-start/10 text-brand-start">
                    <i class="ph ph-film-slate text-xl"></i>
                </span>

            </div>

        </article>


        <article class="cinema-card p-4">

            <div class="flex items-center justify-between gap-3">

                <div>
                    <p class="text-xs font-bold uppercase tracking-wider app-muted">
                        Đang hiển thị
                    </p>

                    <p class="mt-2 text-2xl font-black app-text">
                        {{ number_format($visibleMovieCount) }}
                    </p>
                </div>

                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-success/10 text-success">
                    <i class="ph ph-eye text-xl"></i>
                </span>

            </div>

        </article>


        <article class="cinema-card p-4">

            <div class="flex items-center justify-between gap-3">

                <div>
                    <p class="text-xs font-bold uppercase tracking-wider app-muted">
                        Trang hiện tại
                    </p>

                    <p class="mt-2 text-2xl font-black app-text">
                        {{ $currentPage }}/{{ max(1, $lastPage) }}
                    </p>
                </div>

                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-warning/10 text-warning">
                    <i class="ph ph-files text-xl"></i>
                </span>

            </div>

        </article>


        <article class="cinema-card p-4">

            <div class="flex items-center justify-between gap-3">

                <div>
                    <p class="text-xs font-bold uppercase tracking-wider app-muted">
                        Khoảng dữ liệu
                    </p>

                    <p class="mt-2 font-black app-text">
                        @if($firstItem && $lastItem)
                            {{ $firstItem }} - {{ $lastItem }}
                        @else
                            Không có dữ liệu
                        @endif
                    </p>
                </div>

                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-ai-start/10 text-ai-start">
                    <i class="ph ph-list-numbers text-xl"></i>
                </span>

            </div>

        </article>

    </div>


    {{-- =========================================================
        BỘ LỌC ĐANG ÁP DỤNG
    ========================================================== --}}
    @if($activeMovieFilters->isNotEmpty())

        <div class="rounded-2xl border app-border app-card p-4">

            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">

                <div>

                    <div class="flex items-center gap-2">

                        <i class="ph ph-funnel text-brand-start"></i>

                        <p class="font-extrabold app-text">
                            Bộ lọc đang áp dụng
                        </p>

                    </div>

                    <p class="mt-1 text-xs app-muted">
                        Danh sách phim đang được giới hạn theo các điều kiện bên dưới.
                    </p>

                </div>


                <a
                    href="{{ route('admin.movies.index') }}"
                    class="btn-secondary"
                >
                    <i class="ph ph-arrow-counter-clockwise"></i>
                    Xóa toàn bộ lọc
                </a>

            </div>


            <div class="mt-4 flex flex-wrap gap-2">

                @foreach($activeMovieFilters as $filterLabel)

                    <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-start/20 bg-brand-start/5 px-3 py-1.5 text-xs font-bold text-brand-start">

                        <i class="ph ph-check-circle"></i>

                        {{ $filterLabel }}

                    </span>

                @endforeach

            </div>

        </div>

    @endif


    {{-- =========================================================
        HEADER DANH SÁCH
    ========================================================== --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

        <div>

            <h2
                id="movie-list-heading"
                class="text-xl font-extrabold app-heading"
            >
                Danh sách phim
            </h2>

            <p class="mt-1 text-sm app-muted">
                Theo dõi thông tin phim, trạng thái vòng đời,
                ngày khởi chiếu, thể loại và lịch chiếu sắp tới.
            </p>

        </div>


        <div class="flex flex-wrap gap-2">

            @can('genres.view')

                <a
                    href="{{ route('admin.genres.index') }}"
                    class="btn-secondary"
                >
                    <i class="ph ph-tag"></i>
                    Thể loại
                </a>

            @endcan


            @can('movies.create')

                <a
                    href="{{ route('admin.movies.create') }}"
                    class="btn-primary"
                >
                    <i class="ph-bold ph-plus"></i>
                    Thêm phim
                </a>

            @endcan

        </div>

    </div>


    {{-- =========================================================
        MOBILE MOVIE CARDS
    ========================================================== --}}
    <div class="space-y-4 md:hidden">

        @forelse($movies as $movie)

            @php
                $movieStatus = $movieListStatusMeta[$movie->status]
                    ?? [
                        'label' => $movie->status_label ?? 'Chưa rõ',
                        'description' => 'Trạng thái phim chưa được xác định.',
                        'icon' => 'ph-question',
                        'class' => 'bg-slate-500/10 text-slate-500 border-slate-500/20',
                    ];

                $releaseDate = $movie->release_date;

                $releaseState = match(true) {
                    ! $releaseDate => [
                        'label' => 'Chưa có ngày khởi chiếu',
                        'class' => 'text-slate-500',
                        'icon' => 'ph-calendar-x',
                    ],

                    $releaseDate->isToday() => [
                        'label' => 'Khởi chiếu hôm nay',
                        'class' => 'text-success',
                        'icon' => 'ph-calendar-check',
                    ],

                    $releaseDate->isFuture() => [
                        'label' => 'Sắp khởi chiếu',
                        'class' => 'text-warning',
                        'icon' => 'ph-calendar-plus',
                    ],

                    default => [
                        'label' => 'Đã khởi chiếu',
                        'class' => 'text-brand-start',
                        'icon' => 'ph-calendar',
                    ],
                };
            @endphp


            <article class="cinema-card overflow-hidden">

                <div class="p-4">

                    <div class="flex gap-4">

                        <div class="h-32 w-24 shrink-0 overflow-hidden rounded-2xl border app-border bg-slate-950">

                            @if($movie->poster_url)

                                <img
                                    src="{{ $movie->poster_url }}"
                                    alt="Áp phích {{ $movie->title }}"
                                    class="h-full w-full object-cover"
                                    loading="lazy"
                                >

                            @else

                                <div class="admin-media-fallback h-full w-full">
                                    MM
                                </div>

                            @endif

                        </div>


                        <div class="min-w-0 flex-1">

                            <div class="flex flex-wrap items-start justify-between gap-2">

                                <div class="min-w-0">

                                    <a
                                        href="{{ route('admin.movies.show', $movie) }}"
                                        class="block truncate text-lg font-extrabold app-heading hover:text-brand-start"
                                    >
                                        {{ $movie->title }}
                                    </a>

                                    <p class="mt-1 font-mono text-xs app-muted">
                                        #{{ $movie->id }}
                                    </p>

                                </div>

                            </div>


                            <span class="mt-3 inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-bold {{ $movieStatus['class'] }}">

                                <i class="ph {{ $movieStatus['icon'] }}"></i>

                                {{ $movieStatus['label'] }}

                            </span>


                            <div class="mt-3 space-y-1 text-xs app-muted">

                                <p class="flex items-center gap-2">
                                    <i class="ph ph-globe-hemisphere-east"></i>
                                    {{ $movie->country ?: 'Chưa cập nhật quốc gia' }}
                                </p>

                                <p class="flex items-center gap-2">
                                    <i class="ph ph-timer"></i>
                                    {{ $movie->duration ? $movie->duration.' phút' : 'Chưa có thời lượng' }}
                                </p>

                                <p class="flex items-center gap-2 {{ $releaseState['class'] }}">
                                    <i class="ph {{ $releaseState['icon'] }}"></i>

                                    @if($releaseDate)
                                        {{ $releaseDate->format('d/m/Y') }}
                                    @else
                                        {{ $releaseState['label'] }}
                                    @endif
                                </p>

                            </div>

                        </div>

                    </div>


                    <div class="mt-4 rounded-xl border app-border p-3">

                        <p class="text-xs font-bold uppercase tracking-wide app-muted">
                            Thể loại
                        </p>

                        <p class="mt-1 text-sm app-text">
                            {{ $movie->genres->pluck('name')->join(', ') ?: 'Chưa phân loại' }}
                        </p>

                    </div>


                    <div class="mt-4 grid grid-cols-2 gap-2">

                        <a
                            href="{{ route('admin.movies.show', $movie) }}"
                            class="btn-secondary justify-center"
                        >
                            <i class="ph ph-eye"></i>
                            Xem
                        </a>


                        @can('movies.update')

                            @if($movie->status !== 'archived')

                                <a
                                    href="{{ route('admin.movies.edit', $movie) }}"
                                    class="btn-secondary justify-center"
                                >
                                    <i class="ph ph-pencil-simple"></i>
                                    Chỉnh sửa
                                </a>

                            @endif

                        @endcan

                    </div>

                </div>

            </article>


        @empty

            <div class="cinema-card p-10 text-center">

                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-start/10 text-brand-start">
                    <i class="ph ph-film-slate text-3xl"></i>
                </div>

                <h3 class="mt-4 font-extrabold app-text">
                    Không tìm thấy phim
                </h3>

                <p class="mt-2 text-sm app-muted">
                    Thử thay đổi từ khóa hoặc điều kiện lọc.
                </p>

                @if($activeMovieFilters->isNotEmpty())

                    <a
                        href="{{ route('admin.movies.index') }}"
                        class="btn-primary mt-4"
                    >
                        Xóa bộ lọc
                    </a>

                @endif

            </div>

        @endforelse

    </div>


    {{-- =========================================================
        DESKTOP TABLE
    ========================================================== --}}
    <div class="cinema-card hidden overflow-hidden md:block">

        <div class="overflow-x-auto">

            <table class="admin-table min-w-[78rem]">

                <thead>

                    <tr>

                        <th scope="col">
                            Phim
                        </th>

                        <th scope="col">
                            Quốc gia
                        </th>

                        <th scope="col">
                            Thời lượng
                        </th>

                        <th scope="col">
                            Ngày khởi chiếu
                        </th>

                        <th scope="col">
                            Trạng thái
                        </th>

                        <th scope="col">
                            Thể loại
                        </th>

                        <th scope="col">
                            Suất sắp tới
                        </th>

                        <th
                            scope="col"
                            class="text-right"
                        >
                            Thao tác
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @forelse($movies as $movie)

                        @php
                            $movieStatus = $movieListStatusMeta[$movie->status]
                                ?? [
                                    'label' => $movie->status_label ?? 'Chưa rõ',
                                    'description' => 'Trạng thái chưa xác định.',
                                    'icon' => 'ph-question',
                                    'class' => 'bg-slate-500/10 text-slate-500 border-slate-500/20',
                                ];

                            $releaseDate = $movie->release_date;

                            $upcomingShowtimesCount =
                                $movie->upcoming_showtimes_count
                                ?? 0;
                        @endphp


                        <tr>

                            {{-- PHIM --}}
                            <td>

                                <div class="flex items-center gap-3">

                                    <div class="h-20 w-14 shrink-0 overflow-hidden rounded-xl border app-border bg-slate-950">

                                        @if($movie->poster_url)

                                            <img
                                                src="{{ $movie->poster_url }}"
                                                alt=""
                                                class="h-full w-full object-cover"
                                                loading="lazy"
                                            >

                                        @else

                                            <div class="admin-media-fallback h-full w-full text-xs">
                                                MM
                                            </div>

                                        @endif

                                    </div>


                                    <div class="min-w-0">

                                        <a
                                            href="{{ route('admin.movies.show', $movie) }}"
                                            class="block max-w-72 truncate font-extrabold app-heading hover:text-brand-start"
                                        >
                                            {{ $movie->title }}
                                        </a>

                                        <p class="mt-1 font-mono text-xs app-muted">
                                            #{{ $movie->id }}
                                        </p>

                                        @if($movie->slug)

                                            <p class="mt-1 max-w-64 truncate text-xs app-muted">
                                                {{ $movie->slug }}
                                            </p>

                                        @endif

                                    </div>

                                </div>

                            </td>


                            {{-- QUỐC GIA --}}
                            <td>

                                <span class="inline-flex items-center gap-1.5 text-sm app-text">

                                    <i class="ph ph-globe-hemisphere-east app-muted"></i>

                                    {{ $movie->country ?: 'Chưa cập nhật' }}

                                </span>

                            </td>


                            {{-- THỜI LƯỢNG --}}
                            <td>

                                @if($movie->duration)

                                    <span class="font-bold app-text">
                                        {{ $movie->duration }} phút
                                    </span>

                                @else

                                    <span class="text-sm text-warning">
                                        Chưa nhập
                                    </span>

                                @endif

                            </td>


                            {{-- NGÀY KHỞI CHIẾU --}}
                            <td>

                                @if($releaseDate)

                                    <p class="font-bold app-text">
                                        {{ $releaseDate->format('d/m/Y') }}
                                    </p>

                                    @if($releaseDate->isToday())

                                        <span class="mt-1 inline-flex items-center gap-1 text-xs font-bold text-success">
                                            <i class="ph ph-calendar-check"></i>
                                            Hôm nay
                                        </span>

                                    @elseif($releaseDate->isFuture())

                                        <span class="mt-1 inline-flex items-center gap-1 text-xs font-bold text-warning">
                                            <i class="ph ph-calendar-plus"></i>
                                            Sắp tới
                                        </span>

                                    @else

                                        <span class="mt-1 inline-flex items-center gap-1 text-xs app-muted">
                                            <i class="ph ph-check"></i>
                                            Đã phát hành
                                        </span>

                                    @endif

                                @else

                                    <span class="inline-flex items-center gap-1 text-xs font-bold text-warning">
                                        <i class="ph ph-calendar-x"></i>
                                        Chưa nhập
                                    </span>

                                @endif

                            </td>


                            {{-- TRẠNG THÁI --}}
                            <td>

                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-bold {{ $movieStatus['class'] }}"
                                    title="{{ $movieStatus['description'] }}"
                                >

                                    <i class="ph {{ $movieStatus['icon'] }}"></i>

                                    {{ $movieStatus['label'] }}

                                </span>

                            </td>


                            {{-- THỂ LOẠI --}}
                            <td>

                                <div class="max-w-60">

                                    @if($movie->genres->isNotEmpty())

                                        <div class="flex flex-wrap gap-1">

                                            @foreach($movie->genres->take(3) as $genre)

                                                <span class="rounded-lg border app-border px-2 py-1 text-xs font-semibold app-muted">
                                                    {{ $genre->name }}
                                                </span>

                                            @endforeach

                                            @if($movie->genres->count() > 3)

                                                <span class="rounded-lg border app-border px-2 py-1 text-xs font-semibold app-muted">
                                                    +{{ $movie->genres->count() - 3 }}
                                                </span>

                                            @endif

                                        </div>

                                    @else

                                        <span class="text-sm app-muted">
                                            Chưa phân loại
                                        </span>

                                    @endif

                                </div>

                            </td>


                            {{-- SUẤT SẮP TỚI --}}
                            <td>

                                <a
                                    href="{{ route('admin.showtimes.index', ['movie_id' => $movie->id]) }}"
                                    class="inline-flex items-center gap-2 font-extrabold text-brand-start hover:underline"
                                >

                                    <i class="ph ph-calendar-check"></i>

                                    {{ number_format($upcomingShowtimesCount) }}
                                    suất

                                </a>

                                @if($upcomingShowtimesCount === 0)

                                    <p class="mt-1 text-xs app-muted">
                                        Chưa có lịch sắp tới
                                    </p>

                                @endif

                            </td>


                            {{-- THAO TÁC --}}
                            <td>

                                <div class="flex items-center justify-end gap-2">

                                    <a
                                        href="{{ route('admin.movies.show', $movie) }}"
                                        class="admin-btn-info admin-action-btn"
                                        title="Xem chi tiết"
                                        aria-label="Xem phim {{ $movie->title }}"
                                    >
                                        <i class="ph ph-eye"></i>
                                    </a>


                                    @can('movies.update')

                                        @if($movie->status !== 'archived')

                                            <a
                                                href="{{ route('admin.movies.edit', $movie) }}"
                                                class="admin-btn-warning admin-action-btn"
                                                title="Chỉnh sửa"
                                                aria-label="Chỉnh sửa phim {{ $movie->title }}"
                                            >
                                                <i class="ph ph-pencil-simple"></i>
                                            </a>

                                        @endif

                                    @endcan

                                </div>

                            </td>

                        </tr>


                    @empty

                        <tr>

                            <td
                                colspan="8"
                                class="py-14 text-center"
                            >

                                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-start/10 text-brand-start">
                                    <i class="ph ph-film-slate text-3xl"></i>
                                </div>

                                <p class="mt-4 font-extrabold app-text">
                                    Không có phim phù hợp
                                </p>

                                <p class="mt-1 text-sm app-muted">
                                    Thay đổi từ khóa hoặc bộ lọc để tìm lại.
                                </p>

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>


        {{-- =====================================================
            PAGINATION
        ====================================================== --}}
        @if($movies->hasPages())

            <div class="border-t app-border px-5 py-4">

                <div class="mb-3 flex flex-wrap items-center justify-between gap-2 text-xs app-muted">

                    <p>
                        Hiển thị
                        <strong class="app-text">
                            {{ $firstItem ?? 0 }}
                        </strong>
                        đến
                        <strong class="app-text">
                            {{ $lastItem ?? 0 }}
                        </strong>
                        trong
                        <strong class="app-text">
                            {{ number_format($movieCount) }}
                        </strong>
                        phim.
                    </p>

                    <p>
                        Trang
                        <strong class="app-text">
                            {{ $currentPage }}
                        </strong>
                        /
                        <strong class="app-text">
                            {{ $lastPage }}
                        </strong>
                    </p>

                </div>

                {{ $movies->withQueryString()->links() }}

            </div>

        @endif

    </div>

</section>