@extends('layouts.staff')

@section('title', 'Danh sách vé - MovieMate')
@section('page-title', 'Danh sách vé')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Danh sách vé</h1>
        <p class="admin-page-subtitle">Giao diện MovieMate sử dụng dữ liệu từ hệ thống máy chủ.</p>
    </div>
</div>

<x-empty-state
    title="Chưa có dữ liệu vé"
    description="Hệ thống chưa cung cấp danh sách vé cho khu vực nhân viên."
    icon="ph-ticket"
/>
@endsection
