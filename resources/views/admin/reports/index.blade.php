@extends('layouts.admin')

@section('title', 'Báo cáo - MovieMate')
@section('page-title', 'Báo cáo')

@section('content')
<header class="admin-page-header items-start">
    <div>
        <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-start">Phân tích theo chi nhánh</p>
        <h1 class="admin-page-title mt-2">Báo cáo kinh doanh & vận hành</h1>
        <p class="admin-page-subtitle">Số liệu tổng hợp không chứa thông tin khách hàng hoặc mã giao dịch.</p>
    </div>
    <a class="btn-secondary" href="{{ route('admin.dashboard', $filters) }}"><i class="ph ph-squares-four"></i>Tổng quan</a>
</header>

@include('admin.reports._filters', ['filterAction' => route('admin.reports.index'), 'detailed' => true])
@include('admin.reports._analytics', ['detailed' => true])
@endsection
