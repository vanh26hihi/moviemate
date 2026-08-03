@extends('layouts.admin')

@section('title', 'Quản lý đánh giá - MovieMate')
@section('page-title', 'Quản lý đánh giá')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Quản lý đánh giá</h1>
        <p class="admin-page-subtitle">Giao diện đồng bộ MovieMate và tuân theo dữ liệu backend TEAM.</p>
    </div>
</div>

<x-empty-state
    title="Chưa có dữ liệu đánh giá"
    description="Backend TEAM chưa đăng ký route/controller đánh giá. Không có đánh giá mẫu được hiển thị."
    icon="ph-star"
/>
@endsection
