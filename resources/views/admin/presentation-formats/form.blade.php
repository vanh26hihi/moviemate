@extends('layouts.admin')

@section('title', ($presentationFormat->exists ? 'Sửa' : 'Thêm').' định dạng trình chiếu - MovieMate')
@section('page-title', $presentationFormat->exists ? 'Sửa định dạng trình chiếu' : 'Thêm định dạng trình chiếu')

@section('content')
<div class="admin-page-header"><div><h1 class="admin-page-title">{{ $presentationFormat->exists ? 'Cập nhật '.$presentationFormat->name : 'Tạo định dạng trình chiếu' }}</h1><p class="admin-page-subtitle">Danh mục toàn hệ thống; không gắn với một chi nhánh cụ thể.</p></div></div>

<x-validation-summary class="mb-5" :errors="$errors" />

<form class="admin-form-card mx-auto max-w-3xl space-y-5" method="POST" action="{{ $presentationFormat->exists ? route('admin.presentation-formats.update', $presentationFormat) : route('admin.presentation-formats.store') }}">
    @csrf
    @if($presentationFormat->exists) @method('PUT') @endif
    <div class="grid gap-5 md:grid-cols-2">
        <label><span class="admin-label">Mã định dạng *</span><input class="admin-input" name="code" required maxlength="40" pattern="[A-Za-z0-9]+(?:_[A-Za-z0-9]+)*" value="{{ old('code', $presentationFormat->code) }}" placeholder="Ví dụ: 4DX" data-validation-url="{{ route('admin.validation.field') }}" data-validation-rule="presentation-format.code" data-validation-record="{{ $presentationFormat->exists ? $presentationFormat->getKey() : '' }}"><span class="admin-help">Mã được chuẩn hóa viết hoa và không thể đổi sau khi đã được sử dụng.</span></label>
        <label><span class="admin-label">Tên định dạng *</span><input class="admin-input" name="name" required maxlength="120" value="{{ old('name', $presentationFormat->name) }}" placeholder="Ví dụ: 4DX" data-validation-url="{{ route('admin.validation.field') }}" data-validation-rule="presentation-format.name" data-validation-record="{{ $presentationFormat->exists ? $presentationFormat->getKey() : '' }}"></label>
    </div>
    <label><span class="admin-label">Mô tả</span><textarea class="admin-input" name="description" rows="4" maxlength="2000">{{ old('description', $presentationFormat->description) }}</textarea></label>
    <label><span class="admin-label">Thứ tự</span><input class="admin-input" type="number" min="0" max="4294967295" name="sort_order" value="{{ old('sort_order', $presentationFormat->sort_order ?? 0) }}"></label>
    @if($presentationFormat->exists)<div class="rounded-xl border app-border app-card-soft p-4 text-sm app-muted">Trạng thái: <strong class="app-text">{{ $presentationFormat->status_label }}</strong>. Lưu trữ được thực hiện bằng hành động riêng trên danh sách.</div>@endif
    <div class="flex gap-3"><button class="admin-btn-primary">{{ $presentationFormat->exists ? 'Cập nhật' : 'Tạo định dạng' }}</button><a class="admin-btn-secondary" href="{{ route('admin.presentation-formats.index') }}">Quay lại</a></div>
</form>
@endsection
