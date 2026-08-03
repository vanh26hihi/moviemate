@extends('layouts.admin')

@section('title', 'Quản lý rạp')
@section('page-title', 'Quản lý rạp')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Quản lý rạp</h1>
        <p class="admin-page-subtitle">Quản lý thông tin rạp, địa chỉ và trạng thái vận hành.</p>
    </div>
    @can('cinema.create')<a href="{{ route('admin.cinemas.create') }}" class="admin-btn-primary">
        <i class="ph-bold ph-plus"></i>
        Thêm mới
    </a>@endcan
</div>

@if(session('success'))
    <div class="mb-5 rounded-2xl border border-success/30 bg-success/10 text-success px-4 py-3 text-sm font-semibold">
        {{ session('success') }}
    </div>
@endif

<div class="admin-toolbar">
    <form method="GET" action="{{ route('admin.cinemas.index') }}" class="flex w-full flex-col sm:flex-row gap-3">
        <label class="relative flex-1">
            <i class="ph ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 app-text-muted"></i>
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Tìm tên rạp..."
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
                    <th>Tên rạp</th>
                    <th>Địa chỉ</th>
                    <th>Thành phố</th>
                    <th>Điện thoại</th>
                    <th>Trạng thái</th>
                    <th class="text-right">Hành động</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cinemas as $cinema)
                    @php
                        $isActive = $cinema->status === 'active';
                    @endphp
                    <tr>
                        <td class="font-mono text-xs app-text-muted">#{{ $cinema->id }}</td>
                        <td>
                            <div class="font-extrabold app-heading">{{ $cinema->name }}</div>
                        </td>
                        <td>
                            <div class="max-w-xs app-text-muted text-sm line-clamp-2">{{ $cinema->address }}</div>
                        </td>
                        <td>
                            <span class="admin-badge bg-ai-start/10 text-ai-start border border-ai-start/20">{{ $cinema->city }}</span>
                        </td>
                        <td class="app-text-muted">{{ $cinema->phone ?? 'Chưa cập nhật' }}</td>
                        <td>
                            <span class="admin-badge {{ $isActive ? 'bg-success/10 text-success border border-success/20' : 'bg-error/10 text-error border border-error/20' }}">
                                {{ $isActive ? 'Hoạt động' : 'Tạm ngừng' }}
                            </span>
                        </td>
                        <td>
                            <div class="flex items-center justify-end gap-2">
                                @can('cinema.update')<a href="{{ route('admin.cinemas.edit', $cinema) }}" class="admin-btn-warning admin-action-btn" title="Sửa" aria-label="Sửa" data-tooltip="Sửa">
                                    <i class="ph ph-pencil-simple"></i>
                                </a>@endcan
                                @can('cinema.delete')<form action="{{ route('admin.cinemas.destroy', $cinema) }}" method="POST" class="inline"
                                      onsubmit="return confirm('Bạn có chắc muốn xóa rạp này?');">
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
                        <td colspan="7" class="admin-empty">
                            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-start/10 text-brand-start">
                                <i class="ph-fill ph-buildings text-3xl"></i>
                            </div>
                            Chưa có rạp nào.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t app-border px-5 py-4">
        {{ $cinemas->links() }}
    </div>
</div>
@endsection
