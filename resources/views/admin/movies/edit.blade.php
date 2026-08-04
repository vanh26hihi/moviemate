@extends('layouts.admin')

@section('title', 'Sửa phim')
@section('page-title', 'Sửa phim')

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

@if ($errors->any())
    <div class="mb-5 rounded-2xl border border-error/30 bg-error/10 text-error px-4 py-3 text-sm">
        <ul class="list-disc list-inside">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('admin.movies.update', $movie) }}" method="POST" enctype="multipart/form-data" class="admin-form-card">
    @csrf
    @method('PUT')

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-7 space-y-5">
            <div>
                <label class="admin-label">Tiêu đề *</label>
                <input type="text" name="title" value="{{ old('title', $movie->title) }}" required class="admin-input">
            </div>

            <div>
                <label class="admin-label">Slug</label>
                <input type="text" name="slug" value="{{ old('slug', $movie->slug) }}" class="admin-input">
                <p class="admin-help">Để trống nếu muốn hệ thống tự tạo slug từ tiêu đề.</p>
            </div>

            <div>
                <label class="admin-label">Mô tả</label>
                <textarea name="description" rows="7" class="admin-input resize-y">{{ old('description', $movie->description) }}</textarea>
            </div>

            <div>
                <label class="admin-label">Trailer URL</label>
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
                    <label class="admin-label">Poster hiện tại</label>
                    <div class="mb-3 aspect-[2/3] overflow-hidden rounded-xl bg-slate-950">
                        @if($movie->poster_url)
                            <img src="{{ $movie->poster_url }}" alt="Poster" class="h-full w-full object-cover" loading="lazy">
                        @else
                            <div class="admin-media-fallback h-full w-full">MM</div>
                        @endif
                    </div>
                    <input type="file" name="poster" accept="image/*" class="admin-input text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-start/10 file:px-3 file:py-2 file:font-bold file:text-brand-start">
                </div>

                <div class="rounded-2xl app-card-soft border app-border p-4">
                    <label class="admin-label">Cover hiện tại</label>
                    <div class="mb-3 aspect-[16/10] overflow-hidden rounded-xl bg-slate-950">
                        @if($movie->cover_url)
                            <img src="{{ $movie->cover_url }}" alt="Cover" class="h-full w-full object-cover" loading="lazy">
                        @else
                            <div class="admin-media-fallback h-full w-full">Cover</div>
                        @endif
                    </div>
                    <input type="file" name="cover_image" accept="image/*" class="admin-input text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-brand-start/10 file:px-3 file:py-2 file:font-bold file:text-brand-start">
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
        <button type="submit" class="admin-btn-primary">
            <i class="ph-bold ph-floppy-disk"></i>
            Cập nhật
        </button>
    </div>
</form>
@endsection
