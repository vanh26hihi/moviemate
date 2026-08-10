@extends('layouts.admin')
@section('title', 'Chỉnh sửa suất chiếu - Quản trị MovieMate')
@section('page-title', 'Chỉnh sửa suất chiếu')
@section('suppress-global-validation-summary', '1')

@section('content')
<div class="max-w-5xl">
    <div class="cinema-card p-6 sm:p-8">
        <h1 class="text-2xl font-extrabold app-text mb-2">Cập nhật suất chiếu</h1>
        <p class="app-muted mb-6">
            Chi nhánh: {{ $showtime->cinema?->name ?? $cinema?->name ?? '—' }} · Sơ đồ hiện tại: phiên bản {{ $showtime->roomLayout->version }}
        </p>

        <form method="POST" action="{{ route('admin.showtimes.update', $showtime) }}" class="space-y-6">
            @csrf
            @method('PUT')
            @include('admin.showtimes._form-fields')
            <div class="flex justify-end gap-3">
                <a href="{{ route('admin.showtimes.index') }}" class="btn-secondary">Hủy</a>
                <button class="btn-primary">Cập nhật</button>
            </div>
        </form>
    </div>
</div>
@endsection
