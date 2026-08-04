@extends('layouts.admin')

@section('title', 'Thêm thể loại')
@section('page-title', 'Thêm thể loại')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Thêm thể loại</h1>
        <p class="admin-page-subtitle">Tạo thể loại mới để gắn với phim trong hệ thống.</p>
    </div>
    <a href="{{ route('admin.genres.index') }}" class="admin-btn-secondary">
        <i class="ph ph-arrow-left"></i>
        Quay lại
    </a>
</div>

@if ($errors->any())
    <div class="mb-5 max-w-3xl rounded-2xl border border-error/30 bg-error/10 text-error px-4 py-3 text-sm">
        <ul class="list-disc list-inside">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('admin.genres.store') }}" method="POST" class="admin-form-card max-w-3xl">
    @csrf

    <div class="space-y-5">
        <div>
            <label class="admin-label">Tên *</label>
            <input type="text" name="name" value="{{ old('name') }}" required class="admin-input" placeholder="Ví dụ: Hành động">
        </div>

        <div>
            <label class="admin-label">Slug</label>
            <input type="text" name="slug" value="{{ old('slug') }}" class="admin-input" placeholder="Để trống để tự tạo">
            <p class="admin-help">Slug dùng cho URL và lọc dữ liệu.</p>
        </div>

        <div>
            <label class="admin-label">Mô tả</label>
            <textarea name="description" rows="5" class="admin-input resize-y" placeholder="Mô tả ngắn về thể loại...">{{ old('description') }}</textarea>
        </div>
    </div>

    <div class="mt-8 flex flex-col sm:flex-row justify-end gap-3 border-t app-border pt-5">
        <a href="{{ route('admin.genres.index') }}" class="admin-btn-secondary">Hủy</a>
        <button type="submit" class="admin-btn-primary">
            <i class="ph-bold ph-floppy-disk"></i>
            Lưu
        </button>
    </div>
</form>
@endsection
