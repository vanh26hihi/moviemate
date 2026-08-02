@extends('layouts.admin')

@section('title', 'Quản lý người dùng - MovieMate')
@section('page-title', 'Quản lý người dùng')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Quản lý người dùng</h1>
        <p class="admin-page-subtitle">Giao diện đồng bộ MovieMate và tuân theo dữ liệu backend TEAM.</p>
    </div>
</div>

<x-empty-state
    title="Chưa có dữ liệu người dùng"
    description="Backend TEAM chưa đăng ký route/controller quản trị người dùng, nên giao diện không tự tạo dữ liệu."
    icon="ph-users"
/>
@endsection
