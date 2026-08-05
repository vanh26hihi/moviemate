@extends('layouts.admin')
@section('title', 'Seat generator đã deprecated - MovieMate')
@section('page-title', 'Seat generator đã deprecated')
@section('content')
<div class="cinema-card max-w-3xl p-6">
    <h1 class="text-2xl font-extrabold app-text">Sơ đồ ghế đã chuyển sang Dynamic Layout Editor</h1>
    <p class="mt-3 app-muted">Trình tạo ma trận ghế cố định không còn được dùng để tạo dữ liệu. Layout mới hỗ trợ aisle, empty cell, couple pair, maintenance và versioning riêng cho từng phòng.</p>
    <a href="{{ route('admin.rooms.layout.show', $room) }}" class="btn-primary mt-6">Mở Dynamic Layout Editor</a>
</div>
@endsection
