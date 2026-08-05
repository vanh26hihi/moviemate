@extends('layouts.staff')

@section('title', 'Soát vé - MovieMate')
@section('page-title', 'Soát vé')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Soát vé</h1>
        <p class="admin-page-subtitle">Giao diện đồng bộ MovieMate và tuân theo dữ liệu backend TEAM.</p>
    </div>
</div>

<x-empty-state
    title="Chức năng soát vé chưa được kết nối"
    description="Backend TEAM chưa đăng ký endpoint soát vé. Giao diện không mô phỏng kết quả vé."
    icon="ph-qr-code"
/>
@endsection
