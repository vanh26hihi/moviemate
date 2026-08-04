@extends('layouts.admin')

@section('title', 'Thêm phim mới')
@section('page-title', 'Thêm phim mới')

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

@if ($errors->any())
    <div class="mb-5 rounded-2xl border border-error/30 bg-error/10 text-error px-4 py-3 text-sm">
        <ul class="list-disc list-inside">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('admin.movies.store') }}" method="POST" enctype="multipart/form-data" class="admin-form-card">
    @csrf

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-7 space-y-5">
            <div>
                <label class="admin-label">Tiêu đề *</label>
                <input type="text" name="title" value="{{ old('title') }}" required class="admin-input" placeholder="Tên phim">
            </div>

            <div>
                <label class="admin-label">Slug</label>
                <input type="text" name="slug" value="{{ old('slug') }}" class="admin-input" placeholder="Để trống để tự tạo">
                <p class="admin-help">Để trống nếu muốn hệ thống tự tạo slug từ tiêu đề.</p>
            </div>

            <div>
                <label class="admin-label">Mô tả</label>
                <textarea name="description" rows="7" class="admin-input resize-y" placeholder="Nội dung, synopsis hoặc ghi chú phim...">{{ old('description') }}</textarea>
            </div>

            <div>
                <label class="admin-label">Trailer URL</label>
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

            <div>
                <label class="admin-label">Trạng thái *</label>
                <select name="status" required class="admin-input">
                    <option value="now_showing" {{ old('status') == 'now_showing' ? 'selected' : '' }}>Đang chiếu</option>
                    <option value="coming_soon" {{ old('status') == 'coming_soon' ? 'selected' : '' }}>Sắp chiếu</option>
                    <option value="stopped" {{ old('status') == 'stopped' ? 'selected' : '' }}>Ngừng chiếu</option>
                </select>
            </div>

            <div>
                <label class="admin-label">Poster</label>
                <input type="file" name="poster" accept="image/*" class="admin-input file:mr-4 file:rounded-lg file:border-0 file:bg-brand-start/10 file:px-3 file:py-2 file:font-bold file:text-brand-start">
            </div>

            <div>
                <label class="admin-label">Cover image</label>
                <input type="file" name="cover_image" accept="image/*" class="admin-input file:mr-4 file:rounded-lg file:border-0 file:bg-brand-start/10 file:px-3 file:py-2 file:font-bold file:text-brand-start">
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
        </div>
    </div>

    <div class="mt-8 flex flex-col sm:flex-row justify-end gap-3 border-t app-border pt-5">
        <a href="{{ route('admin.movies.index') }}" class="admin-btn-secondary">Hủy</a>
        <button type="submit" class="admin-btn-primary">
            <i class="ph-bold ph-floppy-disk"></i>
            Lưu phim
        </button>
    </div>
</form>
@endsection
