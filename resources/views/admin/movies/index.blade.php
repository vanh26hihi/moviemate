@extends('layouts.admin')

@section('title', 'Quản lý phim')
@section('page-title', 'Quản lý phim')

@php
    $statusMeta = [
        'now_showing' => ['label' => 'Đang chiếu', 'class' => 'bg-success/10 text-success border border-success/20'],
        'coming_soon' => ['label' => 'Sắp chiếu', 'class' => 'bg-warning/10 text-warning border border-warning/20'],
        'stopped' => ['label' => 'Ngừng chiếu', 'class' => 'bg-slate-500/10 text-slate-500 border border-slate-500/20'],
    ];
@endphp

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Quản lý phim</h1>
        <p class="admin-page-subtitle">Quản lý danh sách phim, poster, trạng thái và thể loại.</p>
    </div>
    @can('movies.create')<a href="{{ route('admin.movies.create') }}" class="admin-btn-primary">
        <i class="ph-bold ph-plus"></i>
        Thêm mới
    </a>@endcan
</div>

<div class="admin-toolbar">
    <form method="GET" action="{{ route('admin.movies.index') }}" class="flex w-full flex-col sm:flex-row gap-3">
        <label class="relative flex-1">
            <i class="ph ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 app-text-muted"></i>
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Tìm kiếm tiêu đề phim..."
                   class="admin-input pl-11">
        </label>
        <button type="submit" class="admin-btn-primary">
            <i class="ph-bold ph-funnel"></i>
            Tìm
        </button>
    </form>
</div>

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
                    <th class="text-right">Hành động</th>
                </tr>
            </thead>
            <tbody>
                @forelse($movies as $movie)
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
@endphp
                    <tr>
                        <td class="font-mono text-xs app-text-muted">#{{ $movie->id }}</td>
                        <td>
                            <div class="h-20 w-14 overflow-hidden rounded-xl border app-border bg-slate-950">
                                @if($movie->poster_url)
                                    <img src="{{ $movie->poster_url }}" alt="{{ $movie->title }}" class="h-full w-full object-cover" loading="lazy">
                                @else
                                    <div class="admin-media-fallback h-full w-full text-xs">MM</div>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="max-w-sm">
                                <a href="{{ route('admin.movies.show', $movie) }}" class="font-extrabold app-heading hover:text-brand-start transition-colors">
                                    {{ $movie->title }}
                                </a>
                                <p class="mt-1 text-xs app-text-muted truncate">{{ $movie->slug }}</p>
                            </div>
                        </td>
                        <td>
                            <div class="max-w-xs app-text-muted text-sm">
                                {{ $movie->genres->pluck('name')->join(', ') ?: 'Chưa phân loại' }}
                            </div>
                        </td>
                        <td>
                            @php
                                $status = $statusMeta[$movie->status] ?? ['label' => $movie->status ?: 'Chưa rõ', 'class' => 'bg-slate-500/10 text-slate-500 border border-slate-500/20'];
                            @endphp         
                            <span class="admin-badge {{ $status['class'] }}">{{ $status['label'] }}</span>
                        </td>
                        <td>
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.movies.show', $movie) }}" class="admin-btn-info admin-action-btn" title="Xem" aria-label="Xem" data-tooltip="Xem">
                                    <i class="ph ph-eye"></i>
                                </a>
                                @can('movies.update')<a href="{{ route('admin.movies.edit', $movie) }}" class="admin-btn-warning admin-action-btn" title="Sửa" aria-label="Sửa" data-tooltip="Sửa">
                                    <i class="ph ph-pencil-simple"></i>
                                </a>@endcan
                                @can('movies.delete')<form action="{{ route('admin.movies.destroy', $movie) }}" method="POST"
                                      onsubmit="return confirm('Bạn có chắc muốn xóa phim này?');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="admin-btn-danger admin-action-btn" title="Xóa" aria-label="Xóa" data-tooltip="Xóa">
                                        <i class="ph ph-trash"></i>
                                    </button>
                                </form>@endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="admin-empty">
                            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-start/10 text-brand-start">
                                <i class="ph-fill ph-film-slate text-3xl"></i>
                            </div>
                            Chưa có phim nào.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t app-border px-5 py-4">
        {{ $movies->links() }}
    </div>
</div>
@endsection
