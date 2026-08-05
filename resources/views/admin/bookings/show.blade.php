@extends('layouts.admin')

@section('title', 'Chi tiết vé đặt - MovieMate')
@section('page-title', 'Chi tiết vé đặt')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Chi tiết vé đặt</h1>
        <p class="admin-page-subtitle">Giao diện MovieMate sử dụng dữ liệu từ hệ thống máy chủ.</p>
    </div>
</div>

<x-empty-state
    title="Chưa có dữ liệu chi tiết"
    description="Hệ thống chưa cung cấp dữ liệu chi tiết đơn đặt vé cho trang quản trị."
    icon="ph-ticket"
/>
@endsection
