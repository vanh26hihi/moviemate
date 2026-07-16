@extends('layouts.admin')

@section('title', 'Sửa phòng chiếu - MovieMate Admin')
@section('page-title', 'Sửa phòng chiếu')

@section('content')
<div class="max-w-3xl">
    <div class="cinema-card p-6 sm:p-8">
        <h1 class="text-2xl font-extrabold app-text mb-2">{{ $room->name }}</h1>
        <p class="app-muted mb-6">Cập nhật thông tin phòng chiếu và trạng thái sử dụng.</p>

        @if ($errors->any())
            <div class="mb-6 rounded-2xl border border-error/30 bg-error/10 text-error px-4 py-3 text-sm font-bold">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('admin.rooms.update', $room) }}" method="POST" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label class="cinema-label">Rạp *</label>
                <select name="cinema_id" required class="cinema-input">
                    @foreach($cinemas as $cinema)
                        <option value="{{ $cinema->id }}" {{ old('cinema_id', $room->cinema_id) == $cinema->id ? 'selected' : '' }}>
                            {{ $cinema->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="cinema-label">Tên phòng *</label>
                    <input type="text" name="name" value="{{ old('name', $room->name) }}" required class="cinema-input">
                </div>
                <div>
                    <label class="cinema-label">Loại phòng *</label>
                    <select name="room_type" required class="cinema-input">
                        <option value="2D" {{ old('room_type', $room->room_type) === '2D' ? 'selected' : '' }}>2D</option>
                        <option value="3D" {{ old('room_type', $room->room_type) === '3D' ? 'selected' : '' }}>3D</option>
                        <option value="IMAX" {{ old('room_type', $room->room_type) === 'IMAX' ? 'selected' : '' }}>IMAX</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="cinema-label">Số ghế *</label>
                    <input type="number" name="total_seats" value="{{ old('total_seats', $room->total_seats) }}" required class="cinema-input" min="0">
                </div>
                <div>
                    <label class="cinema-label">Trạng thái *</label>
                    <select name="status" required class="cinema-input">
                        <option value="active" {{ old('status', $room->status) == 'active' ? 'selected' : '' }}>Hoạt động</option>
                        <option value="inactive" {{ old('status', $room->status) == 'inactive' ? 'selected' : '' }}>Không hoạt động</option>
                    </select>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 pt-3">
                <button type="submit" class="btn-primary">Cập nhật</button>
                <a href="{{ route('admin.rooms.index') }}" class="btn-secondary">Hủy</a>
            </div>
        </form>
    </div>
</div>
@endsection
