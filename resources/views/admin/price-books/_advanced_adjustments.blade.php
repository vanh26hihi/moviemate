<section class="mt-6" aria-labelledby="advanced-rules-title">
    <details class="cinema-card overflow-hidden" @if($errors->has('adjustment')) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-5 sm:p-6">
            <span>
                <span class="block text-xs font-bold uppercase tracking-[0.16em] text-brand-start">Bước 2 · Không bắt buộc</span>
                <span id="advanced-rules-title" class="mt-1 block text-xl font-extrabold app-heading">Khi nào giá vé thay đổi?</span>
                <span class="mt-1 block text-sm app-muted">{{ $contextAdjustments->count() }} phụ thu hoặc giảm giá theo thời gian, phòng và chi nhánh.</span>
            </span>
            <span class="status-badge border app-border">Mở thiết lập nâng cao</span>
        </summary>

        <div class="border-t app-border p-5 sm:p-6">
            <div class="grid gap-3 lg:grid-cols-2">
                @forelse($contextAdjustments as $adjustment)
                    @php
                        $target = match($adjustment->dimension) {
                            'room_type' => $adjustment->roomType?->name,
                            'cinema' => $adjustment->cinema?->name,
                            'room' => ($adjustment->room?->code ? $adjustment->room->code.' — ' : '').$adjustment->room?->name,
                            'time_window' => substr($adjustment->time_start, 0, 5).'–'.substr($adjustment->time_end, 0, 5).' · theo giờ bắt đầu suất chiếu',
                            'weekend' => collect($adjustment->weekend_days)->map(fn($day) => $weekdayLabels[$day] ?? $day)->join(', '),
                            'holiday' => $adjustment->holiday_date_from?->format('d/m/Y').'–'.$adjustment->holiday_date_until?->copy()->subDay()->format('d/m/Y'),
                        };
                    @endphp
                    <article class="rounded-2xl border app-border p-4" data-price-rule="{{ $adjustment->dimension }}">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.14em] text-brand-start">{{ $dimensionLabels[$adjustment->dimension] }}</p>
                                <h3 class="mt-1 font-extrabold app-heading">{{ $adjustment->label }}</h3>
                                <p class="mt-1 text-sm app-muted">{{ $target }}</p>
                            </div>
                            <p class="shrink-0 text-lg font-black tabular-nums {{ $adjustment->amount_vnd > 0 ? 'text-warning' : 'text-success' }}">{{ $adjustment->amount_vnd > 0 ? '+' : '−' }}{{ number_format(abs($adjustment->amount_vnd), 0, ',', '.') }} ₫</p>
                        </div>

                        @if($canManagePriceBook && $version->status === 'draft')
                            <details class="mt-4 rounded-xl bg-black/10 p-3">
                                <summary class="cursor-pointer font-bold text-brand-start">Sửa phụ thu</summary>
                                <form method="POST" action="{{ route('admin.price-books.versions.adjustments.update', [$version, $adjustment]) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="dimension" value="{{ $adjustment->dimension }}">
                                    <div><label class="admin-label">Tên dễ nhớ</label><input class="admin-input" name="label" value="{{ $adjustment->label }}" required></div>
                                    <div><label class="admin-label">Mức tăng/giảm (VND)</label><input class="admin-input" type="number" name="amount_vnd" value="{{ $adjustment->amount_vnd }}" required></div>
                                    @if($adjustment->room_type_id)
                                        <input type="hidden" name="room_type_id" value="{{ $adjustment->room_type_id }}">
                                    @elseif($adjustment->cinema_id)
                                        <input type="hidden" name="cinema_id" value="{{ $adjustment->cinema_id }}">
                                    @elseif($adjustment->room_id)
                                        <input type="hidden" name="room_id" value="{{ $adjustment->room_id }}">
                                    @elseif($adjustment->dimension === 'time_window')
                                        <input type="hidden" name="time_start" value="{{ substr($adjustment->time_start, 0, 5) }}"><input type="hidden" name="time_end" value="{{ substr($adjustment->time_end, 0, 5) }}">
                                    @elseif($adjustment->dimension === 'holiday')
                                        <input type="hidden" name="holiday_date_from" value="{{ $adjustment->holiday_date_from?->format('Y-m-d') }}"><input type="hidden" name="holiday_date_until" value="{{ $adjustment->holiday_date_until?->format('Y-m-d') }}">
                                    @elseif($adjustment->dimension === 'weekend')
                                        @foreach($adjustment->weekend_days as $day)<input type="hidden" name="weekend_days[]" value="{{ $day }}">@endforeach
                                    @endif
                                    <div class="sm:col-span-2"><button class="admin-btn-secondary">Lưu thay đổi</button></div>
                                </form>
                                <form method="POST" action="{{ route('admin.price-books.versions.adjustments.destroy', [$version, $adjustment]) }}" class="mt-2">
                                    @csrf @method('DELETE')
                                    <button class="text-sm font-bold text-error" onclick="return confirm('Xóa phụ thu này khỏi bảng giá đang soạn?')">Xóa phụ thu</button>
                                </form>
                            </details>
                        @endif
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed app-border p-6 text-center app-muted lg:col-span-2">Không có phụ thu nâng cao. Giá bán theo loại vé sẽ được dùng trực tiếp.</div>
                @endforelse
            </div>

            @if($canManagePriceBook && $version->status === 'draft')
                <details class="mt-5 rounded-2xl border border-brand-start/30 bg-brand-start/5 p-4" @if(old('dimension')) open @endif>
                    <summary class="cursor-pointer font-extrabold text-brand-start">+ Thêm thời điểm hoặc nơi áp dụng giá khác</summary>
                    <form id="adjustment-form" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4" method="POST" action="{{ route('admin.price-books.versions.adjustments.store', $version) }}">
                        @csrf
                        <div>
                            <label class="admin-label" for="adjustment-dimension">Giá thay đổi khi</label>
                            <select class="admin-input" id="adjustment-dimension" name="dimension" required>
                                @foreach(collect($dimensionLabels)->except('seat_type') as $value => $label)<option value="{{ $value }}" @selected(old('dimension') === $value)>{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div><label class="admin-label" for="adjustment-label">Tên dễ nhớ</label><input class="admin-input" id="adjustment-label" name="label" maxlength="255" required value="{{ old('label') }}" placeholder="Ví dụ: Phụ thu suất tối"></div>
                        <div><label class="admin-label" for="adjustment-amount">Tăng hoặc giảm bao nhiêu?</label><input class="admin-input" id="adjustment-amount" type="number" name="amount_vnd" required value="{{ old('amount_vnd') }}" placeholder="15000"><p class="mt-1 text-xs app-muted">Số dương để tăng, số âm để giảm.</p></div>
                        <div data-adjustment-field="room_type"><label class="admin-label" for="adjustment-room-type">Loại phòng</label><select class="admin-input" id="adjustment-room-type" name="room_type_id">@foreach($roomTypes as $roomType)<option value="{{ $roomType->id }}">{{ $roomType->name }}</option>@endforeach</select></div>
                        <div data-adjustment-field="cinema"><label class="admin-label" for="adjustment-cinema">Chi nhánh</label><select class="admin-input" id="adjustment-cinema" name="cinema_id">@foreach($previewCinemas as $cinema)<option value="{{ $cinema->id }}">{{ $cinema->name }}</option>@endforeach</select></div>
                        <div data-adjustment-field="room"><label class="admin-label" for="adjustment-room">Phòng</label><select class="admin-input" id="adjustment-room" name="room_id">@foreach($previewRooms->groupBy('cinema_id') as $rooms)<optgroup label="{{ $rooms->first()->cinema?->name }}">@foreach($rooms as $room)<option value="{{ $room->id }}">{{ $room->code }} — {{ $room->name }}</option>@endforeach</optgroup>@endforeach</select></div>
                        <div data-adjustment-field="time_window"><label class="admin-label" for="adjustment-time-start">Từ giờ</label><input class="admin-input" id="adjustment-time-start" type="time" name="time_start"><p class="mt-1 text-xs app-muted">Có thể đặt 22:00–02:00.</p></div>
                        <div data-adjustment-field="time_window"><label class="admin-label" for="adjustment-time-end">Đến trước giờ</label><input class="admin-input" id="adjustment-time-end" type="time" name="time_end"></div>
                        <fieldset data-adjustment-field="weekend" class="md:col-span-2"><legend class="admin-label">Chọn ngày</legend><div class="flex flex-wrap gap-3">@foreach($weekdayLabels as $day => $label)<label class="flex items-center gap-2"><input type="checkbox" name="weekend_days[]" value="{{ $day }}">{{ $label }}</label>@endforeach</div></fieldset>
                        <div data-adjustment-field="holiday"><label class="admin-label" for="holiday-from">Từ ngày</label><input class="admin-input" id="holiday-from" type="date" name="holiday_date_from"><p class="mt-1 text-xs app-muted">Giá ngày lễ thay thế giá cuối tuần.</p></div>
                        <div data-adjustment-field="holiday"><label class="admin-label" for="holiday-until">Đến trước ngày</label><input class="admin-input" id="holiday-until" type="date" name="holiday_date_until"><p class="mt-1 text-xs app-muted">Ngày này không còn được tính là ngày lễ.</p></div>
                        <div class="md:col-span-2 xl:col-span-4"><button class="admin-btn-primary">Thêm phụ thu</button></div>
                    </form>
                </details>
            @endif
        </div>
    </details>
</section>

@if($canManagePriceBook && $version->status === 'draft')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dimension = document.getElementById('adjustment-dimension');
    if (!dimension) return;
    const sync = () => document.querySelectorAll('[data-adjustment-field]').forEach((container) => {
        const active = container.dataset.adjustmentField === dimension.value;
        container.hidden = !active;
        container.querySelectorAll('input,select').forEach((input) => input.disabled = !active);
    });
    dimension.addEventListener('change', sync);
    sync();
});
</script>
@endif
