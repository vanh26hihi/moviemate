@extends('layouts.admin')

@section('title', 'Thêm Phim')

@section('content')
<div class="container mx-auto py-6">
    <h1 class="text-2xl font-bold mb-4">Thêm Phim</h1>

    @if ($errors->any())
        <div class="bg-red-100 text-red-800 p-3 rounded mb-4">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.movies.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
        @csrf

        <div>
            <label class="block font-medium">Tiêu đề *</label>
            <input type="text" name="title" value="{{ old('title') }}" required
                   class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="block font-medium">Slug (để trống để tự tạo)</label>
            <input type="text" name="slug" value="{{ old('slug') }}"
                   class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="block font-medium">Mô tả</label>
            <textarea name="description" rows="4"
                      class="w-full border rounded px-3 py-2">{{ old('description') }}</textarea>
        </div>

        <div>
            <label class="block font-medium">Poster (hình ảnh)</label>
            <input type="file" name="poster" accept="image/*" class="w-full">
        </div>

        <div>
            <label class="block font-medium">Cover Image (hình ảnh)</label>
            <input type="file" name="cover_image" accept="image/*" class="w-full">
        </div>

        <div>
            <label class="block font-medium">Trailer URL</label>
            <input type="url" name="trailer_url" value="{{ old('trailer_url') }}"
                   class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="block font-medium">Country</label>
            <input type="text" name="country" value="{{ old('country') }}"
                   class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="block font-medium">Duration (phút)</label>
            <input type="number" name="duration" value="{{ old('duration') ?? 90 }}"
                   class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="block font-medium">Age Rating</label>
            <input type="text" name="age_rating" value="{{ old('age_rating') ?? 'P' }}"
                   class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="block font-medium">Release Date</label>
            <input type="date" name="release_date" value="{{ old('release_date') }}"
                   class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="block font-medium">Status *</label>
            <select name="status" required class="w-full border rounded px-3 py-2">
                <option value="now_showing" {{ old('status') == 'now_showing' ? 'selected' : '' }}>Now Showing</option>
                <option value="coming_soon" {{ old('status') == 'coming_soon' ? 'selected' : '' }}>Coming Soon</option>
                <option value="stopped" {{ old('status') == 'stopped' ? 'selected' : '' }}>Stopped</option>
            </select>
        </div>

        <div>
            <label class="block font-medium">Thể loại</label>
            <select name="genres[]" multiple class="w-full border rounded px-3 py-2">
                @foreach($genres as $genre)
                    <option value="{{ $genre->id }}"
                        {{ (collect(old('genres'))->contains($genre->id)) ? 'selected' : '' }}>
                        {{ $genre->name }}
                    </option>
                @endforeach
            </select>
            <small class="text-gray-600">Giữ Ctrl (Windows) hoặc Cmd (Mac) để chọn nhiều.</small>
        </div>

        <div class="flex space-x-4">
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded">Lưu</button>
            <a href="{{ route('admin.movies.index') }}" class="bg-gray-600 text-white px-4 py-2 rounded">Hủy</a>
        </div>
    </form>
</div>
@endsection