@extends('layouts.staff')

@section('title', 'Bán vé tại quầy - MovieMate')
@section('page-title', 'Bán vé tại quầy')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Bán vé tại quầy</h1>
        <p class="admin-page-subtitle">Giao diện MovieMate sử dụng dữ liệu từ hệ thống máy chủ.</p>
    </div>
</div>

<x-empty-state
    title="Sơ đồ bán vé chưa có dữ liệu"
    description="Hệ thống chưa cung cấp dữ liệu suất chiếu, phòng và ghế cho chức năng này."
    icon="ph-cash-register"
/>
@endsection
