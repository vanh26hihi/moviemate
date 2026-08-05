@extends('layouts.admin')

@section('title', 'Chi tiết vé đặt - MovieMate')
@section('page-title', 'Chi tiết vé đặt')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Chi tiết vé đặt</h1>
        <p class="admin-page-subtitle">Giao diện đồng bộ MovieMate và tuân theo dữ liệu backend TEAM.</p>
    </div>
</div>

<x-empty-state
    title="Chưa có dữ liệu chi tiết"
    description="Backend TEAM chưa cung cấp route/controller cho chi tiết booking quản trị."
    icon="ph-ticket"
/>
@endsection
