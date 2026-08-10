@extends('layouts.admin')
@section('title', 'Thêm suất chiếu - Quản trị MovieMate')
@section('page-title', 'Thêm suất chiếu')
@section('suppress-global-validation-summary', '1')

@section('content')
<div class="max-w-5xl">
    <div class="cinema-card p-6 sm:p-8">
        <h1 class="text-2xl font-extrabold app-text mb-2">Thông tin suất chiếu</h1>
        <p class="app-muted mb-6">Chi nhánh: {{ $cinema?->name ?? 'Toàn hệ thống — chọn phòng để xác định chi nhánh' }}</p>

        <form method="POST" action="{{ route('admin.showtimes.store') }}" class="space-y-6">
            @csrf
            @include('admin.showtimes._form-fields', ['showtime' => null])
            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.showtimes.index') }}" class="btn-secondary">Hủy</a>
                <button class="btn-primary">Lưu suất chiếu</button>
            </div>
        </form>
    </div>
</div>
@endsection
