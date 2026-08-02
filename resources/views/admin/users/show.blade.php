@extends('layouts.admin')

@section('title', 'Chi tiết người dùng - MovieMate')
@section('page-title', 'Chi tiết người dùng')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Chi tiết người dùng</h1>
        <p class="admin-page-subtitle">Giao diện đồng bộ MovieMate và tuân theo dữ liệu backend TEAM.</p>
    </div>
</div>

<x-empty-state
    title="Chưa có dữ liệu chi tiết"
    description="Backend TEAM chưa cung cấp route/controller cho chi tiết người dùng."
    icon="ph-user-circle"
/>
@endsection
