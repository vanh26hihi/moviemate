@extends('layouts.admin')

@section('title', 'Quản lý vé đặt - MovieMate')
@section('page-title', 'Quản lý vé đặt')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Quản lý vé đặt</h1>
        <p class="admin-page-subtitle">Giao diện MovieMate sử dụng dữ liệu từ hệ thống máy chủ.</p>
    </div>
</div>

<x-empty-state
    title="Chưa có dữ liệu vé đặt"
    description="Hệ thống chưa cung cấp dữ liệu quản trị đơn đặt vé nên trang đang ở trạng thái trống."
    icon="ph-ticket"
/>
@endsection
