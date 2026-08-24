@extends('layouts.admin')

@php
    $canManageGlobalCatalog = app(\App\Services\CinemaAccessService::class)->hasGlobalAccess(auth()->user());
    $statusMeta = [
        'draft' => ['class' => 'bg-blue-500/10 text-blue-500', 'icon' => 'ph-note-pencil'],
        'now_showing' => ['class' => 'bg-success/10 text-success', 'icon' => 'ph-play-circle'],
        'coming_soon' => ['class' => 'bg-warning/10 text-warning', 'icon' => 'ph-calendar'],
        'inactive' => ['class' => 'bg-slate-500/10 text-slate-500', 'icon' => 'ph-pause-circle'],
        'archived' => ['class' => 'bg-slate-700/20 text-slate-500', 'icon' => 'ph-archive'],
    ];
    $hasFilters = $search !== '' || $status !== '' || $genreId || $country !== '' || $sort !== 'updated_at' || $direction !== 'desc';
    $scopeLabel = $currentCinema?->name ?? 'Toàn hệ thống';
@endphp

@section('title', $canManageGlobalCatalog ? 'Quản lý phim - MovieMate' : 'Danh mục phim - MovieMate')
@section('page-title', 'Phim')

@section('content')
<div class="space-y-6">

    {{-- =========================================================
        HEADER
        Giữ giao diện mới từ main + nút thêm phim từ feature
    ========================================================== --}}
    <header class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">

        <div>
            <span class="status-badge bg-brand-start/10 text-brand-start">
                <i class="ph ph-film-slate" aria-hidden="true"></i>
                Danh mục toàn chuỗi
            </span>

            <h1 class="mt-3 text-2xl font-extrabold app-heading sm:text-3xl">
                {{ $canManageGlobalCatalog ? 'Quản lý phim' : 'Danh mục phim dùng chung' }}
            </h1>

            <p class="mt-2 max-w-3xl app-muted">
                Mỗi phim có một hồ sơ dùng chung.
                Vòng đời được quản lý tại hồ sơ phim;
                ngày khởi chiếu là thông tin phát hành,
                còn việc một chi nhánh có chiếu phim hay không
                được quyết định bởi lịch suất chiếu.
            </p>
        </div>

        @can('movies.create')
            <a
                href="{{ route('admin.movies.create') }}"
                class="admin-btn-primary"
            >
                <i class="ph-bold ph-plus"></i>
                Thêm mới
            </a>
        @endcan

    </header>


    {{-- =========================================================
        THANH TÌM KIẾM + BỘ LỌC
    ========================================================== --}}
    <div class="admin-toolbar">

        <form
            method="GET"
            action="{{ route('admin.movies.index') }}"
            class="flex w-full flex-col gap-3 xl:flex-row"
        >

            {{-- Tìm kiếm --}}
            <label class="relative flex-1">

                <i
                    class="ph ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 app-text-muted"
                    aria-hidden="true"
                ></i>

                <input
                    type="text"
                    name="search"
                    value="{{ $searchValue ?? '' }}"
                    placeholder="Tìm theo tên phim, mô tả, quốc gia hoặc trạng thái..."
                    class="admin-input pl-11"
                    autocomplete="off"
                >

            </label>


            {{-- Lọc trạng thái --}}
            <select
                name="status"
                class="admin-input xl:w-52"
            >
                <option value="">
                    Tất cả trạng thái
                </option>

                <option
                    value="draft"
                    {{ ($statusValue ?? '') === 'draft' ? 'selected' : '' }}
                >
                    Bản nháp
                </option>

                <option
                    value="now_showing"
                    {{ ($statusValue ?? '') === 'now_showing' ? 'selected' : '' }}
                >
                    Đang chiếu
                </option>

                <option
                    value="coming_soon"
                    {{ ($statusValue ?? '') === 'coming_soon' ? 'selected' : '' }}
                >
                    Sắp chiếu
                </option>

                <option
                    value="inactive"
                    {{ ($statusValue ?? '') === 'inactive' ? 'selected' : '' }}
                >
                    Ngừng hoạt động
                </option>

                <option
                    value="archived"
                    {{ ($statusValue ?? '') === 'archived' ? 'selected' : '' }}
                >
                    Đã lưu trữ
                </option>
            </select>


            {{-- Lọc thể loại --}}
            <select
                name="genre"
                class="admin-input xl:w-52"
            >
                <option value="">
                    Tất cả thể loại
                </option>

                @foreach(($genres ?? collect()) as $genre)
                    <option
                        value="{{ $genre->id }}"
                        {{ ($genreValue ?? '') === (string) $genre->id ? 'selected' : '' }}
                    >
                        {{ $genre->name }}
                    </option>
                @endforeach

            </select>


            {{-- Lọc quốc gia --}}
            <select
                name="country"
                class="admin-input xl:w-52"
            >
                <option value="">
                    Tất cả quốc gia
                </option>

                @foreach(($countries ?? collect()) as $countryItem)
                    <option
                        value="{{ $countryItem }}"
                        {{ ($countryValue ?? '') === $countryItem ? 'selected' : '' }}
                    >
                        {{ $countryItem }}
                    </option>
                @endforeach

            </select>


            {{-- Submit --}}
            <button
                type="submit"
                class="admin-btn-primary"
            >
                <i class="ph-bold ph-magnifying-glass"></i>
                Tìm kiếm
            </button>


            {{-- Xóa lọc --}}
            @if(
                ($searchValue ?? '') !== '' ||
                ($statusValue ?? '') !== '' ||
                ($genreValue ?? '') !== '' ||
                ($countryValue ?? '') !== ''
            )
                <a
                    href="{{ route('admin.movies.index') }}"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border app-border px-4 py-3 text-sm font-bold app-text transition-colors hover:border-brand-start hover:text-brand-start"
                >
                    <i class="ph ph-x"></i>
                    Xóa lọc
                </a>
            @endif

        </form>

    </div>


    {{-- =========================================================
        KẾT QUẢ TÌM KIẾM
    ========================================================== --}}
    @if(
        ($searchValue ?? '') !== '' ||
        ($statusValue ?? '') !== '' ||
        ($genreValue ?? '') !== '' ||
        ($countryValue ?? '') !== ''
    )

        <div class="rounded-2xl border app-border app-card px-4 py-3 text-sm app-text-muted">

            Tìm thấy

            <span class="font-extrabold app-text">
                {{ $movies->total() }}
            </span>

            phim phù hợp

            @if(($countryValue ?? '') !== '')

                tại quốc gia

                <span class="font-extrabold text-brand-start">
                    {{ $countryValue }}
                </span>

            @endif

            .

        </div>

    @endif


    {{-- =========================================================
        BẢNG PHIM
    ========================================================== --}}
    <div class="admin-table-card">

        <div class="overflow-x-auto">

            <table class="admin-table">

                <thead>
                    <tr>
                        <th>#</th>
                        <th>Áp phích</th>
                        <th>Tiêu đề</th>
                        <th>Thể loại</th>
                        <th>Trạng thái</th>
                        <th class="text-right">
                            Hành động
                        </th>
                    </tr>
                </thead>

                <tbody>

                    @forelse($movies as $movie)

                        @php
                            $statusMeta = [
                                'draft' => [
                                    'label' => 'Bản nháp',
                                    'class' => 'bg-blue-500/10 text-blue-400 border border-blue-500/20',
                                ],

                                'coming_soon' => [
                                    'label' => 'Sắp chiếu',
                                    'class' => 'bg-warning/10 text-warning border border-warning/20',
                                ],

                                'now_showing' => [
                                    'label' => 'Đang chiếu',
                                    'class' => 'bg-success/10 text-success border border-success/20',
                                ],

                                'inactive' => [
                                    'label' => 'Ngừng chiếu',
                                    'class' => 'bg-slate-500/10 text-slate-500 border border-slate-500/20',
                                ],

                                'archived' => [
                                    'label' => 'Đã lưu trữ',
                                    'class' => 'bg-slate-700/20 text-slate-400 border border-slate-600/20',
                                ],
                            ];

                            $movieStatus = $statusMeta[$movie->status] ?? [
                                'label' => $movie->status ?: 'Chưa rõ',
                                'class' => 'bg-slate-500/10 text-slate-500 border border-slate-500/20',
                            ];
                        @endphp


                        <tr>

                            {{-- ID --}}
                            <td class="font-mono text-xs app-text-muted">
                                #{{ $movie->id }}
                            </td>


                            {{-- Poster --}}
                            <td>

                                <div class="h-20 w-14 overflow-hidden rounded-xl border app-border bg-slate-950">

                                    @if($movie->poster_url)

                                        <img
                                            src="{{ $movie->poster_url }}"
                                            alt="{{ $movie->title }}"
                                            class="h-full w-full object-cover"
                                            loading="lazy"
                                        >

                                    @else

                                        <div class="admin-media-fallback h-full w-full text-xs">
                                            MM
                                        </div>

                                    @endif

                                </div>

                            </td>


                            {{-- Tên phim --}}
                            <td>

                                <div class="max-w-sm">

                                    <a
                                        href="{{ route('admin.movies.show', $movie) }}"
                                        class="font-extrabold app-heading transition-colors hover:text-brand-start"
                                    >
                                        {{ $movie->title }}
                                    </a>

                                    @if($movie->slug)
                                        <p class="mt-1 truncate text-xs app-text-muted">
                                            {{ $movie->slug }}
                                        </p>
                                    @endif

                                    @if($movie->country)
                                        <p class="mt-1 text-xs app-text-muted">
                                            <i class="ph ph-globe-hemisphere-east"></i>
                                            {{ $movie->country }}
                                        </p>
                                    @endif

                                </div>

                            </td>


                            {{-- Thể loại --}}
                            <td>

                                <div class="max-w-xs text-sm app-text-muted">

                                    {{ $movie->genres->pluck('name')->join(', ') ?: 'Chưa phân loại' }}

                                </div>

                            </td>


                            {{-- Trạng thái --}}
                            <td>

                                <span class="admin-badge {{ $movieStatus['class'] }}">
                                    {{ $movieStatus['label'] }}
                                </span>

                            </td>


                            {{-- Hành động --}}
                            <td>

                                <div class="flex items-center justify-end gap-2">

                                    <a
                                        href="{{ route('admin.movies.show', $movie) }}"
                                        class="admin-btn-info admin-action-btn"
                                        title="Xem"
                                        aria-label="Xem phim {{ $movie->title }}"
                                        data-tooltip="Xem"
                                    >
                                        <i class="ph ph-eye"></i>
                                    </a>


                                    @can('movies.update')

                                        <a
                                            href="{{ route('admin.movies.edit', $movie) }}"
                                            class="admin-btn-warning admin-action-btn"
                                            title="Sửa"
                                            aria-label="Sửa phim {{ $movie->title }}"
                                            data-tooltip="Sửa"
                                        >
                                            <i class="ph ph-pencil-simple"></i>
                                        </a>

                                    @endcan

                                </div>

                            </td>

                        </tr>


                    @empty

                        <tr>

                            <td
                                colspan="6"
                                class="admin-empty"
                            >

                                <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-start/10 text-brand-start">

                                    <i class="ph-fill ph-film-slate text-3xl"></i>

                                </div>


                                @if(
                                    ($searchValue ?? '') !== '' ||
                                    ($statusValue ?? '') !== '' ||
                                    ($genreValue ?? '') !== '' ||
                                    ($countryValue ?? '') !== ''
                                )

                                    Không tìm thấy phim phù hợp với điều kiện lọc.

                                @else

                                    Chưa có phim nào.

                                @endif

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>


        {{-- =====================================================
            PHÂN TRANG
        ====================================================== --}}
        @if($movies->hasPages())

            <div class="border-t app-border px-5 py-4">

                {{ $movies->links() }}

            </div>

        @endif

    </div>

</div>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($canManageGlobalCatalog)
                @can('genres.view')<a href="{{ route('admin.genres.index') }}" class="btn-secondary"><i class="ph ph-tag" aria-hidden="true"></i>Thể loại phim</a>@endcan
                @can('movies.create')<a href="{{ route('admin.movies.create') }}" class="btn-primary"><i class="ph-bold ph-plus" aria-hidden="true"></i>Thêm phim</a>@endcan
            @endif
        </div>
    </header>

    <section class="rounded-2xl border border-brand-start/20 bg-brand-start/5 p-4" aria-label="Giải thích phạm vi phim và chi nhánh">
        <div class="flex gap-3">
            <i class="ph ph-info text-xl text-brand-start" aria-hidden="true"></i>
            <div><p class="font-bold app-text">Danh mục không thay đổi khi chọn chi nhánh</p><p class="mt-1 text-sm app-muted">Phạm vi <strong>{{ $scopeLabel }}</strong> chỉ được dùng để đếm lịch chiếu sắp tới bên dưới. Muốn ngừng chiếu tại một rạp, hãy điều chỉnh lịch vận hành của rạp đó; “Ngừng hoạt động” ở hồ sơ phim sẽ dừng phim trên toàn chuỗi.</p></div>
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Tổng hợp danh mục phim">
        @foreach([
            ['Phim đang quản lý', $summary['movies'], 'ph-film-slate', 'text-brand-start'],
            ['Bản nháp', $summary['drafts'], 'ph-note-pencil', 'text-blue-500'],
            ['Chưa có ngày khởi chiếu', $summary['missing_release_dates'], 'ph-calendar-x', 'text-warning'],
            ['Suất sắp tới · '.$scopeLabel, $summary['upcoming_showtimes'], 'ph-calendar-check', 'text-success'],
        ] as [$label, $value, $icon, $color])
            <article class="cinema-card flex items-center gap-4 p-4 sm:p-5">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-current/10 {{ $color }}"><i class="ph {{ $icon }} text-xl" aria-hidden="true"></i></span>
                <div class="min-w-0"><p class="text-sm app-muted">{{ $label }}</p><p class="mt-1 text-xl font-black app-text">{{ number_format($value) }}</p></div>
            </article>
        @endforeach
    </section>

    <section class="cinema-card p-4 sm:p-5" aria-labelledby="movie-workflow-title">
        <h2 id="movie-workflow-title" class="font-extrabold app-text">Quy trình dễ nhớ</h2>
        <ol class="mt-4 grid gap-3 md:grid-cols-3">
            @foreach([
                ['1', 'Tạo một hồ sơ', 'Phim mới luôn ở Bản nháp; dùng slug để phân biệt đường dẫn.'],
                ['2', 'Hoàn thiện và công bố', 'Nhập ngày khởi chiếu, định dạng, hình ảnh rồi chuyển vòng đời phù hợp.'],
                ['3', 'Xếp lịch theo rạp', 'Mỗi chi nhánh tự có phòng và suất chiếu; khách chỉ thấy suất còn mở bán.'],
            ] as [$step, $title, $description])
                <li class="flex gap-3 rounded-2xl border app-border p-4"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-start font-black text-white">{{ $step }}</span><div><p class="font-bold app-text">{{ $title }}</p><p class="mt-1 text-sm app-muted">{{ $description }}</p></div></li>
            @endforeach
        </ol>
    </section>

    <form method="GET" action="{{ route('admin.movies.index') }}" class="cinema-card p-4 sm:p-5" aria-label="Bộ lọc danh mục phim">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <label class="cinema-label xl:col-span-2">Tìm phim<input type="search" name="search" maxlength="120" value="{{ $search }}" class="cinema-input mt-1" placeholder="Tên phim, mô tả hoặc quốc gia"></label>
            <label class="cinema-label">Vòng đời phim<select name="status" class="cinema-input mt-1">
                <option value="">Tất cả trạng thái</option>
                <option value="now_showing" @selected($status === 'now_showing')>Đã khởi chiếu</option>
                <option value="coming_soon" @selected($status === 'coming_soon')>Sắp khởi chiếu</option>
                <option value="draft" @selected($status === 'draft')>Bản nháp</option>
                <option value="inactive" @selected($status === 'inactive')>Ngừng hoạt động toàn chuỗi</option>
                <option value="archived" @selected($status === 'archived')>Đã lưu trữ</option>
            </select></label>
            <label class="cinema-label">Thể loại<select name="genre" class="cinema-input mt-1"><option value="">Tất cả thể loại</option>@foreach($genres as $genre)<option value="{{ $genre->id }}" @selected((int) $genreId === (int) $genre->id)>{{ $genre->name }}</option>@endforeach</select></label>
            <label class="cinema-label">Quốc gia<select name="country" class="cinema-input mt-1"><option value="">Tất cả quốc gia</option>@foreach($countries as $countryItem)<option value="{{ $countryItem }}" @selected($country === $countryItem)>{{ $countryItem }}</option>@endforeach</select></label>
            <label class="cinema-label">Sắp xếp<select name="sort" class="cinema-input mt-1"><option value="updated_at" @selected($sort === 'updated_at')>Cập nhật gần nhất</option><option value="release_date" @selected($sort === 'release_date')>Ngày khởi chiếu</option><option value="title" @selected($sort === 'title')>Tên phim</option></select></label>
            <label class="cinema-label">Thứ tự<select name="direction" class="cinema-input mt-1"><option value="desc" @selected($direction === 'desc')>Mới / lớn trước</option><option value="asc" @selected($direction === 'asc')>Cũ / nhỏ trước</option></select></label>
            <div class="flex flex-wrap items-end gap-2"><button class="btn-primary" type="submit"><i class="ph ph-magnifying-glass" aria-hidden="true"></i>Tìm phim</button>@if($hasFilters)<a class="btn-secondary" href="{{ route('admin.movies.index') }}">Đặt lại</a>@endif</div>
        </div>
    </form>

    @if($hasFilters)<p class="text-sm app-muted">Tìm thấy <strong class="app-text">{{ number_format($movies->total()) }}</strong> phim phù hợp.</p>@endif

    <section class="cinema-card overflow-hidden" aria-labelledby="movie-list-title">
        <div class="border-b app-border px-5 py-4"><h2 id="movie-list-title" class="font-extrabold app-text">Danh sách phim</h2><p class="mt-1 text-sm app-muted">Vòng đời được quản lý tại hồ sơ phim; ngày khởi chiếu là thông tin phát hành. Số suất được tính trong phạm vi {{ $scopeLabel }}.</p></div>

        <div class="space-y-3 p-4 md:hidden" aria-label="Danh sách phim trên điện thoại">
            @forelse($movies as $movie)
                @php($movieStatus = $statusMeta[$movie->status] ?? $statusMeta['archived'])
                <article class="rounded-2xl border app-border p-4">
                    <div class="flex gap-3">
                        <div class="h-24 w-16 shrink-0 overflow-hidden rounded-xl border app-border bg-slate-950">@if($movie->poster_url)<img src="{{ $movie->poster_url }}" alt="" class="h-full w-full object-cover" loading="lazy">@else<div class="admin-media-fallback h-full w-full text-xs">MM</div>@endif</div>
                        <div class="min-w-0 flex-1"><a href="{{ route('admin.movies.show', $movie) }}" class="font-extrabold app-heading">{{ $movie->title }}</a><p class="mt-1 text-xs app-muted">{{ $movie->country ?: 'Chưa cập nhật quốc gia' }} · {{ $movie->duration }} phút</p><span class="status-badge mt-2 {{ $movieStatus['class'] }}"><i class="ph {{ $movieStatus['icon'] }}" aria-hidden="true"></i>{{ $movie->status_label }}</span></div>
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm"><div><dt class="app-muted">Ngày khởi chiếu</dt><dd class="mt-1 font-bold app-text">{{ $movie->release_date?->format('d/m/Y') ?? 'Chưa nhập' }}</dd></div><div><dt class="app-muted">Lịch sắp tới</dt><dd class="mt-1 font-bold app-text">{{ $movie->upcoming_showtimes_count }} suất</dd></div></dl>
                    <div class="mt-4 grid grid-cols-2 gap-2"><a class="btn-secondary justify-center" href="{{ route('admin.movies.show', $movie) }}">Xem phim</a>@if($canManageGlobalCatalog)@can('movies.update')@if($movie->status !== 'archived')<a class="btn-secondary justify-center" href="{{ route('admin.movies.edit', $movie) }}">Chỉnh sửa</a>@endif @endcan @endif</div>
                </article>
            @empty
                <div class="py-10 text-center"><i class="ph ph-film-slate text-3xl app-muted" aria-hidden="true"></i><p class="mt-3 font-bold app-text">Không có phim phù hợp</p><p class="mt-1 text-sm app-muted">Hãy thay đổi từ khóa hoặc điều kiện lọc.</p></div>
            @endforelse
        </div>

        <div class="hidden overflow-x-auto md:block">
            <table class="admin-table min-w-[68rem]">
                <thead><tr><th>Phim</th><th>Ngày khởi chiếu</th><th>Vòng đời</th><th>Lịch sắp tới</th><th>Thể loại</th><th class="text-right">Thao tác</th></tr></thead>
                <tbody>
                    @forelse($movies as $movie)
                        @php($movieStatus = $statusMeta[$movie->status] ?? $statusMeta['archived'])
                        <tr>
                            <td><div class="flex items-center gap-3"><div class="h-20 w-14 shrink-0 overflow-hidden rounded-xl border app-border bg-slate-950">@if($movie->poster_url)<img src="{{ $movie->poster_url }}" alt="" class="h-full w-full object-cover" loading="lazy">@else<div class="admin-media-fallback h-full w-full text-xs">MM</div>@endif</div><div class="min-w-0"><a href="{{ route('admin.movies.show', $movie) }}" class="font-extrabold app-heading hover:text-brand-start">{{ $movie->title }}</a><p class="mt-1 text-xs app-muted">#{{ $movie->id }} · {{ $movie->country ?: 'Chưa cập nhật quốc gia' }} · {{ $movie->duration }} phút</p></div></div></td>
                            <td><span class="font-bold app-text">{{ $movie->release_date?->format('d/m/Y') ?? 'Chưa nhập' }}</span></td>
                            <td><span class="status-badge {{ $movieStatus['class'] }}"><i class="ph {{ $movieStatus['icon'] }}" aria-hidden="true"></i>{{ $movie->status_label }}</span></td>
                            <td><a href="{{ route('admin.showtimes.index', ['movie_id' => $movie->id]) }}" class="font-bold text-brand-start">{{ $movie->upcoming_showtimes_count }} suất</a><span class="mt-1 block text-xs app-muted">{{ $scopeLabel }}</span></td>
                            <td><span class="block max-w-64 text-sm app-muted">{{ $movie->genres->pluck('name')->join(', ') ?: 'Chưa phân loại' }}</span></td>
                            <td><div class="flex justify-end gap-2"><a class="btn-secondary !px-3 !py-2 text-xs" href="{{ route('admin.movies.show', $movie) }}">Xem</a>@if($canManageGlobalCatalog)@can('movies.update')@if($movie->status !== 'archived')<a class="btn-secondary !px-3 !py-2 text-xs" href="{{ route('admin.movies.edit', $movie) }}">Chỉnh sửa</a>@endif @endcan @endif</div></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-12 text-center"><i class="ph ph-film-slate text-3xl app-muted" aria-hidden="true"></i><p class="mt-3 font-bold app-text">Không có phim phù hợp</p><p class="mt-1 text-sm app-muted">Hãy thay đổi từ khóa hoặc điều kiện lọc.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($movies->hasPages())<div class="border-t app-border px-5 py-4">{{ $movies->links() }}</div>@endif
    </section>
</div>
@endsection
