@extends('layouts.admin')

@section('title', 'AI nội dung phim - MovieMate')
@section('page-title', 'AI nội dung phim')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">AI nội dung phim</h1>
        <p class="admin-page-subtitle">Giao diện MovieMate sử dụng dữ liệu từ hệ thống máy chủ.</p>
    </div>
</div>

<x-empty-state
    title="AI quản trị chưa được kết nối"
    description="Hệ thống chưa kết nối chức năng xử lý nội dung phim bằng trí tuệ nhân tạo. Không có nội dung mẫu được tạo."
    icon="ph-magic-wand"
/>
@endsection
