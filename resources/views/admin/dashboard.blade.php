@extends('layouts.admin')

@section('title', 'Tổng quan quản trị - MovieMate')
@section('page-title', 'Tổng quan quản trị')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Tổng quan quản trị</h1>
        <p class="admin-page-subtitle">Giao diện đồng bộ MovieMate và tuân theo dữ liệu backend TEAM.</p>
    </div>
</div>

<x-empty-state
    title="Dashboard chưa có dữ liệu"
    description="Backend TEAM chưa đăng ký route/controller cung cấp số liệu Dashboard. Giao diện được giữ ở trạng thái trống an toàn."
    icon="ph-squares-four"
/>
@endsection
