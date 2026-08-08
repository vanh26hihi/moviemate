@extends('layouts.admin')

@section('title', 'Chi tiết người dùng - MovieMate')
@section('page-title', 'Chi tiết người dùng')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Chi tiết người dùng</h1>
        <p class="admin-page-subtitle">Giao diện MovieMate sử dụng dữ liệu từ hệ thống máy chủ.</p>
    </div>
</div>

<x-empty-state
    title="Chưa có dữ liệu chi tiết"
    description="Hệ thống chưa cung cấp dữ liệu chi tiết người dùng."
    icon="ph-user-circle"
/>
@endsection
