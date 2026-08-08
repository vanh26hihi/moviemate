@extends('layouts.admin')

@section('title', 'Báo cáo doanh thu - MovieMate')
@section('page-title', 'Báo cáo doanh thu')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Báo cáo doanh thu</h1>
        <p class="admin-page-subtitle">Giao diện MovieMate sử dụng dữ liệu từ hệ thống máy chủ.</p>
    </div>
</div>

<x-empty-state
    title="Chưa có dữ liệu doanh thu"
    description="Hệ thống chưa cung cấp dữ liệu phân tích doanh thu."
    icon="ph-chart-line-up"
/>
@endsection
