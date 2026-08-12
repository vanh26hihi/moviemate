@extends('layouts.admin')

@section('title', 'Sao chép lịch chiếu - Quản trị MovieMate')
@section('page-title', 'Sao chép lịch chiếu')

@section('content')
<div class="space-y-6" data-showtime-schedule-copy data-cinema-id="{{ $cinema?->id }}">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <p class="text-brand-start text-sm font-extrabold uppercase tracking-[0.22em] mb-2">Lịch chiếu</p>
            <h1 class="text-3xl font-extrabold app-text">Sao chép lịch đã có</h1>
            <p class="app-muted mt-2">Tạo danh sách ý định theo cùng phòng và giờ bắt đầu. Bạn vẫn phải Kiểm tra lịch rồi Đăng toàn bộ trong không gian tạo nhiều suất.</p>
        </div>
        <a href="{{ route('admin.showtimes.index') }}" class="btn-secondary"><i class="ph ph-arrow-left"></i> Quay lại lịch chiếu</a>
    </div>

    <form method="POST" action="{{ route('admin.showtimes.copy.generate') }}" class="cinema-card p-6 space-y-6">
        @csrf

        @if($cinema)
            <input type="hidden" name="cinema_id" value="{{ $cinema->id }}">
            <div>
                <span class="form-label">Chi nhánh</span>
                <p class="app-text font-bold">{{ $cinema->name }} · {{ $cinema->timezone }}</p>
            </div>
        @else
            <div>
                <label for="copyCinema" class="form-label">Chi nhánh</label>
                <select id="copyCinema" name="cinema_id" class="cinema-input" data-copy-cinema required>
                    <option value="">Chọn chi nhánh</option>
                    @foreach($cinemas as $availableCinema)
                        <option value="{{ $availableCinema->id }}" @selected((string) old('cinema_id') === (string) $availableCinema->id)>{{ $availableCinema->code }} · {{ $availableCinema->name }}</option>
                    @endforeach
                </select>
                @error('cinema_id')<p class="text-sm text-error mt-2">{{ $message }}</p>@enderror
            </div>
        @endif

        <fieldset>
            <legend class="form-label">Phạm vi sao chép</legend>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <label class="cinema-card p-4 flex items-start gap-3 cursor-pointer">
                    <input type="radio" name="scope" value="room" class="mt-1" @checked(old('scope', 'room') === 'room')>
                    <span><strong class="app-text block">Một phòng / một ngày</strong><span class="text-sm app-muted">Chỉ lấy các suất đang hoạt động của phòng đã chọn.</span></span>
                </label>
                <label class="cinema-card p-4 flex items-start gap-3 cursor-pointer">
                    <input type="radio" name="scope" value="cinema" class="mt-1" @checked(old('scope') === 'cinema')>
                    <span><strong class="app-text block">Toàn chi nhánh / một ngày</strong><span class="text-sm app-muted">Lấy lịch đang hoạt động của tất cả phòng trong chi nhánh.</span></span>
                </label>
            </div>
            @error('scope')<p class="text-sm text-error mt-2">{{ $message }}</p>@enderror
        </fieldset>

        <div>
            <label for="copyRoom" class="form-label">Phòng nguồn</label>
            <select id="copyRoom" name="room_id" class="cinema-input" data-copy-room>
                <option value="">Chọn phòng</option>
                @foreach($rooms as $room)
                    <option value="{{ $room->id }}" data-cinema-id="{{ $room->cinema_id }}" @selected((string) old('room_id') === (string) $room->id)>{{ $room->cinema->code }} · {{ $room->code }} · {{ $room->name }}{{ $room->status === 'active' ? '' : ' · không hoạt động' }}</option>
                @endforeach
            </select>
            <p class="text-xs app-muted mt-2">Phòng chỉ bắt buộc khi sao chép theo một phòng.</p>
            @error('room_id')<p class="text-sm text-error mt-2">{{ $message }}</p>@enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="copySourceDate" class="form-label">Ngày nguồn tại chi nhánh</label>
                <input id="copySourceDate" type="date" name="source_date" value="{{ old('source_date') }}" class="cinema-input" required>
                @error('source_date')<p class="text-sm text-error mt-2">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="copyTargetDate" class="form-label">Ngày đích tại chi nhánh</label>
                <input id="copyTargetDate" type="date" name="target_date" value="{{ old('target_date') }}" class="cinema-input" required>
                @error('target_date')<p class="text-sm text-error mt-2">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="rounded-2xl app-secondary p-4 text-sm app-muted">
            Chỉ phim, phòng, ngày đích và giờ bắt đầu được chuyển thành ý định. Sơ đồ, giá vé, thời gian vệ sinh và xung đột được máy chủ xác định lại theo trạng thái hiện tại.
        </div>

        <div class="flex flex-wrap justify-end gap-3">
            <a href="{{ route('admin.showtimes.bulk.index') }}" class="btn-secondary">Mở trình tạo nhiều suất trống</a>
            <button type="submit" class="btn-primary"><i class="ph-bold ph-copy"></i> Tạo danh sách ý định</button>
        </div>
    </form>
</div>
@endsection
