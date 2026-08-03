@extends('layouts.admin')
@section('title', 'Thêm suất chiếu - MovieMate Admin')
@section('page-title', 'Thêm suất chiếu')
@section('content')
<div class="max-w-5xl"><div class="cinema-card p-6 sm:p-8">
    <h1 class="text-2xl font-extrabold app-text mb-2">Thông tin suất chiếu</h1>
    <p class="app-muted mb-6">Cơ sở cố định: {{ $cinema->name }}</p>
    <form method="POST" action="{{ route('admin.showtimes.store') }}" class="space-y-6">@csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div><label class="cinema-label">Phim *</label><select name="movie_id" class="cinema-input"><option value="">-- Chọn phim --</option>@foreach($movies as $movie)<option value="{{ $movie->id }}" @selected(old('movie_id') == $movie->id)>{{ $movie->title }}</option>@endforeach</select>@error('movie_id')<p class="text-sm text-error mt-2">{{ $message }}</p>@enderror</div>
            <div><label class="cinema-label">Phòng active *</label><select name="room_id" class="cinema-input"><option value="">-- Chọn phòng --</option>@foreach($rooms as $room)<option value="{{ $room->id }}" @selected(old('room_id') == $room->id)>{{ $room->code }} — {{ $room->name }} ({{ $room->room_type }})</option>@endforeach</select>@error('room_id')<p class="text-sm text-error mt-2">{{ $message }}</p>@enderror</div>
            <div><label class="cinema-label">Ngày chiếu *</label><input type="date" name="show_date" value="{{ old('show_date') }}" class="cinema-input">@error('show_date')<p class="text-sm text-error mt-2">{{ $message }}</p>@enderror</div>
            <div><label class="cinema-label">Giờ chiếu *</label><input type="time" name="show_time" value="{{ old('show_time') }}" class="cinema-input">@error('show_time')<p class="text-sm text-error mt-2">{{ $message }}</p>@enderror</div>
            <div><label class="cinema-label">Giá thường (VND) *</label><input type="number" name="price" step="1000" value="{{ old('price') }}" class="cinema-input">@error('price')<p class="text-sm text-error mt-2">{{ $message }}</p>@enderror</div>
            <div><label class="cinema-label">Giá VIP (VND)</label><input type="number" name="vip_price" step="1000" value="{{ old('vip_price') }}" class="cinema-input"></div>
            <div><label class="cinema-label">Trạng thái *</label><select name="status" class="cinema-input"><option value="active">Đang chiếu</option><option value="cancelled">Đã hủy</option><option value="finished">Đã chiếu xong</option></select></div>
        </div>
        <div class="flex justify-end gap-3"><a href="{{ route('admin.showtimes.index') }}" class="btn-secondary">Hủy</a><button class="btn-primary">Lưu suất chiếu</button></div>
    </form>
</div></div>
@endsection
