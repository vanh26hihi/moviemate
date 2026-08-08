@props([
    'id' => 'room_type',
    'name' => 'room_type',
    'roomTypes',
    'selected' => null,
    'allowEmpty' => false,
    'emptyLabel' => 'Không giới hạn',
    'required' => false,
    'canCreate' => false,
])
@php
    $selectedValue = old($name, $selected);
    $modalId = 'add-room-type-'.$id;
    $selectedKnown = $selectedValue === null || $selectedValue === '' || $roomTypes->contains('code', $selectedValue);
@endphp

<select id="{{ $id }}" name="{{ $name }}" {{ $required ? 'required' : '' }} {{ $attributes->class(['cinema-input'])->merge([
    'data-room-type-select' => $canCreate ? 'true' : null,
    'data-room-type-modal' => $canCreate ? $modalId : null,
]) }}>
    @if($allowEmpty)<option value="">{{ $emptyLabel }}</option>@endif
    @foreach($roomTypes as $roomType)
        <option value="{{ $roomType->code }}" @selected($selectedValue === $roomType->code)>{{ $roomType->name }}@if(! $roomType->is_active) · Đã ngừng sử dụng @endif</option>
    @endforeach
    @if(! $selectedKnown)
        <option value="{{ $selectedValue }}" selected>{{ $selectedValue }} · Dữ liệu lịch sử</option>
    @endif
    @if($canCreate)
        <option disabled>────────────────</option>
        <option value="__create_room_type__">+ Thêm loại phòng mới</option>
    @endif
</select>

@if($canCreate)
    <button type="button" class="sr-only" data-modal-open="{{ $modalId }}" data-room-type-modal-trigger="{{ $modalId }}" aria-controls="{{ $modalId }}">Thêm loại phòng mới</button>
    <x-ui.modal :id="$modalId" title="Thêm loại phòng mới" :description-id="$modalId.'-description'">
        <p id="{{ $modalId }}-description" class="app-muted">Loại phòng được dùng chung cho phòng chiếu và quy tắc giá trên toàn hệ thống.</p>
        <form method="POST" action="{{ route('admin.room-types.store') }}" class="mt-5 space-y-4" data-room-type-create-form data-room-type-target="{{ $id }}">
            @csrf
            <label class="cinema-label">Tên loại phòng <span aria-hidden="true">*</span>
                <input name="name" maxlength="120" required class="cinema-input mt-1" placeholder="Ví dụ: ScreenX" data-room-type-name data-modal-initial-focus>
            </label>
            <label class="cinema-label">Mã loại phòng <span aria-hidden="true">*</span>
                <input name="code" maxlength="40" required pattern="[A-Z0-9]+(?:_[A-Z0-9]+)*" class="cinema-input mt-1 font-mono" placeholder="SCREENX" data-room-type-code>
                <span class="mt-1 block text-xs app-muted">Mã ổn định, chỉ gồm chữ in hoa, số và dấu gạch dưới.</span>
            </label>
            <label class="cinema-label">Mô tả
                <textarea name="description" maxlength="500" rows="3" class="cinema-input mt-1"></textarea>
            </label>
            <div class="rounded-xl bg-error/10 px-4 py-3 text-sm text-error" data-room-type-errors role="alert" aria-live="polite" hidden></div>
            <div class="flex justify-end gap-3">
                <button type="button" class="btn-secondary" data-modal-close="{{ $modalId }}">Hủy</button>
                <button type="submit" class="btn-primary">Thêm loại phòng</button>
            </div>
        </form>
    </x-ui.modal>
@endif
