@extends('layouts.admin')

@section('title', 'Định dạng trình chiếu - MovieMate')
@section('page-title', 'Định dạng trình chiếu')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Định dạng trình chiếu</h1>
        <p class="admin-page-subtitle">Danh mục dùng chung toàn hệ thống cho phim và khả năng kỹ thuật của phòng.</p>
    </div>
    @can('presentation_formats.manage')
        <a class="admin-btn-primary" href="{{ route('admin.presentation-formats.create') }}"><i class="ph ph-plus" aria-hidden="true"></i> Thêm định dạng</a>
    @endcan
</div>

<form method="GET" action="{{ route('admin.presentation-formats.index') }}" class="admin-form-card mb-5 grid gap-4 md:grid-cols-[1fr_14rem_auto]">
    <label><span class="admin-label">Tìm kiếm</span><input class="admin-input" name="search" value="{{ $search }}" maxlength="100" placeholder="Mã hoặc tên định dạng"></label>
    <label><span class="admin-label">Trạng thái</span><select class="admin-input" name="status"><option value="">Tất cả</option><option value="active" @selected($status === 'active')>Đang sử dụng</option><option value="archived" @selected($status === 'archived')>Đã lưu trữ</option></select></label>
    <div class="flex items-end"><button class="admin-btn-secondary w-full" type="submit">Lọc</button></div>
</form>

<div class="app-card overflow-hidden rounded-2xl border app-border">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="border-b app-border text-left"><th class="p-4">Mã</th><th class="p-4">Tên</th><th class="p-4">Trạng thái</th><th class="p-4">Thứ tự</th><th class="p-4 text-right">Thao tác</th></tr></thead>
            <tbody>
            @forelse($formats as $format)
                <tr class="border-b app-border last:border-0">
                    <td class="p-4 font-mono font-bold app-text">{{ $format->code }}</td>
                    <td class="p-4"><p class="font-bold app-text">{{ $format->name }}</p>@if($format->description)<p class="mt-1 max-w-xl text-xs app-muted">{{ $format->description }}</p>@endif</td>
                    <td class="p-4"><span class="rounded-full px-3 py-1 text-xs font-bold {{ $format->is_active ? 'bg-success/10 text-success' : 'bg-slate-500/10 app-muted' }}">{{ $format->status_label }}</span></td>
                    <td class="p-4 app-muted">{{ $format->sort_order }}</td>
                    <td class="p-4"><div class="flex justify-end gap-2">
                        @can('presentation_formats.manage')
                            <a class="admin-btn-secondary !px-3 !py-2" href="{{ route('admin.presentation-formats.edit', $format) }}">Sửa</a>
                            @if($format->is_active)
                                <form method="POST" action="{{ route('admin.presentation-formats.archive', $format) }}" onsubmit="return confirm('Lưu trữ định dạng này? Các liên kết hiện có sẽ được giữ nguyên.')">@csrf @method('PATCH')<button class="admin-btn-secondary !px-3 !py-2">Lưu trữ</button></form>
                            @endif
                        @endcan
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="5" class="p-8 text-center app-muted">Chưa có định dạng trình chiếu phù hợp.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($formats->hasPages())<div class="mt-5">{{ $formats->links() }}</div>@endif
@endsection
