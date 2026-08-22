<section class="cinema-card p-5 sm:p-6" aria-labelledby="price-preview-title">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 id="price-preview-title" class="text-xl font-extrabold app-heading">Xem trước giá</h2>
            <p class="mt-1 text-sm app-muted">Máy chủ dùng bảng giá đã phát hành áp dụng cho giờ bắt đầu suất chiếu tại địa phương của chi nhánh. Khuyến mãi không thuộc phép tính này.</p>
        </div>
        <span class="status-badge border app-border">Định dạng trình chiếu không ảnh hưởng giá</span>
    </div>

    <form method="POST" action="{{ route('admin.price-books.preview') }}" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @csrf
        <div>
            <label class="admin-label" for="preview-cinema">Chi nhánh</label>
            @if($canManagePriceBook)
                <select class="admin-input" id="preview-cinema" name="cinema_id" required>
                    <option value="">Chọn chi nhánh</option>
                    @foreach($previewCinemas as $cinema)
                        <option value="{{ $cinema->id }}" @selected((string) old('cinema_id', $preview['cinema']->id ?? '') === (string) $cinema->id)>{{ $cinema->name }}</option>
                    @endforeach
                </select>
            @else
                @php($lockedCinema = $previewCinemas->first())
                <input type="hidden" id="preview-cinema" name="cinema_id" value="{{ $lockedCinema?->id }}">
                <p class="admin-input" aria-label="Chi nhánh đang khóa">{{ $lockedCinema?->name ?? 'Chưa có chi nhánh được phân công' }} · Đã khóa theo ngữ cảnh</p>
            @endif
            @error('cinema_id')<p class="mt-1 text-sm text-error" role="alert">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="admin-label" for="preview-room">Phòng</label>
            <select class="admin-input" id="preview-room" name="room_id" required>
                <option value="">Chọn phòng</option>
                @foreach($previewRooms as $room)
                    <option value="{{ $room->id }}" data-cinema-id="{{ $room->cinema_id }}" @selected((string) old('room_id', $preview['room']->id ?? '') === (string) $room->id)>{{ $room->cinema?->name }} · {{ $room->code }} — {{ $room->name }}</option>
                @endforeach
            </select>
            @error('room_id')<p class="mt-1 text-sm text-error" role="alert">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="admin-label" for="preview-seat-type">Loại ghế</label>
            <select class="admin-input" id="preview-seat-type" name="seat_type_id" required>
                <option value="">Chọn loại ghế</option>
                @foreach($seatTypes as $seatType)
                    <option value="{{ $seatType->id }}" @selected((string) old('seat_type_id', $preview['seatType']->id ?? '') === (string) $seatType->id)>{{ $seatType->name }}{{ $seatType->is_pair ? ' · một cặp ghế đôi' : '' }}</option>
                @endforeach
            </select>
            @error('seat_type_id')<p class="mt-1 text-sm text-error" role="alert">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="admin-label" for="preview-local-start">Bắt đầu suất chiếu (giờ địa phương)</label>
            <input class="admin-input" id="preview-local-start" type="datetime-local" name="showtime_local_start" required value="{{ old('showtime_local_start', isset($preview['localStart']) ? $preview['localStart']->format('Y-m-d\TH:i') : '') }}">
            @error('showtime_local_start')<p class="mt-1 text-sm text-error" role="alert">{{ $message }}</p>@enderror
        </div>
        <div class="md:col-span-2 xl:col-span-4 flex flex-wrap items-center gap-3">
            <button class="admin-btn-primary" type="submit">Tính giá bằng bảng giá đã phát hành</button>
            <p class="text-xs app-muted">Bản nháp không được tính bằng một calculator riêng; hãy dùng kiểm tra phát hành để xác thực bản nháp.</p>
        </div>
    </form>

    @error('preview')<div class="mt-4 rounded-xl border border-error/30 bg-error/10 p-4 text-sm text-error" role="alert">{{ $message }}</div>@enderror

    @if($preview)
        @php($resolved = $preview['price'])
        <div class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.7fr)]" aria-live="polite">
            <div class="overflow-hidden rounded-2xl border app-border">
                <div class="border-b app-border p-4">
                    <h3 class="font-extrabold app-heading">Giá vé đã tính</h3>
                    <p class="mt-1 text-sm app-muted">Phiên bản v{{ $resolved->versionNumber }} · {{ $preview['cinema']->name }} · {{ $preview['room']->code }} · {{ $preview['seatType']->name }}</p>
                </div>
                <dl class="divide-y app-border">
                    <div class="flex justify-between gap-4 p-4" data-preview-dimension="base"><dt>Giá cơ sở toàn chuỗi</dt><dd class="font-bold tabular-nums">{{ number_format($resolved->basePriceVnd, 0, ',', '.') }} ₫</dd></div>
                    @foreach($resolved->adjustments as $component)
                        @php($componentLabel = ['seat_type'=>'Loại ghế','room_type'=>'Loại phòng','time_window'=>'Khung giờ','weekend'=>'Cuối tuần','holiday'=>'Ngày lễ','cinema'=>'Chi nhánh','room'=>'Phòng'][$component['dimension']] ?? $component['label'])
                        <div class="flex justify-between gap-4 p-4" data-preview-dimension="{{ $component['dimension'] }}"><dt>{{ $componentLabel }} <span class="block text-xs app-muted">{{ $component['label'] }}</span></dt><dd class="font-bold tabular-nums">{{ $component['amount_vnd'] > 0 ? '+' : '−' }}{{ number_format(abs($component['amount_vnd']), 0, ',', '.') }} ₫</dd></div>
                    @endforeach
                    <div class="flex justify-between gap-4 bg-brand-start/10 p-4 text-lg"><dt class="font-extrabold">{{ $preview['seatType']->is_pair ? 'Giá cho một cặp ghế đôi' : 'Giá vé đã tính' }}</dt><dd class="font-black tabular-nums">{{ number_format($resolved->finalUnitAmountVnd, 0, ',', '.') }} ₫</dd></div>
                </dl>
            </div>
            <dl class="space-y-3 rounded-2xl border app-border p-4 text-sm">
                <div><dt class="app-muted">Phiên bản bảng giá</dt><dd class="font-bold">v{{ $resolved->versionNumber }}</dd></div>
                <div><dt class="app-muted">Chi nhánh / múi giờ</dt><dd class="font-bold">{{ $preview['cinema']->name }} · {{ $preview['cinema']->timezone }}</dd></div>
                <div><dt class="app-muted">Giờ bắt đầu địa phương</dt><dd class="font-bold">{{ $preview['localStart']->format('d/m/Y H:i') }}</dd></div>
                <div><dt class="app-muted">Đơn vị tính giá</dt><dd class="font-bold">{{ $preview['seatType']->is_pair ? 'Một cặp ghế đôi (tính một lần)' : 'Một ghế vật lý' }}</dd></div>
            </dl>
        </div>
    @endif
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const cinema = document.getElementById('preview-cinema');
    const room = document.getElementById('preview-room');
    const filterRooms = () => {
        const selectedCinema = cinema?.value || '';
        room?.querySelectorAll('option[data-cinema-id]').forEach((option) => {
            option.hidden = selectedCinema !== '' && option.dataset.cinemaId !== selectedCinema;
            if (option.hidden && option.selected) room.value = '';
        });
    };
    cinema?.addEventListener('change', filterRooms);
    filterRooms();
});
</script>
