@extends('layouts.staff')

@section('title', 'Kết quả soát vé - MovieMate')
@section('page-title', 'Kết quả soát vé')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Kết quả soát vé</h1>
        <p class="admin-page-subtitle">Giao diện đồng bộ MovieMate và tuân theo dữ liệu backend TEAM.</p>
    </div>
</div>

<x-empty-state
    title="Chưa có kết quả soát vé"
    description="Backend TEAM chưa cung cấp dữ liệu vé đã sử dụng."
    icon="ph-clock-counter-clockwise"
/>
@endsection
