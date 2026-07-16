@extends('layouts.admin')

@section('title', 'Quản lý Phim')

@section('content')
<div class="container mx-auto py-6">
    <h1 class="text-2xl font-bold mb-4">Quản lý Phim</h1>

    @if(session('success'))
        <div class="bg-green-100 text-green-800 p-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex justify-between mb-4">
        <form method="GET" action="{{ route('admin.movies.index') }}" class="flex space-x-2">
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Tìm kiếm tiêu đề..."
                   class="border rounded px-3 py-1">
            <button type="submit" class="bg-blue-600 text-white px-3 py-1 rounded">Tìm</button>
        </form>
        <a href="{{ route('admin.movies.create') }}" class="bg-green-600 text-white px-4 py-2 rounded">
            Thêm mới
        </a>
    </div>

    <table class="w-full table-auto border-collapse">
        <thead class="bg-gray-200">
            <tr>
                <th class="border px-4 py-2">#</th>
                <th class="border px-4 py-2">Tiêu đề</th>
                <th class="border px-4 py-2">Thể loại</th>
                <th class="border px-4 py-2">Trạng thái</th>
                <th class="border px-4 py-2">Hành động</th>
            </tr>
        </thead>
        <tbody>
            @forelse($movies as $movie)
                <tr>
                    <td class="border px-4 py-2">{{ $movie->id }}</td>
                    <td class="border px-4 py-2">{{ $movie->title }}</td>
                    <td class="border px-4 py-2">
                        {{ $movie->genres->pluck('name')->join(', ') }}
                    </td>
                    <td class="border px-4 py-2">{{ $movie->status }}</td>
                    <td class="border px-4 py-2 space-x-2">
                        <a href="{{ route('admin.movies.show', $movie) }}" class="text-blue-600">Xem</a>
                        <a href="{{ route('admin.movies.edit', $movie) }}" class="text-yellow-600">Sửa</a>
                        <form action="{{ route('admin.movies.destroy', $movie) }}" method="POST"
                              class="inline"
                              onsubmit="return confirm('Bạn có chắc muốn xóa phim này?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600">Xóa</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="border px-4 py-2 text-center">Không có phim nào.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">
        {{ $movies->links() }}
    </div>
</div>
@endsection