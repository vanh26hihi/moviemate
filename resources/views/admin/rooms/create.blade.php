@extends('layouts.admin')

@section('title', 'Thêm phòng chiếu - MovieMate Admin')
@section('page-title', 'Thêm phòng chiếu')

@section('content')
<div class="max-w-3xl">
    <div class="cinema-card p-6 sm:p-8">
        <h1 class="text-2xl font-extrabold app-text mb-2">Thông tin phòng chiếu</h1>
        <p class="app-muted mb-6">Tạo phòng mới và sau đó cấu hình sơ đồ ghế trong màn quản lý ghế.</p>

        @if ($errors->any())
            <div class="mb-6 rounded-2xl border border-error/30 bg-error/10 text-error px-4 py-3 text-sm font-bold">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('admin.rooms.store') }}" method="POST" class="space-y-5">
            @csrf

            <div>
                <label class="cinema-label">Rạp *</label>
                <select name="cinema_id" required class="cinema-input">
                    @foreach($cinemas as $cinema)
                        <option value="{{ $cinema->id }}" {{ old('cinema_id') == $cinema->id ? 'selected' : '' }}>
                            {{ $cinema->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="cinema-label">Tên phòng *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required class="cinema-input" placeholder="Ví dụ: Phòng 1">
                </div>
                <div>
                    <label class="cinema-label">Loại phòng *</label>
                    <select name="room_type" required class="cinema-input">
                        <option value="2D" {{ old('room_type', '2D') === '2D' ? 'selected' : '' }}>2D</option>
                        <option value="3D" {{ old('room_type') === '3D' ? 'selected' : '' }}>3D</option>
                        <option value="IMAX" {{ old('room_type') === 'IMAX' ? 'selected' : '' }}>IMAX</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="cinema-label">Số ghế *</label>
                    <input type="number" name="total_seats" value="{{ old('total_seats', 0) }}" required class="cinema-input" min="0">
                </div>
                <div>
                    <label class="cinema-label">Trạng thái *</label>
                    <select name="status" required class="cinema-input">
                        <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Hoạt động</option>
                        <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Không hoạt động</option>
                    </select>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 pt-3">
                <button type="submit" class="btn-primary">Lưu phòng</button>
                <a href="{{ route('admin.rooms.index') }}" class="btn-secondary">Hủy</a>
            </div>
        </form>
    </div>
</div>
@endsection
