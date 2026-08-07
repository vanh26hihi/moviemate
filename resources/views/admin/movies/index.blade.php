@extends('layouts.admin')

@section('title', 'Quản lý phim')
@section('page-title', 'Quản lý phim')

@php
    $statusMeta = [
        'now_showing' => [
            'label' => 'Đang chiếu',
            'class' => 'bg-success/10 text-success border border-success/20',
        ],
        'coming_soon' => [
            'label' => 'Sắp chiếu',
            'class' => 'bg-warning/10 text-warning border border-warning/20',
        ],
        'stopped' => [
            'label' => 'Ngừng chiếu',
            'class' => 'bg-slate-500/10 text-slate-500 border border-slate-500/20',
        ],
    ];

    $searchValue = $search ?? request('search', '');
    $statusValue = $status ?? request('status', '');
    $genreValue = (string) ($genreId ?? request('genre', ''));
    $countryValue = $country ?? request('country', '');
@endphp

@section('content')

<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Quản lý phim</h1>

        <p class="admin-page-subtitle">
            Quản lý danh sách phim, poster, trạng thái và thể loại.
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
</div>

@if(session('success'))
    <div class="mb-5 rounded-2xl border border-success/30 bg-success/10 px-4 py-3 text-sm font-semibold text-success">
        {{ session('success') }}
    </div>
@endif

<div class="admin-toolbar">
    <form
        method="GET"
        action="{{ route('admin.movies.index') }}"
        class="flex w-full flex-col gap-3 xl:flex-row"
    >

        <label class="relative flex-1">
            <i class="ph ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 app-text-muted"></i>

            <input
                type="text"
                name="search"
                value="{{ $searchValue }}"
                placeholder="Tìm theo tên phim, mô tả, quốc gia hoặc trạng thái..."
                class="admin-input pl-11"
                autocomplete="off"
            >
        </label>

        <select
            name="status"
            class="admin-input xl:w-52"
        >
            <option value="">Tất cả trạng thái</option>

            <option
                value="now_showing"
                {{ $statusValue === 'now_showing' ? 'selected' : '' }}
            >
                Đang chiếu
            </option>

            <option
                value="coming_soon"
                {{ $statusValue === 'coming_soon' ? 'selected' : '' }}
            >
                Sắp chiếu
            </option>

            <option
                value="stopped"
                {{ $statusValue === 'stopped' ? 'selected' : '' }}
            >
                Ngừng chiếu
            </option>
        </select>

        <select
            name="genre"
            class="admin-input xl:w-52"
        >
            <option value="">Tất cả thể loại</option>

            @foreach($genres as $genre)
                <option
                    value="{{ $genre->id }}"
                    {{ $genreValue === (string) $genre->id ? 'selected' : '' }}
                >
                    {{ $genre->name }}
                </option>
            @endforeach
        </select>

        <select
            name="country"
            class="admin-input xl:w-52"
        >
            <option value="">Tất cả quốc gia</option>

            @foreach($countries as $countryItem)
                <option
                    value="{{ $countryItem }}"
                    {{ $countryValue === $countryItem ? 'selected' : '' }}
                >
                    {{ $countryItem }}
                </option>
            @endforeach
        </select>

        <button
            type="submit"
            class="admin-btn-primary"
        >
            <i class="ph-bold ph-magnifying-glass"></i>
            Tìm kiếm
        </button>

        @if(
            $searchValue !== '' ||
            $statusValue !== '' ||
            $genreValue !== '' ||
            $countryValue !== ''
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

@if(
    $searchValue !== '' ||
    $statusValue !== '' ||
    $genreValue !== '' ||
    $countryValue !== ''
)
    <div class="mb-5 rounded-2xl border app-border app-card px-4 py-3 text-sm app-text-muted">

        Tìm thấy

        <span class="font-extrabold app-text">
            {{ $movies->total() }}
        </span>

        phim phù hợp

        @if($countryValue !== '')
            tại quốc gia

            <span class="font-extrabold text-brand-start">
                {{ $countryValue }}
            </span>
        @endif

        .
    </div>
@endif

<div class="admin-table-card">

    <div class="overflow-x-auto">

        <table class="admin-table">

            <thead>
                <tr>
                    <th>#</th>
                    <th>Poster</th>
                    <th>Tiêu đề</th>
                    <th>Thể loại</th>
                    <th>Trạng thái</th>
                    <th class="text-right">Hành động</th>
                </tr>
            </thead>

            <tbody>

                @forelse($movies as $movie)

                    @php
                        $movieStatus = $statusMeta[$movie->status] ?? [
                            'label' => $movie->status ?: 'Chưa rõ',
                            'class' => 'bg-slate-500/10 text-slate-500 border border-slate-500/20',
                        ];
                    @endphp

                    <tr>

                        <td class="font-mono text-xs app-text-muted">
                            #{{ $movie->id }}
                        </td>

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

                        <td>
                            <div class="max-w-sm">

                                <a
                                    href="{{ route('admin.movies.show', $movie) }}"
                                    class="font-extrabold app-heading transition-colors hover:text-brand-start"
                                >
                                    {{ $movie->title }}
                                </a>

                                <p class="mt-1 truncate text-xs app-text-muted">
                                    {{ $movie->slug }}
                                </p>

                                @if($movie->country)
                                    <p class="mt-1 text-xs app-text-muted">
                                        {{ $movie->country }}
                                    </p>
                                @endif

                            </div>
                        </td>

                        <td>
                            <div class="max-w-xs text-sm app-text-muted">
                                {{ $movie->genres->pluck('name')->join(', ') ?: 'Chưa phân loại' }}
                            </div>
                        </td>

                        <td>
                            <span class="admin-badge {{ $movieStatus['class'] }}">
                                {{ $movieStatus['label'] }}
                            </span>
                        </td>

                        <td>
                            <div class="flex items-center justify-end gap-2">

                                <a
                                    href="{{ route('admin.movies.show', $movie) }}"
                                    class="admin-btn-info admin-action-btn"
                                    title="Xem"
                                    aria-label="Xem"
                                    data-tooltip="Xem"
                                >
                                    <i class="ph ph-eye"></i>
                                </a>

                                @can('movies.update')
                                    <a
                                        href="{{ route('admin.movies.edit', $movie) }}"
                                        class="admin-btn-warning admin-action-btn"
                                        title="Sửa"
                                        aria-label="Sửa"
                                        data-tooltip="Sửa"
                                    >
                                        <i class="ph ph-pencil-simple"></i>
                                    </a>
                                @endcan

                                @can('movies.delete')
                                    <form
                                        action="{{ route('admin.movies.destroy', $movie) }}"
                                        method="POST"
                                        class="inline"
                                        onsubmit="return confirm('Bạn có chắc muốn xóa phim này?');"
                                    >
                                        @csrf
                                        @method('DELETE')

                                        <button
                                            type="submit"
                                            class="admin-btn-danger admin-action-btn"
                                            title="Xóa"
                                            aria-label="Xóa"
                                            data-tooltip="Xóa"
                                        >
                                            <i class="ph ph-trash"></i>
                                        </button>
                                    </form>
                                @endcan

                            </div>
                        </td>

                    </tr>

                @empty

                    <tr>
                        <td colspan="6" class="admin-empty">

                            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-start/10 text-brand-start">
                                <i class="ph-fill ph-film-slate text-3xl"></i>
                            </div>

                            @if(
                                $searchValue !== '' ||
                                $statusValue !== '' ||
                                $genreValue !== '' ||
                                $countryValue !== ''
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

    @if($movies->hasPages())
        <div class="border-t app-border px-5 py-4">
            {{ $movies->links() }}
        </div>
    @endif

</div>

@endsection