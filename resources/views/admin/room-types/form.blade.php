@extends('layouts.admin')

@section('title', ($roomType->exists ? 'Sửa' : 'Thêm').' loại phòng - MovieMate')
@section('page-title', $roomType->exists ? 'Sửa loại phòng' : 'Thêm loại phòng')

@section('content')
<form class="app-card mx-auto max-w-3xl space-y-5 rounded-2xl border app-border p-6" method="POST" action="{{ $roomType->exists ? route('admin.room-types.update', $roomType) : route('admin.room-types.store') }}">
    @csrf @if($roomType->exists) @method('PUT') @endif
    <div><label class="cinema-label" for="name">Tên hiển thị <span aria-hidden="true">*</span></label><input class="cinema-input" id="name" name="name" required maxlength="120" value="{{ old('name', $roomType->name) }}" placeholder="Ví dụ: ScreenX" data-validation-url="{{ route('admin.validation.field') }}" data-validation-rule="room-type.name" data-validation-record="{{ $roomType->exists ? $roomType->getKey() : '' }}">@error('name')<p class="text-error" role="alert">{{ $message }}</p>@enderror</div>
    <div><label class="cinema-label" for="code">Mã hệ thống <span aria-hidden="true">*</span></label><input class="cinema-input" id="code" name="code" required maxlength="40" pattern="[A-Za-z0-9]+(?:_[A-Za-z0-9]+)*" value="{{ old('code', $roomType->code) }}" placeholder="Ví dụ: SCREENX" data-validation-url="{{ route('admin.validation.field') }}" data-validation-rule="room-type.code" data-validation-record="{{ $roomType->exists ? $roomType->getKey() : '' }}" @readonly($roomType->exists && ($roomType->rooms_count || $roomType->pricing_rules_count))><p class="mt-1 text-xs app-muted">Chữ in hoa không dấu, số và dấu gạch dưới. Mã không thể đổi sau khi đã được sử dụng.</p>@error('code')<p class="text-error" role="alert">{{ $message }}</p>@enderror</div>
    <div><label class="cinema-label" for="description">Mô tả</label><textarea class="cinema-input" id="description" name="description" maxlength="500" rows="3">{{ old('description', $roomType->description) }}</textarea>@error('description')<p class="text-error" role="alert">{{ $message }}</p>@enderror</div>
    <div class="grid gap-4 md:grid-cols-2"><div><label class="cinema-label" for="sort_order">Thứ tự hiển thị</label><input class="cinema-input" id="sort_order" type="number" name="sort_order" value="{{ old('sort_order', $roomType->sort_order ?? 100) }}"></div><div><label class="cinema-label" for="is_active">Trạng thái</label><select class="cinema-input" id="is_active" name="is_active"><option value="1" @selected((string) old('is_active', (int) ($roomType->is_active ?? true)) === '1')>Đang sử dụng</option><option value="0" @selected((string) old('is_active', (int) ($roomType->is_active ?? true)) === '0')>Lưu trữ</option></select></div></div>
    <div class="flex gap-3"><button class="admin-btn-primary">{{ $roomType->exists ? 'Cập nhật' : 'Tạo loại phòng' }}</button><a class="admin-btn-secondary" href="{{ route('admin.room-types.index') }}">Quay lại</a></div>
</form>
@endsection
