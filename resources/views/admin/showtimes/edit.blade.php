@extends('layouts.admin')

@section('title', 'Chỉnh sửa suất chiếu - MovieMate Admin')
@section('page-title', 'Chỉnh sửa suất chiếu')

@section('content')
<div class="max-w-5xl">
    <div class="cinema-card p-6 sm:p-8">
        <h1 class="text-2xl font-extrabold app-text mb-2">Cập nhật suất chiếu</h1>
        <p class="app-muted mb-6">{{ $showtime->movie->title ?? 'Suất chiếu' }}</p>

        <form method="POST" action="{{ route('admin.showtimes.update', $showtime) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="cinema-label">Phim *</label>
                    <select name="movie_id" class="cinema-input">
                        <option value="">-- Chọn phim --</option>
                        @foreach($movies as $movie)
                            <option value="{{ $movie->id }}" {{ old('movie_id', $showtime->movie_id) == $movie->id ? 'selected' : '' }}>{{ $movie->title }}</option>
                        @endforeach
                    </select>
                    @error('movie_id')<p class="text-sm text-error mt-2">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="cinema-label">Rạp *</label>
                    <select name="cinema_id" id="cinema-select" class="cinema-input">
                        <option value="">-- Chọn rạp --</option>
                        @foreach($cinemas as $cinema)
                            <option value="{{ $cinema->id }}" {{ old('cinema_id', $showtime->cinema_id) == $cinema->id ? 'selected' : '' }}>{{ $cinema->name }}</option>
                        @endforeach
                    </select>
                    @error('cinema_id')<p class="text-sm text-error mt-2">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="cinema-label">Phòng *</label>
                    <select name="room_id" id="room-select" class="cinema-input">
                        <option value="">-- Chọn phòng --</option>
                    </select>
                    @error('room_id')<p class="text-sm text-error mt-2">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="cinema-label">Ngày chiếu *</label>
                    <input type="date" name="show_date" value="{{ old('show_date', $showtime->show_date ? \Carbon\Carbon::parse($showtime->show_date)->format('Y-m-d') : '') }}" class="cinema-input">
                    @error('show_date')<p class="text-sm text-error mt-2">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="cinema-label">Giờ chiếu *</label>
                    <input type="time" name="show_time" value="{{ old('show_time', $showtime->show_time ? \Carbon\Carbon::parse($showtime->show_time)->format('H:i') : '') }}" class="cinema-input">
                    @error('show_time')<p class="text-sm text-error mt-2">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="cinema-label">Giá thường (VND) *</label>
                    <input type="number" name="price" step="1000" value="{{ old('price', $showtime->price) }}" class="cinema-input">
                    @error('price')<p class="text-sm text-error mt-2">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="cinema-label">Giá VIP (VND)</label>
                    <input type="number" name="vip_price" step="1000" value="{{ old('vip_price', $showtime->vip_price) }}" class="cinema-input">
                    @error('vip_price')<p class="text-sm text-error mt-2">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="cinema-label">Trạng thái *</label>
                    <select name="status" class="cinema-input">
                        <option value="active" {{ old('status', $showtime->status) == 'active' ? 'selected' : '' }}>Đang chiếu</option>
                        <option value="cancelled" {{ old('status', $showtime->status) == 'cancelled' ? 'selected' : '' }}>Đã hủy</option>
                        <option value="finished" {{ old('status', $showtime->status) == 'finished' ? 'selected' : '' }}>Đã chiếu xong</option>
                    </select>
                    @error('status')<p class="text-sm text-error mt-2">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex flex-col sm:flex-row justify-end gap-3 pt-2">
                <a href="{{ route('admin.showtimes.index') }}" class="btn-secondary">Hủy</a>
                <button type="submit" class="btn-primary">Cập nhật</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    const cinemaSelect = document.getElementById('cinema-select');
    const roomSelect = document.getElementById('room-select');

    function loadRooms(cinemaId, selectedRoomId = null) {
        roomSelect.innerHTML = '<option value="">-- Đang tải phòng... --</option>';
        if (!cinemaId) {
            roomSelect.innerHTML = '<option value="">-- Chọn phòng --</option>';
            return;
        }

        fetch(`/api/cinemas/${cinemaId}/rooms`)
            .then(res => res.json())
            .then(data => {
                roomSelect.innerHTML = '<option value="">-- Chọn phòng --</option>' + data.map(room => {
                    const selected = String(selectedRoomId || '') === String(room.id) ? 'selected' : '';
                    return `<option value="${room.id}" ${selected}>${room.name}</option>`;
                }).join('');
            })
            .catch(() => roomSelect.innerHTML = '<option value="">-- Lỗi tải phòng --</option>');
    }

    cinemaSelect.addEventListener('change', function () {
        loadRooms(this.value);
    });

    loadRooms('{{ old('cinema_id', $showtime->cinema_id) }}', '{{ old('room_id', $showtime->room_id) }}');
</script>
@endpush
@endsection
