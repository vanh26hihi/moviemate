@php
    $editing = isset($showtime) && $showtime;
    $movieValue = old('movie_id', $editing ? $showtime->movie_id : '');
    $formatValue = old('presentation_format_id', $editing ? $showtime->presentation_format_id : '');
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
                <option value="{{ $movie->id }}" data-runtime="{{ $movie->duration }}" data-format-ids="{{ $movie->supportedPresentationFormats->pluck('id')->implode(',') }}" @selected($movieValue == $movie->id)>
                    {{ $movie->title }} — {{ $movie->duration }} phút
                </option>
            @endforeach
        </select>
        @error('movie_id')<p class="text-sm text-error mt-2">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="cinema-label" for="presentation_format_id">Định dạng trình chiếu *</label>
        <select id="presentation_format_id" name="presentation_format_id" class="cinema-input">
            <option value="">-- Chọn định dạng --</option>
            @if($editing && $showtime->presentationFormat && !$presentationFormats->contains('id', $showtime->presentationFormat->id))
                <option value="{{ $showtime->presentationFormat->id }}" data-historical-format selected disabled>
                    {{ $showtime->presentationFormat->code }} — {{ $showtime->presentationFormat->name }} (đã lưu trữ — hãy chọn định dạng đang hoạt động)
                </option>
            @endif
            @foreach($presentationFormats as $format)
                <option value="{{ $format->id }}" data-format-code="{{ $format->code }}" @selected($formatValue == $format->id)>
                    {{ $format->code }} — {{ $format->name }}
                </option>
            @endforeach
        </select>
        <p class="mt-2 text-xs app-muted" data-format-guidance>Chọn phim để xem các định dạng phim hỗ trợ.</p>
        @error('presentation_format_id')<p class="text-sm text-error mt-2">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="cinema-label" for="room_id">Phòng đang hoạt động *</label>
        <select id="room_id" name="room_id" class="cinema-input">
            <option value="">-- Chọn phòng --</option>
            @foreach($rooms as $room)
                @php
                    $layout = $editing && (int) $showtime->room_id === (int) $room->id
                        ? $showtime->roomLayout
                        : $room->latestPublishedLayout;
                @endphp
                <option value="{{ $room->id }}" data-cinema-id="{{ $room->cinema_id }}" data-timezone="{{ $room->cinema->timezone ?? $cinemaTimezone }}" data-layout-version="{{ $layout->version }}" data-cleaning-buffer="{{ $room->cleaning_buffer_minutes ?? $room->cinema->default_cleaning_buffer_minutes ?? $cleaningBufferMinutes }}" data-format-ids="{{ $room->presentationCapabilities->pluck('id')->implode(',') }}" @selected($roomValue == $room->id)>
                    {{ $room->code }} — {{ $room->name }} (sơ đồ phiên bản {{ $layout->version }})
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

    <div class="rounded-2xl border app-border p-4 md:col-span-2">
        <p class="font-bold app-text">Giá vé được tính từ Bảng giá vé</p>
        <p class="mt-1 text-sm app-muted">Máy chủ áp dụng giá cơ bản và các phụ thu theo chi nhánh, phòng, loại ghế, khung giờ và ngày chiếu. Giá không thể nhập thủ công tại suất chiếu.</p>
        @if($editing)<p class="mt-2 text-sm text-brand-start">Giá tham chiếu hiện tại: thường {{ number_format((int) $showtime->price, 0, ',', '.') }} VNĐ · VIP {{ number_format((int) $showtime->vip_price, 0, ',', '.') }} VNĐ</p>@endif
        <p id="showtime-price-preview" class="mt-2 text-sm font-bold text-brand-start" aria-live="polite">Chọn phòng và thời gian để xem giá theo loại ghế.</p>
    </div>

    <input type="hidden" name="status" value="active">
    @error('status')<p class="text-sm text-error md:col-span-2">{{ $message }}</p>@enderror
</div>

<section
    id="schedule-preview"
    class="rounded-2xl border app-border p-5 bg-white/5"
    data-showtime-schedule-preview
    data-endpoint="{{ route('admin.showtimes.preview') }}"
    data-showtime-id="{{ $editing ? $showtime->id : '' }}"
    data-timezone="{{ $cinemaTimezone }}"
>
    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
        <h2 class="font-extrabold app-text">Kiểm tra khung giờ vận hành</h2>
        <span class="text-xs app-muted">Múi giờ chi nhánh: <span data-schedule-timezone>{{ $cinemaTimezone }}</span></span>
    </div>
    <p class="mb-4 text-sm app-muted" data-schedule-preview-state aria-live="polite">Chọn đủ phim, định dạng, phòng, ngày và giờ bắt đầu để kiểm tra khung giờ.</p>
    <p class="mb-4 text-sm app-muted">Định dạng đã xác thực: <strong class="app-text" data-schedule-format>--</strong></p>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
        <div><span class="app-muted block">Bắt đầu</span><strong data-schedule-start class="app-text">--</strong></div>
        <div><span class="app-muted block">Kết thúc phim</span><strong data-schedule-end class="app-text">--</strong></div>
        <div><span class="app-muted block">Vệ sinh</span><strong data-schedule-cleaning class="app-text">--</strong></div>
        <div><span class="app-muted block">Phòng sẵn sàng</span><strong data-schedule-ready class="text-brand-start">--</strong></div>
    </div>
    <div data-schedule-conflict hidden class="mt-4 rounded-xl border border-error/30 bg-error/10 p-4 text-sm">
        <p class="font-bold text-error">Suất đang chiếm phòng</p>
        <p data-conflict-movie class="mt-1 app-text"></p>
        <p data-conflict-window class="mt-1 app-muted"></p>
        <p data-conflict-ready class="mt-1 app-muted"></p>
    </div>
    <p class="text-xs app-muted mt-4">Kết quả do máy chủ tính theo múi giờ chi nhánh và chỉ có giá trị tại thời điểm kiểm tra. Hệ thống sẽ kiểm tra lại khi lưu.</p>
</section>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const room = document.getElementById('room_id');
    const movie = document.getElementById('movie_id');
    const format = document.getElementById('presentation_format_id');
    const date = document.getElementById('show_date');
    const time = document.getElementById('show_time');
    const formatGuidance = document.querySelector('[data-format-guidance]');

    function idsFor(option) {
        return new Set((option?.dataset.formatIds || '').split(',').filter(Boolean));
    }

    function refreshFormatChoices(preserveHistorical = false) {
        const supported = idsFor(movie.selectedOptions[0]);
        Array.from(format.options).forEach(option => {
            if (!option.value) return;
            option.disabled = !supported.has(option.value);
        });
        const previousFormat = format.value;
        if (format.value && format.selectedOptions[0]?.disabled
            && !(preserveHistorical && format.selectedOptions[0]?.dataset.historicalFormat !== undefined)) {
            format.value = '';
        }
        formatGuidance.textContent = format.selectedOptions[0]?.dataset.historicalFormat !== undefined
            ? 'Định dạng hiện tại đã lưu trữ. Hãy chọn một định dạng đang hoạt động và tương thích trước khi thay đổi cấu trúc suất chiếu.'
            : movie.value
                ? 'Danh sách được lọc theo định dạng phim hỗ trợ; máy chủ sẽ kiểm tra lại khi xem trước và lưu.'
                : 'Chọn phim để xem các định dạng phim hỗ trợ.';
        refreshRoomChoices();
        if (previousFormat !== format.value) format.dispatchEvent(new Event('change'));
    }

    function refreshRoomChoices() {
        const selectedFormat = format.value;
        Array.from(room.options).forEach(option => {
            if (!option.value) return;
            option.disabled = !selectedFormat || !idsFor(option).has(selectedFormat);
        });
        const previousRoom = room.value;
        if (room.value && room.selectedOptions[0]?.disabled) room.value = '';
        if (previousRoom !== room.value) room.dispatchEvent(new Event('change'));
    }

    let pricingRequest = 0;
    async function refreshPricePreview() {
        const option = room.selectedOptions[0];
        const output = document.getElementById('showtime-price-preview');
        if (!option?.value || !date.value || !time.value) { output.textContent = 'Chọn phòng và thời gian để xem giá theo loại ghế.'; return; }
        const requestId = ++pricingRequest;
        output.textContent = 'Đang tính giá từ máy chủ…';
        const prices = [];
        for (const type of ['normal','vip','couple']) {
            const csrfToken = document.querySelector('input[name="_token"]')?.value
                ?? document.querySelector('meta[name="csrf-token"]')?.content;
            if (!csrfToken) { output.textContent = 'Không thể xác thực yêu cầu xem trước giá.'; return; }
            const body = new FormData(); body.append('_token', csrfToken);
            body.append('cinema_id', option.dataset.cinemaId); body.append('room_id', option.value);
            body.append('show_date', date.value); body.append('show_time', time.value); body.append('seat_type', type);
            const response = await fetch(@json(route('admin.pricing-rules.preview')), {method:'POST',headers:{Accept:'application/json'},body});
            const data = await response.json();
            if (requestId !== pricingRequest) return;
            if (!response.ok) { output.textContent = Object.values(data.errors || {}).flat()[0] || 'Chưa có cấu hình giá phù hợp.'; return; }
            prices.push(`${type}: ${Number(data.final_amount).toLocaleString('vi-VN')} VNĐ`);
        }
        output.textContent = prices.join(' · ');
    }

    [room, date, time].forEach(input => input.addEventListener('change', refreshPricePreview));
    movie.addEventListener('change', () => refreshFormatChoices(false));
    format.addEventListener('change', refreshRoomChoices);
    refreshFormatChoices(true);
    refreshPricePreview();
});
</script>
@endpush
