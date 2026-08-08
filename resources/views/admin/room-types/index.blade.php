@extends('layouts.admin')

@section('title', 'Danh mục loại phòng - MovieMate')
@section('page-title', 'Danh mục loại phòng')

@section('content')
<div class="space-y-6">
    <div class="admin-page-header">
        <div>
            <h1 class="admin-page-title">Loại phòng chiếu</h1>
            <p class="admin-page-subtitle">Dùng chung cho phòng chiếu, mẫu sơ đồ và quy tắc phụ thu. Loại đã lưu trữ vẫn được giữ trên dữ liệu lịch sử.</p>
        </div>
        @can('room_types.manage')
            <a class="admin-btn-primary" href="{{ route('admin.room-types.create') }}"><i class="ph ph-plus" aria-hidden="true"></i> Thêm loại phòng</a>
        @endcan
    </div>

    <form method="GET" class="app-card grid gap-3 rounded-2xl border app-border p-4 md:grid-cols-[1fr_14rem_auto]">
        <label class="cinema-label">Tìm kiếm<input class="cinema-input" name="search" value="{{ request('search') }}" placeholder="Tên hoặc mã loại phòng"></label>
        <label class="cinema-label">Trạng thái<select class="cinema-input" name="status"><option value="">Tất cả</option><option value="active" @selected(request('status') === 'active')>Đang sử dụng</option><option value="archived" @selected(request('status') === 'archived')>Đã lưu trữ</option></select></label>
        <button class="admin-btn-secondary self-end" type="submit">Lọc</button>
    </form>

    <div class="app-card overflow-x-auto rounded-2xl border app-border">
        <table class="admin-table w-full">
            <thead><tr><th>Tên hiển thị</th><th>Mã hệ thống</th><th>Mô tả</th><th>Thứ tự</th><th>Đang dùng</th><th>Trạng thái</th><th class="text-right">Thao tác</th></tr></thead>
            <tbody>
            @forelse($roomTypes as $roomType)
                <tr>
                    <td class="font-bold app-text">{{ $roomType->name }}</td>
                    <td><code>{{ $roomType->code }}</code></td>
                    <td>{{ $roomType->description ?: '—' }}</td>
                    <td>{{ $roomType->sort_order }}</td>
                    <td>{{ $roomType->rooms_count }} phòng · {{ $roomType->pricing_rules_count }} quy tắc giá</td>
                    <td><span class="status-badge {{ $roomType->is_active ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning' }}">{{ $roomType->status_label }}</span></td>
                    <td>
                        @can('room_types.manage')
                        <div class="flex justify-end gap-2">
                            <a class="admin-btn-secondary !px-3 !py-2" href="{{ route('admin.room-types.edit', $roomType) }}">Sửa</a>
                            <form method="POST" action="{{ route('admin.room-types.status', $roomType) }}" onsubmit="return confirm(@js($roomType->is_active ? 'Lưu trữ loại phòng này?' : 'Kích hoạt lại loại phòng này?'))">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $roomType->is_active ? 0 : 1 }}"><button class="admin-btn-secondary !px-3 !py-2">{{ $roomType->is_active ? 'Lưu trữ' : 'Kích hoạt' }}</button></form>
                        </div>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="p-10 text-center app-muted">Chưa có loại phòng phù hợp.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $roomTypes->links() }}
</div>
@endsection
