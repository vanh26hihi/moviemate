@extends('layouts.admin')

@section('title', 'Sửa rạp')
@section('page-title', 'Sửa rạp')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Sửa rạp: {{ $cinema->name }}</h1>
        <p class="admin-page-subtitle">Cập nhật thông tin rạp theo dữ liệu backend TEAM.</p>
    </div>
    <a href="{{ route('admin.cinemas.index') }}" class="admin-btn-secondary">
        <i class="ph ph-arrow-left"></i>
        Quay lại
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

<form action="{{ route('admin.cinemas.update', $cinema) }}" method="POST" enctype="multipart/form-data" class="admin-form-card">
    @csrf
    @method('PUT')

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-7 space-y-5">
            <div>
                <label class="admin-label">Tên *</label>
                <input type="text" name="name" value="{{ old('name', $cinema->name) }}" required class="admin-input">
            </div>

            <div>
                <label class="admin-label">Địa chỉ *</label>
                <input type="text" name="address" value="{{ old('address', $cinema->address) }}" required class="admin-input">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="admin-label">Thành phố *</label>
                    <input type="text" name="city" value="{{ old('city', $cinema->city) }}" required class="admin-input">
                </div>

                <div>
                    <label class="admin-label">Số điện thoại</label>
                    <input type="text" name="phone" value="{{ old('phone', $cinema->phone) }}" class="admin-input">
                </div>
            </div>

            <div>
                <label class="admin-label">Mô tả</label>
                <textarea name="description" rows="6" class="admin-input resize-y">{{ old('description', $cinema->description) }}</textarea>
            </div>
        </div>

        <div class="lg:col-span-5 space-y-5">
            <div class="rounded-2xl app-card-soft border app-border p-4">
                <label class="admin-label">Hình ảnh hiện tại</label>
                <div class="mb-3 aspect-[16/10] overflow-hidden rounded-xl bg-slate-950">
                    @if($cinema->image)
                        <img src="{{ asset('storage/' . $cinema->image) }}" alt="{{ $cinema->name }}" class="h-full w-full object-cover" loading="lazy">
                    @else
                        <div class="admin-media-fallback h-full w-full">
                            <i class="ph-fill ph-buildings text-4xl"></i>
                        </div>
                    @endif
                </div>
                <input type="file" name="image" accept="image/*" class="admin-input file:mr-4 file:rounded-lg file:border-0 file:bg-brand-start/10 file:px-3 file:py-2 file:font-bold file:text-brand-start">
            </div>

            <div>
                <label class="admin-label">Trạng thái *</label>
                <select name="status" required class="admin-input">
                    <option value="active" {{ old('status', $cinema->status) == 'active' ? 'selected' : '' }}>Hoạt động</option>
                    <option value="inactive" {{ old('status', $cinema->status) == 'inactive' ? 'selected' : '' }}>Tạm ngừng</option>
                </select>
            </div>
        </div>
    </div>

    <div class="mt-8 flex flex-col sm:flex-row justify-end gap-3 border-t app-border pt-5">
        <a href="{{ route('admin.cinemas.index') }}" class="admin-btn-secondary">Hủy</a>
        <button type="submit" class="admin-btn-primary">
            <i class="ph-bold ph-floppy-disk"></i>
            Cập nhật
        </button>
    </div>
</form>
@endsection
