@extends('layouts.staff')

@section('title', 'Tổng quan nhân viên - MovieMate')
@section('page-title', 'Tổng quan nhân viên')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Tổng quan nhân viên</h1>
        <p class="admin-page-subtitle">Giao diện MovieMate sử dụng dữ liệu từ hệ thống máy chủ.</p>
    </div>
</div>

<x-empty-state
    title="Tổng quan nhân viên chưa có dữ liệu"
    description="Hệ thống chưa cung cấp dữ liệu cho khu vực nhân viên."
    icon="ph-squares-four"
/>
@endsection
