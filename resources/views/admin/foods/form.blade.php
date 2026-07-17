@extends('layouts.admin')

@section('title', $food->exists ? 'Sửa món ăn - MovieMate Admin' : 'Thêm món ăn - MovieMate Admin')
@section('page-title', $food->exists ? 'Sửa món ăn' : 'Thêm món ăn')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">{{ $food->exists ? 'Sửa món ăn' : 'Thêm món ăn' }}</h1>
        <p class="admin-page-subtitle">{{ $food->exists ? 'Cập nhật thông tin món ăn.' : 'Tạo món ăn mới để khách hàng đặt tại rạp.' }}</p>
    </div>
    <a href="{{ route('admin.foods.index') }}" class="admin-btn-secondary">
        <i class="ph ph-arrow-left"></i>
        Quay lại danh sách
    </a>
</div>

@if ($errors->any())
    <div class="mb-5 rounded-2xl border border-error/30 bg-error/10 text-error px-4 py-3 text-sm">
        <ul class="list-disc list-inside">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ $food->exists ? route('admin.foods.update', $food) : route('admin.foods.store') }}" method="POST" enctype="multipart/form-data" class="admin-form-card">
    @csrf
    @if($food->exists)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-8 space-y-5">
            <div>
                <label class="admin-label">Tên món *</label>
                <input type="text" name="name" value="{{ old('name', $food->name) }}" required class="admin-input" placeholder="Ví dụ: Bắp rang bơ" />
            </div>

            <div>
                <label class="admin-label">Mô tả</label>
                <textarea name="description" rows="5" class="admin-input resize-y" placeholder="Mô tả món ăn...">{{ old('description', $food->description) }}</textarea>
            </div>

            <div>
                <label class="admin-label">Ảnh món</label>
                <input type="file" name="image" accept="image/*" class="admin-input" />
                <p class="admin-help">Chọn file ảnh món ăn từ máy tính. Để trống nếu không muốn thay đổi ảnh.</p>
            </div>
        </div>

        <div class="lg:col-span-4 space-y-5">
            <div>
                <label class="admin-label">Giá *</label>
                <input type="number" name="price" step="0.01" value="{{ old('price', $food->price ?? '') }}" class="admin-input" placeholder="100000" required />
            </div>

            <div class="flex items-center gap-3">
                <input type="hidden" name="active" value="0">
                <label class="inline-flex items-center gap-3">
                    <input type="checkbox" name="active" value="1" class="form-checkbox h-5 w-5 rounded border app-border text-brand-start" {{ old('active', $food->active) ? 'checked' : '' }}>
                    <span class="text-sm font-medium">Hiển thị món</span>
                </label>
            </div>

            @if($food->image)
                <div class="rounded-3xl overflow-hidden border app-border">
                    <img src="{{ asset('storage/' . $food->image) }}" alt="{{ $food->name }}" class="w-full h-40 object-cover" />
                </div>
            @endif
        </div>
    </div>

    <div class="mt-8 flex flex-col sm:flex-row justify-end gap-3 border-t app-border pt-5">
        <a href="{{ route('admin.foods.index') }}" class="admin-btn-secondary">Hủy</a>
        <button type="submit" class="admin-btn-primary">
            <i class="ph-bold ph-floppy-disk"></i>
            {{ $food->exists ? 'Cập nhật món' : 'Lưu món' }}
        </button>
    </div>
</form>
@endsection