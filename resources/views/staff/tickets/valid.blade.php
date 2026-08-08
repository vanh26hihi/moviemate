@extends('layouts.staff')

@section('title', 'Kết quả soát vé - MovieMate')
@section('page-title', 'Kết quả soát vé')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Kết quả soát vé</h1>
        <p class="admin-page-subtitle">Giao diện MovieMate sử dụng dữ liệu từ hệ thống máy chủ.</p>
    </div>
</div>

<x-empty-state
    title="Chưa có kết quả soát vé"
    description="Hệ thống chưa cung cấp dữ liệu vé hợp lệ."
    icon="ph-check-circle"
/>
@endsection
