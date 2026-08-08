@extends('layouts.admin')

@section('title', 'Quản lý đánh giá - MovieMate')
@section('page-title', 'Quản lý đánh giá')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Quản lý đánh giá</h1>
        <p class="admin-page-subtitle">Giao diện MovieMate sử dụng dữ liệu từ hệ thống máy chủ.</p>
    </div>
</div>

<x-empty-state
    title="Chưa có dữ liệu đánh giá"
    description="Hệ thống chưa cung cấp dữ liệu đánh giá. Không có đánh giá mẫu được hiển thị."
    icon="ph-star"
/>
@endsection
