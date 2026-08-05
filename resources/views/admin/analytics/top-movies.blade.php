@extends('layouts.admin')

@section('title', 'Phim bán chạy - MovieMate')
@section('page-title', 'Phim bán chạy')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Phim bán chạy</h1>
        <p class="admin-page-subtitle">Giao diện MovieMate sử dụng dữ liệu từ hệ thống máy chủ.</p>
    </div>
</div>

<x-empty-state
    title="Chưa có dữ liệu xếp hạng"
    description="Hệ thống chưa cung cấp dữ liệu xếp hạng phim."
    icon="ph-crown"
/>
@endsection
