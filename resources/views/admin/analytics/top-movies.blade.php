@extends('layouts.admin')

@section('title', 'Phim bán chạy - MovieMate')
@section('page-title', 'Phim bán chạy')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Phim bán chạy</h1>
        <p class="admin-page-subtitle">Giao diện đồng bộ MovieMate và tuân theo dữ liệu backend TEAM.</p>
    </div>
</div>

<x-empty-state
    title="Chưa có dữ liệu xếp hạng"
    description="Backend TEAM chưa cung cấp truy vấn hoặc route xếp hạng phim."
    icon="ph-crown"
/>
@endsection
