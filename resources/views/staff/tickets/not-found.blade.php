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
    title="Không có dữ liệu vé"
    description="Hệ thống chưa cung cấp kết quả tra cứu vé."
    icon="ph-magnifying-glass"
/>
@endsection
