@extends('layouts.admin')

@section('title', 'Quản lý ghế - MovieMate Admin')
@section('page-title', 'Quản lý ghế')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <p class="text-brand-start text-sm font-extrabold uppercase tracking-[0.22em] mb-2">Seat map</p>
            <h1 class="text-3xl font-extrabold app-text">Ghế phòng chiếu</h1>
            <p class="app-muted mt-2">Lọc theo phòng, đổi loại ghế và trạng thái bảo trì.</p>
        </div>
    </div>

    @if(session('success') || session('error'))
        <div class="rounded-2xl border {{ session('success') ? 'border-success/30 bg-success/10 text-success' : 'border-error/30 bg-error/10 text-error' }} px-4 py-3 text-sm font-bold">
            {{ session('success') ?? session('error') }}
        </div>
    @endif

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
                    <i class="ph ph-funnel"></i> Lọc
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
                                    {{ strtoupper($seat->type) }}
                                </span>
                            </td>
                            <td>
                                @if($seat->status === 'active')
                                    <span class="status-badge text-success bg-success/10">Hoạt động</span>
                                @else
                                    <span class="status-badge text-error bg-error/10">Bảo trì</span>
                                @endif
                            </td>
                            <td>
                                <form action="{{ route('admin.seats.update', $seat) }}" method="POST" class="flex flex-col lg:flex-row justify-end gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <select name="type" class="cinema-input !py-2 !text-xs lg:w-28">
                                        <option value="normal" {{ $seat->type == 'normal' ? 'selected' : '' }}>Normal</option>
                                        <option value="vip" {{ $seat->type == 'vip' ? 'selected' : '' }}>VIP</option>
                                        <option value="couple" {{ $seat->type == 'couple' ? 'selected' : '' }}>Couple</option>
                                    </select>
                                    <select name="status" class="cinema-input !py-2 !text-xs lg:w-36">
                                        <option value="active" {{ $seat->status == 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="maintenance" {{ $seat->status == 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                                    </select>
                                    <button type="submit" class="btn-primary !rounded-xl !px-3 !py-2 text-xs">Lưu</button>
                                </form>
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
