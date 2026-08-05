@extends('layouts.admin')
@section('title', 'Trình tạo ghế cũ đã ngừng sử dụng - MovieMate')
@section('page-title', 'Trình tạo ghế cũ đã ngừng sử dụng')
@section('content')
<div class="cinema-card max-w-3xl p-6">
    <h1 class="text-2xl font-extrabold app-text">Sơ đồ ghế đã chuyển sang trình thiết kế mới</h1>
    <p class="mt-3 app-muted">Trình tạo ma trận cố định không còn được dùng. Sơ đồ mới hỗ trợ lối đi, ô trống, cặp ghế đôi, trạng thái bảo trì và nhiều phiên bản riêng cho từng phòng.</p>
    <a href="{{ route('admin.rooms.layout.show', $room) }}" class="btn-primary mt-6">Mở trình thiết kế sơ đồ ghế</a>
</div>
@endsection
