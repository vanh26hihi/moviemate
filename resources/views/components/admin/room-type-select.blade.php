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

<div data-room-type-selector>
    <select id="{{ $id }}" name="{{ $name }}" {{ $required ? 'required' : '' }} {{ $attributes->class(['cinema-input'])->merge([
        'data-room-type-select' => $canCreate ? 'true' : null,
        'data-room-type-modal-id' => $canCreate ? $modalId : null,
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
    @if($canCreate)<p class="mt-2 text-sm font-semibold text-success" data-room-type-success aria-live="polite" hidden></p>@endif
</div>

@if($canCreate)
    @push('modals')
        <x-ui.modal :id="$modalId" title="Thêm loại phòng mới" :description-id="$modalId.'-description'" variant="admin-solid">
            <p id="{{ $modalId }}-description" class="text-sm leading-6 app-muted">Loại phòng được dùng chung cho phòng chiếu và quy tắc giá trên toàn hệ thống.</p>
            <form method="POST" action="{{ route('admin.room-types.store') }}" autocomplete="off" class="mt-6 space-y-5" data-room-type-create-form data-room-type-target="{{ $id }}">
                @csrf
                <div>
                    <label class="cinema-label" for="{{ $modalId }}-display-name">Tên loại phòng <span class="text-error" aria-hidden="true">*</span></label>
                    <input id="{{ $modalId }}-display-name" name="room_type_display_name" type="text" autocomplete="off" maxlength="120" required class="cinema-input" placeholder="Ví dụ: ScreenX" data-room-type-name data-modal-initial-focus aria-describedby="{{ $modalId }}-name-error">
                    <p id="{{ $modalId }}-name-error" class="mt-1 text-sm text-error" data-room-type-field-error="name" role="alert" hidden></p>
                </div>
                <div>
                    <label class="cinema-label" for="{{ $modalId }}-code">Mã loại phòng <span class="text-error" aria-hidden="true">*</span></label>
                    <input id="{{ $modalId }}-code" name="room_type_code" type="text" autocomplete="off" maxlength="40" required pattern="[A-Z0-9]+(?:_[A-Z0-9]+)*" class="cinema-input font-mono uppercase" placeholder="SCREENX" data-room-type-code aria-describedby="{{ $modalId }}-code-help {{ $modalId }}-code-error" spellcheck="false">
                    <p id="{{ $modalId }}-code-help" class="mt-1 text-xs leading-5 app-muted">Được gợi ý từ tên. Chỉ dùng chữ in hoa, số và dấu gạch dưới; mã đã sửa thủ công sẽ không bị ghi đè.</p>
                    <p id="{{ $modalId }}-code-error" class="mt-1 text-sm text-error" data-room-type-field-error="code" role="alert" hidden></p>
                </div>
                <div>
                    <label class="cinema-label" for="{{ $modalId }}-description-field">Mô tả</label>
                    <textarea id="{{ $modalId }}-description-field" name="room_type_description" autocomplete="off" maxlength="500" rows="3" class="cinema-input" data-room-type-description aria-describedby="{{ $modalId }}-description-error"></textarea>
                    <p id="{{ $modalId }}-description-error" class="mt-1 text-sm text-error" data-room-type-field-error="description" role="alert" hidden></p>
                </div>
                <div class="rounded-xl border border-error/30 bg-error/10 px-4 py-3 text-sm text-error" data-room-type-errors role="alert" aria-live="polite" hidden></div>
                <div class="flex flex-col-reverse gap-3 border-t app-border pt-5 sm:flex-row sm:justify-end">
                    <button type="button" class="btn-secondary w-full sm:w-auto" data-modal-close="{{ $modalId }}">Hủy</button>
                    <button type="submit" class="btn-primary w-full disabled:cursor-wait disabled:opacity-70 sm:w-auto" data-room-type-submit>
                        <span data-submit-idle>Thêm loại phòng</span><span data-submit-loading hidden>Đang thêm...</span>
                    </button>
                </div>
            </form>
        </x-ui.modal>
    @endpush
@endif
