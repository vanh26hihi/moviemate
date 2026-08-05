@extends('layouts.admin')

@section('title', 'Thêm phòng chiếu - MovieMate')
@section('page-title', 'Thêm phòng chiếu')

@section('content')
<div class="max-w-3xl">
    <div class="cinema-card p-6 sm:p-8">
        <h1 class="mb-2 text-2xl font-extrabold app-text">Thông tin phòng chiếu</h1>
        <p class="mb-6 app-muted">Tạo phòng mới, sau đó thiết kế sơ đồ ghế riêng cho phòng.</p>

        <form action="{{ route('admin.rooms.store') }}" method="POST" class="space-y-5" novalidate>
            @csrf
            @include('admin.rooms._form')
        </form>
    </div>
</div>
@endsection
