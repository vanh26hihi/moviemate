@php
    $editing = isset($showtime) && $showtime;
    $movieValue = old('movie_id', $editing ? $showtime->movie_id : '');
    $roomValue = old('room_id', $editing ? $showtime->room_id : '');
    $dateValue = old('show_date', $editing ? $showtime->show_date?->format('Y-m-d') : '');
    $timeValue = old('show_time', $editing ? substr((string) $showtime->show_time, 0, 5) : '');
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div>
        <label class="cinema-label" for="movie_id">Phim *</label>
        <select id="movie_id" name="movie_id" class="cinema-input">
            <option value="">-- Chọn phim --</option>
            @foreach($movies as $movie)
                <option value="{{ $movie->id }}" data-runtime="{{ $movie->duration }}" @selected($movieValue == $movie->id)>
                    {{ $movie->title }} — {{ $movie->duration }} phút
                </option>
            @endforeach
        </select>
        @error('movie_id')<p class="text-sm text-error mt-2">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="cinema-label" for="room_id">Phòng active *</label>
        <select id="room_id" name="room_id" class="cinema-input">
            <option value="">-- Chọn phòng --</option>
            @foreach($rooms as $room)
                @php
                    $layout = $editing && (int) $showtime->room_id === (int) $room->id
                        ? $showtime->roomLayout
                        : $room->latestPublishedLayout;
                @endphp
                <option value="{{ $room->id }}" data-layout-version="{{ $layout->version }}" @selected($roomValue == $room->id)>
                    {{ $room->code }} — {{ $room->name }} (layout v{{ $layout->version }})
                </option>
            @endforeach
        </select>
        @error('room_id')<p class="text-sm text-error mt-2">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="cinema-label" for="show_date">Ngày chiếu *</label>
        <input id="show_date" type="date" name="show_date" value="{{ $dateValue }}" class="cinema-input">
        @error('show_date')<p class="text-sm text-error mt-2">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="cinema-label" for="show_time">Giờ bắt đầu *</label>
        <input id="show_time" type="time" name="show_time" value="{{ $timeValue }}" class="cinema-input">
        @error('show_time')<p class="text-sm text-error mt-2">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="cinema-label" for="price">Giá thường (VND) *</label>
        <input id="price" type="number" name="price" min="0" max="{{ \App\Models\Showtime::MAX_PRICE }}" step="1" value="{{ old('price', $editing ? $showtime->price : '') }}" class="cinema-input">
        @error('price')<p class="text-sm text-error mt-2">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="cinema-label" for="vip_price">Giá VIP (VND)</label>
        <input id="vip_price" type="number" name="vip_price" min="0" max="{{ \App\Models\Showtime::MAX_PRICE }}" step="1" value="{{ old('vip_price', $editing ? $showtime->vip_price : '') }}" class="cinema-input">
        @error('vip_price')<p class="text-sm text-error mt-2">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="cinema-label" for="status">Trạng thái *</label>
        <select id="status" name="status" class="cinema-input">
            @foreach(['active' => 'Đang chiếu', 'cancelled' => 'Đã hủy', 'finished' => 'Đã chiếu xong'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $editing ? $showtime->status : 'active') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status')<p class="text-sm text-error mt-2">{{ $message }}</p>@enderror
    </div>
</div>

<section id="schedule-preview" class="rounded-2xl border app-border p-5 bg-white/5" data-cleaning-buffer="{{ $cleaningBufferMinutes }}" data-timezone="{{ $cinemaTimezone }}">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
        <h2 class="font-extrabold app-text">Xem trước thời gian vận hành phòng</h2>
        <span class="text-xs app-muted">Múi giờ: {{ $cinemaTimezone }}</span>
    </div>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
        <div><span class="app-muted block">Thời lượng phim</span><strong id="preview-runtime" class="app-text">-- phút</strong></div>
        <div><span class="app-muted block">Phim kết thúc</span><strong id="preview-movie-end" class="app-text">--</strong></div>
        <div><span class="app-muted block">Vệ sinh phòng</span><strong class="app-text">{{ $cleaningBufferMinutes }} phút</strong></div>
        <div><span class="app-muted block">Phòng sẵn sàng</span><strong id="preview-room-ready" class="text-brand-start">--</strong></div>
    </div>
    <p class="text-xs app-muted mt-4">Thời gian kết thúc được backend tính lại từ runtime phim và cấu hình vệ sinh; dữ liệu từ trình duyệt không được dùng để xếp lịch.</p>
</section>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const movie = document.getElementById('movie_id');
    const date = document.getElementById('show_date');
    const time = document.getElementById('show_time');
    const preview = document.getElementById('schedule-preview');
    const runtimeOutput = document.getElementById('preview-runtime');
    const movieEndOutput = document.getElementById('preview-movie-end');
    const roomReadyOutput = document.getElementById('preview-room-ready');

    const pad = value => String(value).padStart(2, '0');
    const formatWallClock = value => `${pad(value.getUTCDate())}/${pad(value.getUTCMonth() + 1)}/${value.getUTCFullYear()} ${pad(value.getUTCHours())}:${pad(value.getUTCMinutes())}`;

    function refreshSchedulePreview() {
        const runtime = Number(movie.selectedOptions[0]?.dataset.runtime);
        const buffer = Number(preview.dataset.cleaningBuffer);
        runtimeOutput.textContent = Number.isInteger(runtime) && runtime > 0 ? `${runtime} phút` : '-- phút';

        if (!date.value || !time.value || !Number.isInteger(runtime) || runtime <= 0 || !Number.isInteger(buffer)) {
            movieEndOutput.textContent = '--';
            roomReadyOutput.textContent = '--';
            return;
        }

        const start = new Date(`${date.value}T${time.value}:00Z`);
        const movieEnd = new Date(start.getTime() + runtime * 60000);
        const roomReady = new Date(movieEnd.getTime() + buffer * 60000);
        const movieNextDay = movieEnd.toISOString().slice(0, 10) !== date.value ? ' (+1 ngày)' : '';
        const readyNextDay = roomReady.toISOString().slice(0, 10) !== date.value ? ' (+1 ngày)' : '';

        movieEndOutput.textContent = formatWallClock(movieEnd) + movieNextDay;
        roomReadyOutput.textContent = formatWallClock(roomReady) + readyNextDay;
    }

    [movie, date, time].forEach(input => input.addEventListener('change', refreshSchedulePreview));
    refreshSchedulePreview();
});
</script>
@endpush
