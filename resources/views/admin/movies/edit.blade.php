@extends('layouts.admin')

@section('title', 'Sửa phim')
@section('page-title', 'Sửa phim')
@section('suppress-global-validation-summary', '1')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Sửa phim: {{ $movie->title }}</h1>
        <p class="admin-page-subtitle">Cập nhật thông tin phát hành, poster, cover và thể loại.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.movies.show', $movie) }}" class="admin-btn-info">
            <i class="ph ph-eye"></i>
            Xem
        </a>
        <a href="{{ route('admin.movies.index') }}" class="admin-btn-secondary">
            <i class="ph ph-arrow-left"></i>
            Quay lại
        </a>
    </div>
</div>

<x-validation-summary class="mb-5" :errors="$errors" :except="['poster', 'cover_image']" />

<form action="{{ route('admin.movies.update', $movie) }}" method="POST" enctype="multipart/form-data" class="admin-form-card" data-submit-once>
    @csrf
    @method('PUT')

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-7 space-y-5">
            <div>
                <label class="admin-label">Tiêu đề *</label>
                <input type="text" name="title" value="{{ old('title', $movie->title) }}" required class="admin-input">
            </div>

            <div>
                <label class="admin-label">Đường dẫn rút gọn</label>
                <input type="text" name="slug" value="{{ old('slug', $movie->slug) }}" class="admin-input">
                <p class="admin-help">Để trống nếu muốn hệ thống tự tạo slug từ tiêu đề.</p>
            </div>

            <div>
                <label class="admin-label">Mô tả</label>
                <textarea name="description" rows="7" class="admin-input resize-y">{{ old('description', $movie->description) }}</textarea>
            </div>

            <div>
                <label class="admin-label">Đường dẫn video giới thiệu</label>
                <input type="url" name="trailer_url" value="{{ old('trailer_url', $movie->trailer_url) }}" class="admin-input">
            </div>
        </div>

        <div class="lg:col-span-5 space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="admin-label">Quốc gia</label>
                    <input type="text" name="country" value="{{ old('country', $movie->country) }}" class="admin-input">
                </div>

                <div>
                    <label class="admin-label">Thời lượng (phút)</label>
                    <input type="number" name="duration" value="{{ old('duration', $movie->duration) }}" class="admin-input">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="admin-label">Độ tuổi</label>
                    <input type="text" name="age_rating" value="{{ old('age_rating', $movie->age_rating) }}" class="admin-input">
                </div>

                <div>
                    <label class="admin-label">Ngày khởi chiếu</label>
                    <input type="date" name="release_date" value="{{ old('release_date', $movie->release_date ? \Carbon\Carbon::parse($movie->release_date)->format('Y-m-d') : '') }}" class="admin-input">
                </div>
            </div>

            <div>
                <label class="admin-label">Trạng thái *</label>
                <select name="status" required class="admin-input">
                    <option value="now_showing" {{ old('status', $movie->status) == 'now_showing' ? 'selected' : '' }}>Đang chiếu</option>
                    <option value="coming_soon" {{ old('status', $movie->status) == 'coming_soon' ? 'selected' : '' }}>Sắp chiếu</option>
                    <option value="stopped" {{ old('status', $movie->status) == 'stopped' ? 'selected' : '' }}>Ngừng chiếu</option>
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="rounded-2xl app-card-soft border app-border p-4">
                    <label class="admin-label">Ảnh áp phích hiện tại</label>
                    <div class="mb-3 aspect-[2/3] overflow-hidden rounded-xl bg-slate-950">
                        @if($movie->poster_url)
                            <img id="poster-preview" src="{{ $movie->poster_url }}" alt="Ảnh áp phích của {{ $movie->title }}" class="h-full w-full object-cover" loading="lazy">
                            <div data-image-fallback class="admin-media-fallback hidden h-full w-full">MM</div>
                        @else
                            <img id="poster-preview" alt="Xem trước poster" class="hidden h-full w-full object-cover">
                            <div class="admin-media-fallback h-full w-full">MM</div>
                        @endif
                    </div>
                    <input type="file" name="poster" accept="image/jpeg,image/png,image/webp" data-image-preview="poster-preview" class="admin-input text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-start/10 file:px-3 file:py-2 file:font-bold file:text-brand-start">
                    <p class="admin-help">Để trống để giữ ảnh hiện tại. Tối đa 4 MB, tỷ lệ 2:3.</p>
                    @error('poster')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
                </div>

                <div class="rounded-2xl app-card-soft border app-border p-4">
                    <label class="admin-label">Ảnh bìa hiện tại</label>
                    <div class="mb-3 aspect-video overflow-hidden rounded-xl bg-slate-950">
                        @if($movie->cover_url)
                            <img id="banner-preview" src="{{ $movie->cover_url }}" alt="Ảnh bìa của {{ $movie->title }}" class="h-full w-full object-cover" loading="lazy">
                            <div data-image-fallback class="admin-media-fallback hidden h-full w-full">Ảnh bìa</div>
                        @else
                            <img id="banner-preview" alt="Xem trước banner" class="hidden h-full w-full object-cover">
                            <div class="admin-media-fallback h-full w-full">Ảnh bìa</div>
                        @endif
                    </div>
                    <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" data-image-preview="banner-preview" class="admin-input text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-start/10 file:px-3 file:py-2 file:font-bold file:text-brand-start">
                    <p class="admin-help">Để trống để giữ ảnh hiện tại. Tối đa 8 MB, tỷ lệ 16:9.</p>
                    @error('cover_image')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="admin-label">Thể loại</label>
                <select name="genres[]" multiple class="admin-input min-h-40">
                    @foreach($genres as $genre)
                        <option value="{{ $genre->id }}" {{ (collect(old('genres', $movie->genres->pluck('id')->toArray()))->contains($genre->id)) ? 'selected' : '' }}>
                            {{ $genre->name }}
                        </option>
                    @endforeach
                </select>
                <p class="admin-help">Giữ Ctrl (Windows) hoặc Cmd (Mac) để chọn nhiều thể loại.</p>
            </div>
        </div>
    </div>

    <div class="mt-8 flex flex-col sm:flex-row justify-end gap-3 border-t app-border pt-5">
        <a href="{{ route('admin.movies.index') }}" class="admin-btn-secondary">Hủy</a>
        <button type="submit" class="admin-btn-primary" data-loading-label="Đang cập nhật…">
            <i class="ph-bold ph-floppy-disk"></i>
            Cập nhật
        </button>
    </div>
</form>
@endsection
