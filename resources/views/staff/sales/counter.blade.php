@extends('layouts.staff')

@section('title', 'Bán vé tại quầy - MovieMate')
@section('page-title', 'Bán vé tại quầy')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Bán vé tại quầy</h1>
        <p class="admin-page-subtitle">Giao diện đồng bộ MovieMate và tuân theo dữ liệu backend TEAM.</p>
    </div>
</div>

<x-empty-state
    title="Sơ đồ bán vé chưa có dữ liệu"
    description="Backend TEAM chưa đăng ký route/controller và chưa truyền dữ liệu suất chiếu, phòng, ghế."
    icon="ph-cash-register"
/>
@endsection
