@extends('layouts.admin')

@php
    $dashboardAccess = app(\App\Services\CinemaAccessService::class);
    $isGlobalDashboard = $dashboardAccess->hasGlobalAccess(auth()->user());
    $dashboardCinema = $dashboardAccess->currentCinema(auth()->user());
@endphp

@section('title', ($isGlobalDashboard ? 'Tổng quan hệ thống' : 'Tổng quan chi nhánh').' - MovieMate')
@section('page-title', $isGlobalDashboard ? 'Tổng quan hệ thống' : 'Tổng quan chi nhánh')

@section('content')
<header class="admin-page-header items-start">
    <div>
        <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-start">{{ $isGlobalDashboard ? 'Quản trị toàn chuỗi' : 'Vận hành chi nhánh' }}</p>
        <h1 class="admin-page-title mt-2 break-words">{{ $isGlobalDashboard ? 'Tổng quan hệ thống' : 'Tổng quan — '.($dashboardCinema?->name ?? 'Chưa chọn chi nhánh') }}</h1>
        <p class="admin-page-subtitle">Tài chính theo thời điểm thu tiền; vận hành theo ngày bắt đầu suất chiếu.</p>
    </div>
    <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
        @if(!$isGlobalDashboard && $dashboardCinema)
            <a class="btn-secondary" href="{{ route('admin.cinemas.show', $dashboardCinema) }}"><i class="ph ph-buildings"></i>Mở Branch 360</a>
        @endif
        @can('reports.view')
            <a class="btn-secondary" href="{{ route('admin.reports.index', $filters) }}"><i class="ph ph-chart-line-up"></i>{{ $isGlobalDashboard ? 'Báo cáo chi tiết' : 'Báo cáo chi nhánh' }}</a>
        @endcan
    </div>
</header>

@include('admin.reports._filters', ['filterAction' => route('admin.dashboard'), 'detailed' => false])
@include('admin.reports._analytics', ['detailed' => false])
@endsection
