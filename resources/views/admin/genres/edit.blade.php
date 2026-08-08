@extends('layouts.admin')

@section('title', 'Sửa thể loại')
@section('page-title', 'Sửa thể loại')
@section('suppress-global-validation-summary', '1')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Sửa thể loại: {{ $genre->name }}</h1>
        <p class="admin-page-subtitle">Cập nhật tên, slug và mô tả của thể loại.</p>
    </div>
    <a href="{{ route('admin.genres.index') }}" class="admin-btn-secondary">
        <i class="ph ph-arrow-left"></i>
        Quay lại
    </a>
</div>

<x-validation-summary class="mb-5 max-w-3xl" :errors="$errors" />

<form action="{{ route('admin.genres.update', $genre) }}" method="POST" class="admin-form-card max-w-3xl">
    @csrf
    @method('PUT')

    <div class="space-y-5">
        <div>
            <label class="admin-label">Tên *</label>
            <input type="text" name="name" value="{{ old('name', $genre->name) }}" required class="admin-input">
        </div>

        <div>
            <label class="admin-label">Đường dẫn rút gọn</label>
            <input type="text" name="slug" value="{{ old('slug', $genre->slug) }}" class="admin-input">
            <p class="admin-help">Để trống nếu muốn hệ thống tự tạo slug từ tên.</p>
        </div>

        <div>
            <label class="admin-label">Mô tả</label>
            <textarea name="description" rows="5" class="admin-input resize-y">{{ old('description', $genre->description) }}</textarea>
        </div>
    </div>

    <div class="mt-8 flex flex-col sm:flex-row justify-end gap-3 border-t app-border pt-5">
        <a href="{{ route('admin.genres.index') }}" class="admin-btn-secondary">Hủy</a>
        <button type="submit" class="admin-btn-primary">
            <i class="ph-bold ph-floppy-disk"></i>
            Cập nhật
        </button>
    </div>
</form>
@endsection
