@extends('layouts.admin')

@section('title', 'Chỉnh sửa phòng chiếu - MovieMate')
@section('page-title', 'Chỉnh sửa phòng chiếu')
@section('suppress-global-validation-summary', '1')

@section('content')
<div class="max-w-3xl">
    <div class="cinema-card p-6 sm:p-8">
        <h1 class="mb-2 text-2xl font-extrabold app-text">{{ $room->name }}</h1>
        <p class="mb-6 app-muted">Cập nhật thông tin và trạng thái phòng. Thao tác này không thay đổi sơ đồ ghế hiện có.</p>

        <form action="{{ route('admin.rooms.update', $room) }}" method="POST" class="space-y-5" novalidate>
            @csrf
            @method('PUT')
            @include('admin.rooms._form')
        </form>
    </div>
</div>
@endsection
