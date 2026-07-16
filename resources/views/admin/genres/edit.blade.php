@extends('layouts.admin')

@section('title', 'Sửa Thể Loại')

@section('content')
<div class="container mx-auto py-6">
    <h1 class="text-2xl font-bold mb-4">Sửa Thể Loại: {{ $genre->name }}</h1>

    @if ($errors->any())
        <div class="bg-red-100 text-red-800 p-3 rounded mb-4">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.genres.update', $genre) }}" method="POST" class="space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label class="block font-medium">Tên *</label>
            <input type="text" name="name" value="{{ old('name', $genre->name) }}" required
                   class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="block font-medium">Slug (để trống để tự tạo)</label>
            <input type="text" name="slug" value="{{ old('slug', $genre->slug) }}"
                   class="w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="block font-medium">Mô tả</label>
            <textarea name="description" rows="4"
                      class="w-full border rounded px-3 py-2">{{ old('description', $genre->description) }}</textarea>
        </div>

        <div class="flex space-x-4">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Cập nhật</button>
            <a href="{{ route('admin.genres.index') }}" class="bg-gray-600 text-white px-4 py-2 rounded">Hủy</a>
        </div>
    </form>
</div>
@endsection