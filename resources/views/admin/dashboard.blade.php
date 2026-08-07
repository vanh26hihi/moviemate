@extends('layouts.admin')

@section('title', 'Tổng quan hệ thống - MovieMate')
@section('page-title', 'Tổng quan')

@section('content')
<header class="admin-page-header items-start">
    <div>
        <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-start">MovieMate Cinema</p>
        <h1 class="admin-page-title mt-2">Tổng quan hệ thống</h1>
        <p class="admin-page-subtitle">Tài chính theo thời điểm thu tiền; vận hành theo ngày bắt đầu suất chiếu.</p>
    </div>
    @can('reports.view')
        <a class="btn-secondary" href="{{ route('admin.reports.index', $filters) }}"><i class="ph ph-chart-line-up"></i>Báo cáo chi tiết</a>
    @endcan
</header>

@include('admin.reports._filters', ['filterAction' => route('admin.dashboard'), 'detailed' => false])
@include('admin.reports._analytics', ['detailed' => false])
@endsection
