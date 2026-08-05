@extends('layouts.app')

@section('title', 'Không tìm thấy trang - MovieMate')

@section('content')
<section class="cinema-surface flex min-h-[70vh] items-center py-12">
    <div class="mx-auto w-full max-w-3xl px-4 sm:px-6">
        <x-empty-state
            title="Không tìm thấy trang"
            description="Đường dẫn bạn truy cập không tồn tại hoặc chưa được backend TEAM cung cấp."
            icon="ph-map-pin-line"
        >
            <a href="{{ route('home') }}" class="btn-primary"><i class="ph ph-house"></i> Về trang chủ</a>
            @if(\Illuminate\Support\Facades\Route::has('user.movies.index'))
                <a href="{{ route('user.movies.index') }}" class="btn-secondary"><i class="ph ph-film-strip"></i> Xem phim</a>
            @endif
        </x-empty-state>
    </div>
</section>
@endsection
