@php($editing = isset($room))

<div class="rounded-2xl border app-border app-card-soft p-4">
    <label for="cinema_id" class="text-sm app-muted">{{ __('rooms.fields.cinema') }}</label>
    @if($editing || $cinema)
        <strong class="block app-text">{{ $cinema->name }}</strong>
        <input type="hidden" name="cinema_id" value="{{ $cinema->id }}">
    @else
        <select id="cinema_id" name="cinema_id" required class="cinema-input mt-2">
            @foreach($cinemas as $availableCinema)<option value="{{ $availableCinema->id }}" @selected(old('cinema_id') == $availableCinema->id)>{{ $availableCinema->name }}</option>@endforeach
        </select>
    @endif
</div>

<div>
    <label for="cleaning_buffer_minutes" class="cinema-label">Vệ sinh phòng (phút)</label>
    <input id="cleaning_buffer_minutes" type="number" min="0" max="180" name="cleaning_buffer_minutes" value="{{ old('cleaning_buffer_minutes', $room->cleaning_buffer_minutes ?? '') }}" class="cinema-input" placeholder="Để trống để dùng mặc định chi nhánh">
    <p class="mt-1 text-xs app-muted">Để trống để kế thừa thời gian vệ sinh mặc định của chi nhánh.</p>
    @error('cleaning_buffer_minutes')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
</div>

<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <div>
        <label for="code" class="cinema-label">{{ __('rooms.fields.code') }} <span aria-hidden="true">*</span></label>
        <input id="code" type="text" name="code" value="{{ old('code', $room->code ?? '') }}" required maxlength="32" class="cinema-input @error('code') !border-error @enderror" placeholder="Ví dụ: P04" aria-describedby="code-help code-error">
        <p id="code-help" class="mt-1 text-xs app-muted">Mã dùng để nhận diện phòng và không trùng trong cùng cơ sở.</p>
        @error('code')<p id="code-error" class="mt-1 text-sm font-semibold text-error" role="alert"><i class="ph-fill ph-warning-circle mr-1" aria-hidden="true"></i>{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="name" class="cinema-label">{{ __('rooms.fields.name') }} <span aria-hidden="true">*</span></label>
        <input id="name" type="text" name="name" value="{{ old('name', $room->name ?? '') }}" required maxlength="255" class="cinema-input @error('name') !border-error @enderror" placeholder="Ví dụ: Phòng 1" aria-describedby="name-error">
        @error('name')<p id="name-error" class="mt-1 text-sm font-semibold text-error" role="alert"><i class="ph-fill ph-warning-circle mr-1" aria-hidden="true"></i>{{ $message }}</p>@enderror
    </div>
</div>

<div class="rounded-2xl border app-border app-card-soft p-4">
    <div>
        <p class="font-bold app-text">Kích thước phòng</p>
        <p class="mt-1 text-sm app-muted">Kích thước mặt bằng chữ nhật phục vụ quản lý hành chính; không quy đổi sang tỷ lệ ô của lưới ghế.</p>
    </div>
    <div class="mt-4 grid grid-cols-1 gap-5 md:grid-cols-2">
        <div>
            <label for="width_m" class="cinema-label">Chiều rộng phòng (m)</label>
            <input id="width_m" type="number" name="width_m" min="0.001" max="3000000" step="0.001" inputmode="decimal" value="{{ old('width_m', $editing ? $room->widthMetersForInput() : '') }}" class="cinema-input @error('width_mm') !border-error @enderror" placeholder="Ví dụ: 7.5" aria-describedby="width-help width-error">
            <p id="width-help" class="mt-1 text-xs app-muted">Dùng dấu chấm cho phần thập phân, tối đa 3 chữ số.</p>
            @error('width_mm')<p id="width-error" class="mt-1 text-sm font-semibold text-error" role="alert"><i class="ph-fill ph-warning-circle mr-1" aria-hidden="true"></i>{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="length_m" class="cinema-label">Chiều dài phòng (m)</label>
            <input id="length_m" type="number" name="length_m" min="0.001" max="3000000" step="0.001" inputmode="decimal" value="{{ old('length_m', $editing ? $room->lengthMetersForInput() : '') }}" class="cinema-input @error('length_mm') !border-error @enderror" placeholder="Ví dụ: 10" aria-describedby="length-help length-error">
            <p id="length-help" class="mt-1 text-xs app-muted">Phòng hoạt động cần đủ cả chiều rộng và chiều dài; phòng ngừng hoạt động có thể để trống cả hai.</p>
            @error('length_mm')<p id="length-error" class="mt-1 text-sm font-semibold text-error" role="alert"><i class="ph-fill ph-warning-circle mr-1" aria-hidden="true"></i>{{ $message }}</p>@enderror
        </div>
    </div>
</div>

<div class="grid grid-cols-1 gap-5 md:grid-cols-2">
    <div>
        <label for="room_type" class="cinema-label">{{ __('rooms.fields.type') }} <span aria-hidden="true">*</span></label>
        <x-admin.room-type-select :room-types="$roomTypes" :selected="old('room_type', $room->room_type ?? $roomTypes->first()?->code)" :can-create="auth()->user()->hasPermission('room_types.manage')" required />
        @error('room_type')<p id="room-type-error" class="mt-1 text-sm font-semibold text-error" role="alert"><i class="ph-fill ph-warning-circle mr-1" aria-hidden="true"></i>{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="status" class="cinema-label">{{ __('rooms.fields.status') }} <span aria-hidden="true">*</span></label>
        <select id="status" name="status" required class="cinema-input @error('status') !border-error @enderror" aria-describedby="status-help status-error">
            <option value="active" @selected(old('status', $room->status ?? 'active') === 'active')>{{ \App\Support\StatusLabel::for('room', 'active') }}</option>
            <option value="inactive" @selected(old('status', $room->status ?? 'active') === 'inactive')>{{ \App\Support\StatusLabel::for('room', 'inactive') }}</option>
        </select>
        <p id="status-help" class="mt-1 text-xs app-muted">Không thể ngừng phòng đang có suất chiếu sắp tới.</p>
        @error('status')<p id="status-error" class="mt-1 text-sm font-semibold text-error" role="alert"><i class="ph-fill ph-warning-circle mr-1" aria-hidden="true"></i>{{ $message }}</p>@enderror
    </div>
</div>

@include('admin.rooms._presentation-capabilities')

<div class="rounded-2xl border border-info/20 bg-info/5 p-4 text-sm app-muted">
    <p class="font-bold app-text">{{ __('rooms.fields.layout') }} và sức chứa vật lý</p>
    <p class="mt-1">Sức chứa vật lý là số ô ghế trong sơ đồ đã phát hành. Ghế bảo trì vẫn là một vị trí vật lý; lưới logic không có đơn vị mét.</p>
</div>

@if(! $editing && isset($templates) && $templates->isNotEmpty() && auth()->user()->hasPermission('room_layouts.apply_template'))
<div class="rounded-2xl border app-border app-card-soft p-4 space-y-4">
    <div><p class="font-bold app-text">Khởi tạo từ mẫu sơ đồ</p><p class="text-sm app-muted">Tùy chọn. Hệ thống sao chép mẫu thành ghế và sơ đồ riêng của phòng rồi phát hành ngay.</p></div>
    <label class="cinema-label">Mẫu<select name="template_id" class="cinema-input"><option value="">Thiết kế sau</option>@foreach($templates as $template)<option value="{{ $template->id }}" @selected(old('template_id')==$template->id)>{{ $template->name }} · lưới {{ $template->rows }} hàng × {{ $template->columns }} cột logic{{ $template->room_type ? ' · '.($roomTypes->firstWhere('code', $template->room_type)?->name ?? $template->room_type) : '' }}</option>@endforeach</select></label>
    <label class="cinema-label">Tên sơ đồ<input name="layout_name" class="cinema-input" value="{{ old('layout_name') }}" placeholder="Ví dụ: Tiêu chuẩn 100 ghế – khai trương"></label>
    <label class="cinema-label">Ghi chú thay đổi<textarea name="change_note" class="cinema-input" rows="2">{{ old('change_note') }}</textarea></label>
    @error('template_id')<p class="text-error">{{ $message }}</p>@enderror @error('layout_name')<p class="text-error">{{ $message }}</p>@enderror
</div>
@endif

<div class="flex flex-col gap-3 pt-3 sm:flex-row">
    <button type="submit" class="btn-primary">{{ $editing ? __('rooms.actions.update') : __('rooms.actions.save') }}</button>
    <a href="{{ $editing ? route('admin.rooms.show', $room) : route('admin.rooms.index') }}" class="btn-secondary">{{ __('rooms.actions.cancel') }}</a>
</div>
