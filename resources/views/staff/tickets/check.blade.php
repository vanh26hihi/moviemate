@extends('layouts.staff')

@section('title', 'Soát vé - MovieMate')
@section('page-title', 'Soát vé')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Soát vé</h1>
        <p class="admin-page-subtitle">Giao diện MovieMate sử dụng dữ liệu từ hệ thống máy chủ.</p>
    </div>
</div>

<x-empty-state
    title="Chức năng soát vé chưa được kết nối"
    description="Hệ thống chưa kết nối chức năng soát vé nên không hiển thị kết quả giả lập."
    icon="ph-qr-code"
/>
@endsection
