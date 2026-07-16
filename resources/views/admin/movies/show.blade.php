@extends('layouts.admin')

@section('title', 'Chi Tiết Phim')

@section('content')
<div class="container mx-auto py-6">
    <h1 class="text-2xl font-bold mb-4">Chi Tiết Phim: {{ $movie->title }}</h1>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <strong>Slug:</strong> {{ $movie->slug }}
        </div>
        <div>
            <strong>Trạng thái:</strong> {{ $movie->status }}
        </div>
        <div>
            <strong>Country:</strong> {{ $movie->country ?? 'N/A' }}
        </div>
        <div>
            <strong>Duration:</strong> {{ $movie->duration ?? 'N/A' }} phút
        </div>
        <div>
            <strong>Age Rating:</strong> {{ $movie->age_rating ?? 'N/A' }}
        </div>
        <div>
            <strong>Release Date:</strong> {{ $movie->release_date ? $movie->release_date->format('Y-m-d') : 'N/A' }}
        </div>
        <div class="col-span-2">
            <strong>Mô tả:</strong>
            <p class="mt-1">{{ $movie->description ?? 'Chưa có mô tả.' }}</p>
        </div>
        <div class="col-span-2">
            <strong>Thể loại:</strong>
            <p>{{ $movie->genres->pluck('name')->join(', ') }}</p>
        </div>
        @if($movie->poster)
            <div class="col-span-2">
                <strong>Poster:</strong><br>
                <img src="{{ asset('storage/' . $movie->poster) }}" alt="Poster" class="max-w-xs">
            </div>
        @endif
        @if($movie->cover_image)
            <div class="col-span-2">
                <strong>Cover Image:</strong><br>
                <img src="{{ asset('storage/' . $movie->cover_image) }}" alt="Cover" class="max-w-xs">
            </div>
        @endif
        @if($movie->trailer_url)
            <div class="col-span-2">
                <strong>Trailer:</strong>
                <a href="{{ $movie->trailer_url }}" target="_blank" class="text-blue-600 underline">
                    Xem Trailer
                </a>
            </div>
        @endif
    </div>

    <div class="mt-6 flex space-x-4">
        <a href="{{ route('admin.movies.edit', $movie) }}" class="bg-yellow-600 text-white px-4 py-2 rounded">Sửa</a>
        <form action="{{ route('admin.movies.destroy', $movie) }}" method="POST"
              onsubmit="return confirm('Bạn có chắc muốn xóa phim này?');" class="inline">
            @csrf
            @method('DELETE')
            <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded">Xóa</button>
        </form>
        <a href="{{ route('admin.movies.index') }}" class="bg-gray-600 text-white px-4 py-2 rounded">Quay lại</a>
    </div>
</div>
@endsection