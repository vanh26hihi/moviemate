@extends('layouts.admin')

@section('title', 'Quản lý phòng chiếu - MovieMate Admin')
@section('page-title', 'Quản lý phòng chiếu')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <p class="text-brand-start text-sm font-extrabold uppercase tracking-[0.22em] mb-2">Cinema rooms</p>
            <h1 class="text-3xl font-extrabold app-text">Phòng chiếu</h1>
            <p class="app-muted mt-2">Quản lý phòng, loại phòng và sơ đồ ghế theo từng rạp.</p>
        </div>
        <a href="{{ route('admin.rooms.create') }}" class="btn-primary">
            <i class="ph-bold ph-plus"></i> Thêm phòng
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-2xl border border-success/30 bg-success/10 text-success px-4 py-3 text-sm font-bold">
            {{ session('success') }}
        </div>
    @endif

    <div class="cinema-card overflow-hidden">
        <div class="p-5 border-b app-border">
            <form method="GET" action="{{ route('admin.rooms.index') }}" class="grid grid-cols-1 md:grid-cols-[1fr_auto] gap-3">
                <label class="flex items-center gap-3 px-4 app-input border app-border rounded-2xl">
                    <i class="ph ph-magnifying-glass app-muted"></i>
                    <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Tìm tên phòng..." class="w-full bg-transparent app-text focus:outline-none py-3">
                </label>
                <button type="submit" class="btn-secondary !rounded-2xl">
                    <i class="ph ph-funnel"></i> Lọc
                </button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Rạp</th>
                        <th>Tên phòng</th>
                        <th>Loại</th>
                        <th>Số ghế</th>
                        <th>Trạng thái</th>
                        <th class="text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rooms as $room)
                        <tr>
                            <td class="app-muted">#{{ $room->id }}</td>
                            <td>
                                <p class="font-bold app-text">{{ $room->cinema->name ?? '-' }}</p>
                                <p class="text-xs app-muted">{{ $room->cinema->city ?? '' }}</p>
                            </td>
                            <td class="font-extrabold">{{ $room->name }}</td>
                            <td>{{ $room->room_type }}</td>
                            <td>{{ $room->total_seats }}</td>
                            <td>
                                @if($room->status === 'active')
                                    <span class="status-badge text-success bg-success/10">Hoạt động</span>
                                @else
                                    <span class="status-badge text-warning bg-warning/10">Tạm ngưng</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.seats.manage', $room) }}" class="btn-secondary !rounded-xl !px-3 !py-2 text-xs">
                                        <i class="ph ph-armchair"></i> Ghế
                                    </a>
                                    <a href="{{ route('admin.rooms.edit', $room) }}" class="btn-secondary !rounded-xl !px-3 !py-2 text-xs">
                                        <i class="ph ph-pencil-simple"></i> Sửa
                                    </a>
                                    <form action="{{ route('admin.rooms.destroy', $room) }}" method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa phòng này?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center justify-center w-9 h-9 rounded-xl border app-border app-muted hover:bg-error hover:border-error hover:text-white transition-colors">
                                            <i class="ph ph-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center app-muted py-10">Không có phòng nào.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-5 border-t app-border">
            {{ $rooms->links() }}
        </div>
    </div>
</div>
@endsection
