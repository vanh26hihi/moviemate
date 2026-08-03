@extends('layouts.admin')

@section('title', 'Báo cáo doanh thu - MovieMate')
@section('page-title', 'Báo cáo doanh thu')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Báo cáo doanh thu</h1>
        <p class="admin-page-subtitle">Giao diện đồng bộ MovieMate và tuân theo dữ liệu backend TEAM.</p>
    </div>
</div>

<x-empty-state
    title="Chưa có dữ liệu doanh thu"
    description="Backend TEAM chưa cung cấp truy vấn hoặc route Analytics doanh thu."
    icon="ph-chart-line-up"
/>
@endsection
