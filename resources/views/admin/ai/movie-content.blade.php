@extends('layouts.admin')

@section('title', 'AI nội dung phim - MovieMate')
@section('page-title', 'AI nội dung phim')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">AI nội dung phim</h1>
        <p class="admin-page-subtitle">Giao diện đồng bộ MovieMate và tuân theo dữ liệu backend TEAM.</p>
    </div>
</div>

<x-empty-state
    title="AI quản trị chưa được kết nối"
    description="Backend TEAM chưa đăng ký route xử lý AI nội dung phim. Không có nội dung mẫu được tạo."
    icon="ph-magic-wand"
/>
@endsection
