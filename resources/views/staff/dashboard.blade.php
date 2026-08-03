@extends('layouts.staff')

@section('title', 'Tổng quan nhân viên - MovieMate')
@section('page-title', 'Tổng quan nhân viên')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Tổng quan nhân viên</h1>
        <p class="admin-page-subtitle">Giao diện đồng bộ MovieMate và tuân theo dữ liệu backend TEAM.</p>
    </div>
</div>

<x-empty-state
    title="Dashboard nhân viên chưa có dữ liệu"
    description="Backend TEAM chưa đăng ký route/controller cho khu vực nhân viên."
    icon="ph-squares-four"
/>
@endsection
