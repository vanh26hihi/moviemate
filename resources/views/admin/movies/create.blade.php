@extends('layouts.admin')

@section('title', 'Thêm phim mới')
@section('page-title', 'Thêm phim mới')
@section('suppress-global-validation-summary', '1')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Thêm phim mới</h1>
        <p class="admin-page-subtitle">Tạo phim mới với thông tin phát hành, media và thể loại.</p>
    </div>
    <a href="{{ route('admin.movies.index') }}" class="admin-btn-secondary">
        <i class="ph ph-arrow-left"></i>
        Quay lại
    </a>
</div>

<x-validation-summary class="mb-5" :errors="$errors" :except="['poster', 'cover_image']" />

<form action="{{ route('admin.movies.store') }}" method="POST" enctype="multipart/form-data" class="admin-form-card" data-submit-once>
    @csrf

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-7 space-y-5">
            <div>
                <label class="admin-label">Tên phim *</label>
                <input type="text" name="title" value="{{ old('title') }}" required maxlength="255" class="admin-input" placeholder="Nhập đúng tên phát hành">
                <p class="admin-help">Tên phim có thể trùng; slug bên dưới phân biệt đường dẫn của từng hồ sơ.</p>
                @error('title')<p class="mt-1 text-sm font-semibold text-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="admin-label">Đường dẫn rút gọn</label>
                <input type="text" name="slug" value="{{ old('slug') }}" maxlength="255" class="admin-input" placeholder="Để trống để tự tạo" data-validation-url="{{ route('admin.validation.field') }}" data-validation-rule="movie.slug">
                <p class="admin-help">Slug phải duy nhất trên toàn hệ thống. Để trống nếu muốn hệ thống tự tạo từ tên phim.</p>
                @error('slug')<p class="mt-1 text-sm font-semibold text-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="admin-label">Mô tả</label>
                <textarea name="description" rows="7" class="admin-input resize-y" placeholder="Nội dung, synopsis hoặc ghi chú phim...">{{ old('description') }}</textarea>
            </div>

            <div>
                <label class="admin-label">Đường dẫn video giới thiệu</label>
                <input type="url" name="trailer_url" value="{{ old('trailer_url') }}" class="admin-input" placeholder="https://youtube.com/...">
            </div>
        </div>

        <div class="lg:col-span-5 space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="admin-label">Quốc gia</label>
                    <input type="text" name="country" value="{{ old('country') }}" class="admin-input" placeholder="Việt Nam, Mỹ...">
                </div>

                <div>
                    <label class="admin-label">Thời lượng (phút)</label>
                    <input type="number" name="duration" value="{{ old('duration') ?? 90 }}" class="admin-input">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="admin-label">Độ tuổi</label>
                    <input type="text" name="age_rating" value="{{ old('age_rating') ?? 'P' }}" class="admin-input">
                </div>

                <div>
                    <label class="admin-label">Ngày khởi chiếu</label>
                    <input type="date" name="release_date" value="{{ old('release_date') }}" class="admin-input">
                </div>
            </div>

            <div><label class="admin-label">Vòng đời ban đầu</label><div class="admin-input">Bản nháp</div><input type="hidden" name="status" value="draft"><p class="admin-help">Phim mới chưa xuất hiện với khách hàng. Sau khi hoàn thiện, quản trị viên mới công bố và xếp lịch theo từng rạp.</p></div>

            <div>
                <label class="admin-label">Ảnh áp phích</label>
                <div class="mb-3 aspect-[2/3] max-w-48 overflow-hidden rounded-xl bg-slate-950">
                    <img id="poster-preview" alt="Xem trước poster" class="hidden h-full w-full object-cover">
                    <div data-image-fallback class="admin-media-fallback h-full w-full">Ảnh áp phích</div>
                </div>
                <input type="file" name="poster" accept="image/jpeg,image/png,image/webp" data-image-preview="poster-preview" class="admin-input file:mr-4 file:rounded-lg file:border-0 file:bg-brand-start/10 file:px-3 file:py-2 file:font-bold file:text-brand-start">
                <p class="admin-help">JPG, PNG hoặc WebP; tối đa 4 MB. Tỷ lệ đề xuất 2:3.</p>
                @error('poster')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="admin-label">Ảnh bìa</label>
                <div class="mb-3 aspect-video overflow-hidden rounded-xl bg-slate-950">
                    <img id="banner-preview" alt="Xem trước banner" class="hidden h-full w-full object-cover">
                    <div data-image-fallback class="admin-media-fallback h-full w-full">Ảnh bìa</div>
                </div>
                <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" data-image-preview="banner-preview" class="admin-input file:mr-4 file:rounded-lg file:border-0 file:bg-brand-start/10 file:px-3 file:py-2 file:font-bold file:text-brand-start">
                <p class="admin-help">JPG, PNG hoặc WebP; tối đa 8 MB. Tỷ lệ đề xuất 16:9.</p>
                @error('cover_image')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="admin-label">Thể loại</label>
                <select name="genres[]" multiple class="admin-input min-h-40">
                    @foreach($genres as $genre)
                        <option value="{{ $genre->id }}" {{ (collect(old('genres'))->contains($genre->id)) ? 'selected' : '' }}>
                            {{ $genre->name }}
                        </option>
                    @endforeach
                </select>
                <p class="admin-help">Giữ Ctrl (Windows) hoặc Cmd (Mac) để chọn nhiều thể loại.</p>
            </div>

            @include('admin.movies._presentation-formats')
        </div>
    </div>

    <div class="mt-8 flex flex-col sm:flex-row justify-end gap-3 border-t app-border pt-5">
        <a href="{{ route('admin.movies.index') }}" class="admin-btn-secondary">Hủy</a>
        <button type="submit" class="admin-btn-primary" data-loading-label="Đang lưu…">
            <i class="ph-bold ph-floppy-disk"></i>
            Lưu phim
        </button>
    </div>
</form>
@endsection
