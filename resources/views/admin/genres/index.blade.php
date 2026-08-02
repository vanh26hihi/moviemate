@extends('layouts.admin')

@section('title', 'Quản lý thể loại')
@section('page-title', 'Quản lý thể loại')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Quản lý thể loại</h1>
        <p class="admin-page-subtitle">Quản lý nhóm thể loại dùng để phân loại phim trong MovieMate.</p>
    </div>
    <a href="{{ route('admin.genres.create') }}" class="admin-btn-primary">
        <i class="ph-bold ph-plus"></i>
        Thêm mới
    </a>
</div>

@if(session('success'))
    <div class="mb-5 rounded-2xl border border-success/30 bg-success/10 text-success px-4 py-3 text-sm font-semibold">
        {{ session('success') }}
    </div>
@endif

<div class="admin-toolbar">
    <form method="GET" action="{{ route('admin.genres.index') }}" class="flex w-full flex-col sm:flex-row gap-3">
        <label class="relative flex-1">
            <i class="ph ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 app-text-muted"></i>
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Tìm kiếm tên thể loại..."
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
                    <th>Tên</th>
                    <th>Slug</th>
                    <th class="text-right">Hành động</th>
                </tr>
            </thead>
            <tbody>
                @forelse($genres as $genre)
                    <tr>
                        <td class="font-mono text-xs app-text-muted">#{{ $genre->id }}</td>
                        <td>
                            <div class="font-extrabold app-heading">{{ $genre->name }}</div>
                        </td>
                        <td>
                            <span class="font-mono text-xs app-text-muted">{{ $genre->slug }}</span>
                        </td>
                        <td>
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.genres.edit', $genre) }}" class="admin-btn-warning admin-action-btn" title="Sửa" aria-label="Sửa" data-tooltip="Sửa">
                                    <i class="ph ph-pencil-simple"></i>
                                </a>
                                <form action="{{ route('admin.genres.destroy', $genre) }}" method="POST"
                                      onsubmit="return confirm('Bạn có chắc muốn xóa thể loại này?');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="admin-btn-danger admin-action-btn" title="Xóa" aria-label="Xóa" data-tooltip="Xóa">
                                        <i class="ph ph-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="admin-empty">
                            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-start/10 text-brand-start">
                                <i class="ph-fill ph-tag text-3xl"></i>
                            </div>
                            Chưa có thể loại nào.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t app-border px-5 py-4">
        {{ $genres->links() }}
    </div>
</div>
@endsection
