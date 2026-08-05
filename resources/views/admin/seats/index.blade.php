@extends('layouts.admin')

@section('title', 'Bảo trì ghế - Quản trị MovieMate')
@section('page-title', 'Bảo trì ghế')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <p class="text-brand-start text-sm font-extrabold uppercase tracking-[0.22em] mb-2">Phòng chiếu</p>
            <h1 class="text-3xl font-extrabold app-text">Bảo trì ghế</h1>
            <p class="app-muted mt-2">Lọc theo phòng, đổi loại ghế và trạng thái vận hành.</p>
        </div>
    </div>

    <div class="cinema-card overflow-hidden">
        <div class="p-5 border-b app-border">
            <form method="GET" action="{{ route('admin.seats.index') }}" class="flex flex-col sm:flex-row gap-3">
                <select name="room_id" class="cinema-input sm:max-w-md">
                    <option value="">Tất cả phòng</option>
                    @foreach($rooms as $room)
                        <option value="{{ $room->id }}" {{ request('room_id') == $room->id ? 'selected' : '' }}>
                            {{ $room->name }} ({{ $room->cinema->name ?? '' }})
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="btn-secondary">
                    <i class="ph ph-funnel" aria-hidden="true"></i> Lọc
                </button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Phòng</th>
                        <th>Mã ghế</th>
                        <th>Hàng</th>
                        <th>Số</th>
                        <th>Loại</th>
                        <th>Trạng thái</th>
                        <th class="text-right">Cập nhật</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($seats as $seat)
                        <tr>
                            <td class="app-muted">#{{ $seat->id }}</td>
                            <td>{{ $seat->room->name ?? '' }}</td>
                            <td><span class="font-extrabold text-brand-start">{{ $seat->seat_code }}</span></td>
                            <td>{{ $seat->row }}</td>
                            <td>{{ $seat->number }}</td>
                            <td>
                                <span class="status-badge {{ $seat->type === 'vip' ? 'text-warning bg-warning/10' : 'text-ai-start bg-ai-start/10' }}">
                                    {{ $seat->type_label }}
                                </span>
                            </td>
                            <td>
                                @if($seat->status === 'active')
                                    <span class="status-badge text-success bg-success/10">{{ $seat->status_label }}</span>
                                @else
                                    <span class="status-badge text-error bg-error/10">{{ $seat->status_label }}</span>
                                @endif
                            </td>
                            <td>
                                @can('seats.maintenance.update')<form action="{{ route('admin.seats.update', $seat) }}" method="POST" class="flex flex-col lg:flex-row justify-end gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <select name="type" class="cinema-input !py-2 !text-xs lg:w-28">
                                        <option value="normal" {{ $seat->type == 'normal' ? 'selected' : '' }}>Ghế thường</option>
                                        <option value="vip" {{ $seat->type == 'vip' ? 'selected' : '' }}>VIP</option>
                                        @if($seat->type === 'couple')<option value="couple" selected>Ghế đôi (cả cặp)</option>@endif
                                    </select>
                                    <select name="status" class="cinema-input !py-2 !text-xs lg:w-36">
                                        <option value="active" {{ $seat->status == 'active' ? 'selected' : '' }}>Đang sử dụng</option>
                                        <option value="maintenance" {{ $seat->status == 'maintenance' ? 'selected' : '' }}>Đang bảo trì</option>
                                    </select>
                                    <button type="submit" class="btn-primary !rounded-xl !px-3 !py-2 text-xs">Lưu</button>
                                </form>@else<span class="text-xs app-muted">Chỉ xem</span>@endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center app-muted py-10">Không có ghế nào.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-5 border-t app-border">
            {{ $seats->links() }}
        </div>
    </div>
</div>
@endsection
