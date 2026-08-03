@extends('layouts.admin')

@section('title', 'Quản lý vé đặt - MovieMate')
@section('page-title', 'Quản lý vé đặt')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Quản lý vé đặt</h1>
        <p class="admin-page-subtitle">Giao diện đồng bộ MovieMate và tuân theo dữ liệu backend TEAM.</p>
    </div>
</div>

<x-empty-state
    title="Chưa có dữ liệu vé đặt"
    description="Backend TEAM chưa đăng ký route/controller quản trị booking, nên giao diện chỉ hiển thị empty state."
    icon="ph-ticket"
/>
@endsection
