@extends('layouts.admin')

@section('title', 'Quản lý đơn đồ ăn')

@section('content')
<style>
    /* =========================================================
       MOVIEMATE - FOOD ORDER ADMIN
       INDEX PAGE
       ========================================================= */

    .food-page {
        min-height: 100vh;
        padding: 20px;
        background: #f5f7fb;
        color: #172033;
    }

    .food-page * {
        box-sizing: border-box;
    }

    .food-hero {
        position: relative;
        overflow: hidden;
        border-radius: 24px;
        padding: 30px;
        margin-bottom: 24px;
        background: linear-gradient(135deg, #111827 0%, #312e81 52%, #4f46e5 100%);
        color: #fff;
        box-shadow: 0 16px 45px rgba(49, 46, 129, .20);
    }

    .food-hero::before {
        content: "";
        position: absolute;
        width: 260px;
        height: 260px;
        right: -90px;
        top: -100px;
        border-radius: 50%;
        background: rgba(255,255,255,.08);
    }

    .food-hero::after {
        content: "";
        position: absolute;
        width: 180px;
        height: 180px;
        right: 160px;
        bottom: -110px;
        border-radius: 50%;
        background: rgba(255,255,255,.05);
    }

    .food-hero-content {
        position: relative;
        z-index: 2;
    }

    .food-breadcrumb {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 14px;
        font-size: 13px;
        color: rgba(255,255,255,.68);
    }

    .food-hero-title {
        margin: 0;
        font-size: 30px;
        line-height: 1.2;
        font-weight: 800;
        letter-spacing: -.5px;
    }

    .food-hero-desc {
        max-width: 650px;
        margin: 10px 0 0;
        font-size: 14px;
        line-height: 1.7;
        color: rgba(255,255,255,.78);
    }

    .hero-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 22px;
    }

    .hero-btn {
        min-height: 42px;
        padding: 0 17px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        border-radius: 11px;
        text-decoration: none;
        border: 1px solid rgba(255,255,255,.16);
        color: #fff;
        background: rgba(255,255,255,.10);
        font-size: 13px;
        font-weight: 700;
        transition: .2s;
    }

    .hero-btn:hover {
        color: #fff;
        background: rgba(255,255,255,.18);
        transform: translateY(-1px);
    }

    .hero-btn.primary {
        color: #312e81;
        background: #fff;
        border-color: #fff;
    }

    .hero-btn.primary:hover {
        color: #312e81;
        background: #f8fafc;
    }

    .hero-side {
        position: relative;
        z-index: 2;
        min-width: 230px;
        padding: 20px;
        border: 1px solid rgba(255,255,255,.12);
        border-radius: 18px;
        background: rgba(255,255,255,.08);
        backdrop-filter: blur(8px);
    }

    .hero-side-label {
        font-size: 12px;
        color: rgba(255,255,255,.65);
        margin-bottom: 5px;
    }

    .hero-side-value {
        font-size: 28px;
        font-weight: 800;
    }

    .hero-side-small {
        margin-top: 4px;
        color: rgba(255,255,255,.7);
        font-size: 12px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .stat-card {
        position: relative;
        overflow: hidden;
        padding: 21px;
        border: 1px solid #e8edf5;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 7px 25px rgba(15,23,42,.05);
        transition: .2s;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 14px 32px rgba(15,23,42,.08);
    }

    .stat-card::after {
        content: "";
        position: absolute;
        width: 90px;
        height: 90px;
        right: -35px;
        bottom: -40px;
        border-radius: 50%;
        background: #f8fafc;
    }

    .stat-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        flex: 0 0 48px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        font-size: 20px;
    }

    .stat-icon.blue { background: #eff6ff; color: #2563eb; }
    .stat-icon.orange { background: #fff7ed; color: #ea580c; }
    .stat-icon.green { background: #f0fdf4; color: #16a34a; }
    .stat-icon.purple { background: #faf5ff; color: #9333ea; }

    .stat-title {
        margin-top: 17px;
        font-size: 12px;
        color: #7b8799;
        font-weight: 600;
    }

    .stat-value {
        margin-top: 4px;
        font-size: 25px;
        font-weight: 800;
        color: #111827;
    }

    .stat-change {
        margin-top: 7px;
        font-size: 11px;
        color: #64748b;
    }

    .stat-change.positive {
        color: #16a34a;
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.6fr) minmax(300px, 1fr);
        gap: 20px;
        margin-bottom: 24px;
    }

    .panel {
        border: 1px solid #e8edf5;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 7px 25px rgba(15,23,42,.05);
        overflow: hidden;
    }

    .panel-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        padding: 20px 21px;
        border-bottom: 1px solid #eef2f7;
    }

    .panel-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 15px;
        font-weight: 800;
        color: #172033;
    }

    .panel-title-icon {
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        color: #4f46e5;
        background: #eef2ff;
    }

    .panel-subtitle {
        margin-top: 3px;
        font-size: 11px;
        color: #94a3b8;
    }

    .panel-body {
        padding: 21px;
    }

    .chart-area {
        position: relative;
        height: 265px;
        padding: 12px 8px 5px;
    }

    .chart-grid {
        position: absolute;
        inset: 20px 12px 34px 38px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .chart-grid-line {
        height: 1px;
        width: 100%;
        background: #eef2f7;
    }

    .fake-chart {
        position: absolute;
        left: 42px;
        right: 12px;
        top: 20px;
        bottom: 34px;
    }

    .chart-bars {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: flex-end;
        justify-content: space-around;
        gap: 10px;
    }

    .chart-bar-group {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: flex-end;
        justify-content: center;
        gap: 3px;
    }

    .chart-bar {
        width: 13px;
        min-height: 5px;
        border-radius: 5px 5px 2px 2px;
        background: #c7d2fe;
    }

    .chart-bar.main {
        background: #4f46e5;
    }

    .chart-labels {
        position: absolute;
        left: 42px;
        right: 12px;
        bottom: 4px;
        display: flex;
        justify-content: space-around;
        color: #94a3b8;
        font-size: 10px;
    }

    .y-labels {
        position: absolute;
        top: 17px;
        bottom: 32px;
        left: 0;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        font-size: 10px;
        color: #94a3b8;
    }

    .best-food-list {
        display: flex;
        flex-direction: column;
        gap: 13px;
    }

    .best-food {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px;
        border: 1px solid #f0f2f6;
        border-radius: 12px;
    }

    .best-food-image {
        width: 48px;
        height: 48px;
        flex: 0 0 48px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        background: #f8fafc;
        color: #94a3b8;
    }

    .best-food-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .best-food-info {
        min-width: 0;
        flex: 1;
    }

    .best-food-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 13px;
        font-weight: 700;
        color: #334155;
    }

    .best-food-meta {
        margin-top: 4px;
        color: #94a3b8;
        font-size: 11px;
    }

    .rank {
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        background: #f8fafc;
        color: #64748b;
        font-size: 11px;
        font-weight: 800;
    }

    .quick-tools {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-bottom: 24px;
    }

    .quick-tool {
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 65px;
        padding: 13px;
        text-decoration: none;
        border: 1px solid #e8edf5;
        border-radius: 14px;
        background: #fff;
        color: #334155;
        box-shadow: 0 5px 18px rgba(15,23,42,.04);
        transition: .2s;
    }

    .quick-tool:hover {
        color: #4f46e5;
        border-color: #c7d2fe;
        transform: translateY(-2px);
    }

    .quick-tool-icon {
        width: 38px;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 38px;
        border-radius: 10px;
        background: #f8fafc;
        color: #4f46e5;
    }

    .quick-tool-title {
        font-size: 12px;
        font-weight: 800;
    }

    .quick-tool-desc {
        margin-top: 2px;
        font-size: 10px;
        color: #94a3b8;
    }

    .filter-panel {
        margin-bottom: 20px;
    }

    .filter-body {
        padding: 20px;
    }

    .filter-grid {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr auto;
        gap: 12px;
        align-items: end;
    }

    .field-label {
        display: block;
        margin-bottom: 7px;
        color: #475569;
        font-size: 11px;
        font-weight: 800;
    }

    .field {
        width: 100%;
        min-height: 43px;
        border: 1px solid #dfe5ed;
        border-radius: 10px;
        padding: 0 12px;
        outline: none;
        color: #334155;
        background: #fff;
        font-size: 13px;
        transition: .2s;
    }

    .field:focus {
        border-color: #818cf8;
        box-shadow: 0 0 0 3px rgba(99,102,241,.10);
    }

    .search-wrap {
        position: relative;
    }

    .search-wrap .field {
        padding-left: 37px;
    }

    .search-icon {
        position: absolute;
        left: 13px;
        top: 14px;
        color: #94a3b8;
        font-size: 13px;
    }

    .btn {
        min-height: 43px;
        border: 0;
        border-radius: 10px;
        padding: 0 15px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        font-size: 12px;
        font-weight: 800;
        text-decoration: none;
        cursor: pointer;
        transition: .2s;
    }

    .btn-primary {
        color: #fff;
        background: #4f46e5;
    }

    .btn-primary:hover {
        color: #fff;
        background: #4338ca;
        transform: translateY(-1px);
    }

    .btn-light {
        color: #475569;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }

    .btn-light:hover {
        color: #334155;
        background: #f1f5f9;
    }

    .btn-danger {
        color: #fff;
        background: #dc2626;
    }

    .btn-success {
        color: #fff;
        background: #16a34a;
    }

    .btn-warning {
        color: #fff;
        background: #d97706;
    }

    .status-tabs {
        display: flex;
        gap: 7px;
        flex-wrap: wrap;
        margin-top: 15px;
    }

    .status-tab {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 35px;
        padding: 0 12px;
        border: 1px solid #e2e8f0;
        border-radius: 9px;
        background: #fff;
        color: #64748b;
        text-decoration: none;
        font-size: 11px;
        font-weight: 800;
    }

    .status-tab:hover,
    .status-tab.active {
        border-color: #c7d2fe;
        background: #eef2ff;
        color: #4338ca;
    }

    .orders-panel {
        overflow: hidden;
    }

    .orders-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    .toolbar-left,
    .toolbar-right {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .selected-count {
        display: none;
        padding: 7px 10px;
        border-radius: 8px;
        background: #eef2ff;
        color: #4338ca;
        font-size: 11px;
        font-weight: 800;
    }

    .table-wrap {
        overflow-x: auto;
    }

    .order-table {
        width: 100%;
        min-width: 1100px;
        border-collapse: collapse;
    }

    .order-table th {
        padding: 13px 15px;
        border-bottom: 1px solid #e8edf5;
        background: #fafbfc;
        color: #64748b;
        text-align: left;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .35px;
        white-space: nowrap;
    }

    .order-table td {
        padding: 15px;
        border-bottom: 1px solid #f0f2f6;
        vertical-align: middle;
        color: #334155;
        font-size: 12px;
    }

    .order-table tbody tr {
        transition: .15s;
    }

    .order-table tbody tr:hover {
        background: #fafbff;
    }

    .checkbox {
        width: 16px;
        height: 16px;
        cursor: pointer;
    }

    .order-code {
        color: #4f46e5;
        font-weight: 800;
        text-decoration: none;
    }

    .order-code:hover {
        color: #312e81;
    }

    .customer {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .customer-avatar {
        width: 36px;
        height: 36px;
        flex: 0 0 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        background: #eef2ff;
        color: #4f46e5;
        font-size: 13px;
        font-weight: 800;
    }

    .customer-name {
        color: #1e293b;
        font-weight: 800;
    }

    .customer-phone {
        margin-top: 3px;
        color: #94a3b8;
        font-size: 10px;
    }

    .food-count {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 8px;
        border-radius: 7px;
        background: #f8fafc;
        color: #64748b;
        font-weight: 700;
    }

    .money {
        color: #111827;
        font-weight: 800;
        white-space: nowrap;
    }

    .status-badge,
    .payment-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 9px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 800;
        white-space: nowrap;
    }

    .status-badge.pending { color: #92400e; background: #fef3c7; }
    .status-badge.confirmed { color: #1d4ed8; background: #dbeafe; }
    .status-badge.preparing { color: #7e22ce; background: #f3e8ff; }
    .status-badge.completed { color: #166534; background: #dcfce7; }
    .status-badge.cancelled { color: #991b1b; background: #fee2e2; }

    .payment-badge.paid { color: #166534; background: #dcfce7; }
    .payment-badge.unpaid { color: #92400e; background: #fef3c7; }
    .payment-badge.failed { color: #991b1b; background: #fee2e2; }

    .date-main {
        color: #475569;
        font-weight: 700;
    }

    .date-sub {
        margin-top: 3px;
        color: #94a3b8;
        font-size: 10px;
    }

    .row-actions {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
    }

    .icon-btn {
        width: 33px;
        height: 33px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #e2e8f0;
        border-radius: 9px;
        background: #fff;
        color: #64748b;
        text-decoration: none;
        cursor: pointer;
    }

    .icon-btn:hover {
        color: #4f46e5;
        border-color: #c7d2fe;
        background: #eef2ff;
    }

    .icon-btn.danger:hover {
        color: #dc2626;
        border-color: #fecaca;
        background: #fef2f2;
    }

    .pagination-area {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        padding: 17px 21px;
    }

    .pagination-note {
        color: #94a3b8;
        font-size: 11px;
    }

    .empty {
        padding: 70px 20px;
        text-align: center;
    }

    .empty-icon {
        width: 76px;
        height: 76px;
        margin: 0 auto 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: #f8fafc;
        color: #94a3b8;
        font-size: 29px;
    }

    .empty h3 {
        margin: 0;
        color: #334155;
        font-size: 17px;
    }

    .empty p {
        margin: 7px 0 0;
        color: #94a3b8;
        font-size: 12px;
    }

    .modal-backdrop-custom {
        position: fixed;
        inset: 0;
        z-index: 1050;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: rgba(15,23,42,.58);
        backdrop-filter: blur(3px);
    }

    .modal-backdrop-custom.show {
        display: flex;
    }

    .custom-modal {
        width: min(720px, 100%);
        max-height: 90vh;
        overflow: auto;
        border-radius: 20px;
        background: #fff;
        box-shadow: 0 30px 80px rgba(15,23,42,.28);
    }

    .modal-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        padding: 19px 21px;
        border-bottom: 1px solid #eef2f7;
    }

    .modal-title {
        font-size: 16px;
        font-weight: 800;
        color: #172033;
    }

    .modal-close {
        width: 34px;
        height: 34px;
        border: 0;
        border-radius: 9px;
        background: #f8fafc;
        color: #64748b;
        cursor: pointer;
    }

    .modal-body {
        padding: 21px;
    }

    .modal-foot {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        padding: 15px 21px;
        border-top: 1px solid #eef2f7;
    }

    .mini-order {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .mini-box {
        padding: 14px;
        border-radius: 12px;
        background: #f8fafc;
    }

    .mini-label {
        color: #94a3b8;
        font-size: 10px;
    }

    .mini-value {
        margin-top: 4px;
        color: #334155;
        font-size: 13px;
        font-weight: 800;
    }

    .toast-custom {
        position: fixed;
        right: 24px;
        bottom: 24px;
        z-index: 2000;
        min-width: 280px;
        max-width: 380px;
        padding: 14px 16px;
        display: none;
        align-items: center;
        gap: 10px;
        border: 1px solid #dbeafe;
        border-radius: 13px;
        background: #fff;
        box-shadow: 0 15px 40px rgba(15,23,42,.16);
    }

    .toast-custom.show {
        display: flex;
    }

    .toast-icon {
        width: 34px;
        height: 34px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 9px;
        background: #dcfce7;
        color: #16a34a;
    }

    .toast-title {
        font-size: 12px;
        font-weight: 800;
    }

    .toast-text {
        margin-top: 2px;
        color: #64748b;
        font-size: 11px;
    }

    @media (max-width: 1200px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .filter-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .filter-grid > div:last-child {
            grid-column: span 2;
        }
    }

    @media (max-width: 900px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }

        .quick-tools {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 650px) {
        .food-page {
            padding: 10px;
        }

        .food-hero {
            padding: 22px;
        }

        .food-hero-title {
            font-size: 23px;
        }

        .stats-grid,
        .quick-tools,
        .filter-grid {
            grid-template-columns: 1fr;
        }

        .filter-grid > div:last-child {
            grid-column: auto;
        }

        .hero-side {
            margin-top: 18px;
            width: 100%;
        }

        .mini-order {
            grid-template-columns: 1fr;
        }
    }
</style>

@php
    $statusLabels = [
        'pending' => 'Chờ xử lý',
        'confirmed' => 'Đã xác nhận',
        'preparing' => 'Đang chuẩn bị',
        'completed' => 'Hoàn thành',
        'cancelled' => 'Đã hủy',
    ];

    $statusIcons = [
        'pending' => 'bi-hourglass-split',
        'confirmed' => 'bi-check2-circle',
        'preparing' => 'bi-fire',
        'completed' => 'bi-check-circle',
        'cancelled' => 'bi-x-circle',
    ];

    $paymentLabels = [
        'paid' => 'Đã thanh toán',
        'unpaid' => 'Chưa thanh toán',
        'failed' => 'Thất bại',
    ];

    $totalOrdersValue = $totalOrders ?? (isset($orders) ? $orders->total() : 0);
    $pendingValue = $pendingOrders ?? 0;
    $completedValue = $completedOrders ?? 0;
    $revenueValue = $totalRevenue ?? 0;
@endphp

<div class="food-page">

    {{-- ===================== HERO ===================== --}}
    <section class="food-hero">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="food-hero-content">
                    <div class="food-breadcrumb">
                        <i class="bi bi-house"></i>
                        <span>Admin</span>
                        <i class="bi bi-chevron-right"></i>
                        <span>Đặt đồ ăn</span>
                    </div>

                    <h1 class="food-hero-title">
                        Quản lý đơn đồ ăn MovieMate
                    </h1>

                    <p class="food-hero-desc">
                        Theo dõi đơn hàng, kiểm tra thanh toán, xử lý món ăn
                        và cập nhật trạng thái phục vụ cho khách hàng tại rạp.
                    </p>

                    <div class="hero-actions">
                        <a href="#orders" class="hero-btn primary">
                            <i class="bi bi-list-ul"></i>
                            Xem danh sách đơn
                        </a>

                        @if(Route::has('admin.foods.index'))
                            <a href="{{ route('admin.foods.index') }}" class="hero-btn">
                                <i class="bi bi-cup-straw"></i>
                                Quản lý món ăn
                            </a>
                        @else
                            <a href="#" class="hero-btn">
                                <i class="bi bi-cup-straw"></i>
                                Quản lý món ăn
                            </a>
                        @endif

                        <button type="button" class="hero-btn" onclick="window.print()">
                            <i class="bi bi-printer"></i>
                            In báo cáo
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 mt-4 mt-lg-0">
                <div class="hero-side">
                    <div class="hero-side-label">Doanh thu hoàn thành</div>
                    <div class="hero-side-value">
                        {{ number_format($revenueValue, 0, ',', '.') }}đ
                    </div>
                    <div class="hero-side-small">
                        <i class="bi bi-graph-up-arrow"></i>
                        Cập nhật theo dữ liệu hệ thống
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===================== STATISTICS ===================== --}}
    <section class="stats-grid">

        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon blue">
                    <i class="bi bi-receipt-cutoff"></i>
                </div>
            </div>
            <div class="stat-title">TỔNG ĐƠN HÀNG</div>
            <div class="stat-value">{{ number_format($totalOrdersValue) }}</div>
            <div class="stat-change">
                <i class="bi bi-bar-chart"></i>
                Tất cả đơn trong hệ thống
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon orange">
                    <i class="bi bi-hourglass-split"></i>
                </div>
            </div>
            <div class="stat-title">CHỜ XỬ LÝ</div>
            <div class="stat-value">{{ number_format($pendingValue) }}</div>
            <div class="stat-change">
                <i class="bi bi-clock"></i>
                Cần nhân viên kiểm tra
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon green">
                    <i class="bi bi-check-circle"></i>
                </div>
            </div>
            <div class="stat-title">ĐÃ HOÀN THÀNH</div>
            <div class="stat-value">{{ number_format($completedValue) }}</div>
            <div class="stat-change positive">
                <i class="bi bi-arrow-up-right"></i>
                Đơn đã phục vụ xong
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon purple">
                    <i class="bi bi-cash-stack"></i>
                </div>
            </div>
            <div class="stat-title">DOANH THU ĐỒ ĂN</div>
            <div class="stat-value">
                {{ number_format($revenueValue, 0, ',', '.') }}đ
            </div>
            <div class="stat-change positive">
                <i class="bi bi-wallet2"></i>
                Tổng đơn hoàn thành
            </div>
        </div>

    </section>

    {{-- ===================== DASHBOARD ===================== --}}
    <section class="dashboard-grid">

        <div class="panel">
            <div class="panel-header">
                <div>
                    <div class="panel-title">
                        <span class="panel-title-icon">
                            <i class="bi bi-bar-chart-line"></i>
                        </span>
                        Doanh thu đồ ăn
                    </div>
                    <div class="panel-subtitle">
                        Biểu đồ minh họa doanh thu theo thời gian
                    </div>
                </div>

                <select class="field" style="width:auto;min-width:120px;">
                    <option>7 ngày</option>
                    <option>30 ngày</option>
                    <option>90 ngày</option>
                </select>
            </div>

            <div class="panel-body">
                <div class="chart-area">
                    <div class="y-labels">
                        <span>100%</span>
                        <span>75%</span>
                        <span>50%</span>
                        <span>25%</span>
                        <span>0%</span>
                    </div>

                    <div class="chart-grid">
                        <div class="chart-grid-line"></div>
                        <div class="chart-grid-line"></div>
                        <div class="chart-grid-line"></div>
                        <div class="chart-grid-line"></div>
                        <div class="chart-grid-line"></div>
                    </div>

                    <div class="fake-chart">
                        <div class="chart-bars">
                            <div class="chart-bar-group">
                                <span class="chart-bar" style="height:35%"></span>
                                <span class="chart-bar main" style="height:45%"></span>
                            </div>
                            <div class="chart-bar-group">
                                <span class="chart-bar" style="height:48%"></span>
                                <span class="chart-bar main" style="height:58%"></span>
                            </div>
                            <div class="chart-bar-group">
                                <span class="chart-bar" style="height:40%"></span>
                                <span class="chart-bar main" style="height:68%"></span>
                            </div>
                            <div class="chart-bar-group">
                                <span class="chart-bar" style="height:55%"></span>
                                <span class="chart-bar main" style="height:72%"></span>
                            </div>
                            <div class="chart-bar-group">
                                <span class="chart-bar" style="height:46%"></span>
                                <span class="chart-bar main" style="height:61%"></span>
                            </div>
                            <div class="chart-bar-group">
                                <span class="chart-bar" style="height:66%"></span>
                                <span class="chart-bar main" style="height:82%"></span>
                            </div>
                            <div class="chart-bar-group">
                                <span class="chart-bar" style="height:53%"></span>
                                <span class="chart-bar main" style="height:76%"></span>
                            </div>
                        </div>
                    </div>

                    <div class="chart-labels">
                        <span>T2</span>
                        <span>T3</span>
                        <span>T4</span>
                        <span>T5</span>
                        <span>T6</span>
                        <span>T7</span>
                        <span>CN</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <div>
                    <div class="panel-title">
                        <span class="panel-title-icon">
                            <i class="bi bi-trophy"></i>
                        </span>
                        Món bán chạy
                    </div>
                    <div class="panel-subtitle">
                        Danh sách món có lượt đặt cao
                    </div>
                </div>
            </div>

            <div class="panel-body">
                <div class="best-food-list">

                    @php
                        $bestFoods = $bestFoods ?? collect([
                            (object)['name' => 'Combo Bắp + Nước', 'sold' => 128, 'revenue' => 6400000],
                            (object)['name' => 'Bắp Rang Bơ', 'sold' => 96, 'revenue' => 2880000],
                            (object)['name' => 'Coca Cola', 'sold' => 82, 'revenue' => 1640000],
                            (object)['name' => 'Combo Couple', 'sold' => 64, 'revenue' => 3840000],
                            (object)['name' => 'Nước suối', 'sold' => 51, 'revenue' => 510000],
                        ]);
                    @endphp

                    @foreach($bestFoods->take(5) as $foodIndex => $food)
                        <div class="best-food">
                            <div class="rank">
                                #{{ $foodIndex + 1 }}
                            </div>

                            <div class="best-food-image">
                                @if(!empty($food->image))
                                    <img src="{{ asset($food->image) }}" alt="{{ $food->name }}">
                                @else
                                    <i class="bi bi-cup-straw"></i>
                                @endif
                            </div>

                            <div class="best-food-info">
                                <div class="best-food-name">
                                    {{ $food->name ?? 'Món ăn' }}
                                </div>
                                <div class="best-food-meta">
                                    {{ number_format($food->sold ?? 0) }} lượt bán
                                    ·
                                    {{ number_format($food->revenue ?? 0, 0, ',', '.') }}đ
                                </div>
                            </div>
                        </div>
                    @endforeach

                </div>
            </div>
        </div>

    </section>

    {{-- ===================== QUICK TOOLS ===================== --}}
    <section class="quick-tools">

        <a href="#orders" class="quick-tool">
            <span class="quick-tool-icon">
                <i class="bi bi-receipt"></i>
            </span>
            <span>
                <span class="quick-tool-title d-block">Đơn hàng</span>
                <span class="quick-tool-desc d-block">Xem toàn bộ đơn</span>
            </span>
        </a>

        <a href="#pending" class="quick-tool">
            <span class="quick-tool-icon">
                <i class="bi bi-hourglass"></i>
            </span>
            <span>
                <span class="quick-tool-title d-block">Chờ xử lý</span>
                <span class="quick-tool-desc d-block">Kiểm tra đơn mới</span>
            </span>
        </a>

        <a href="#statistics" class="quick-tool">
            <span class="quick-tool-icon">
                <i class="bi bi-graph-up"></i>
            </span>
            <span>
                <span class="quick-tool-title d-block">Báo cáo</span>
                <span class="quick-tool-desc d-block">Theo dõi doanh thu</span>
            </span>
        </a>

        <button type="button" class="quick-tool border-0 text-start" onclick="exportOrders()">
            <span class="quick-tool-icon">
                <i class="bi bi-file-earmark-spreadsheet"></i>
            </span>
            <span>
                <span class="quick-tool-title d-block">Xuất dữ liệu</span>
                <span class="quick-tool-desc d-block">Tải danh sách đơn</span>
            </span>
        </button>

    </section>

    {{-- ===================== FILTER ===================== --}}
    <section class="panel filter-panel" id="pending">

        <div class="panel-header">
            <div>
                <div class="panel-title">
                    <span class="panel-title-icon">
                        <i class="bi bi-funnel"></i>
                    </span>
                    Tìm kiếm và lọc đơn hàng
                </div>
                <div class="panel-subtitle">
                    Sử dụng bộ lọc để tìm nhanh đơn cần xử lý
                </div>
            </div>

            <button type="button" class="btn btn-light" onclick="resetFilters()">
                <i class="bi bi-arrow-counterclockwise"></i>
                Xóa lọc
            </button>
        </div>

        <div class="filter-body">

            <form method="GET" action="{{ request()->url() }}" id="filterForm">

                <div class="filter-grid">

                    <div>
                        <label class="field-label">TÌM KIẾM</label>
                        <div class="search-wrap">
                            <i class="bi bi-search search-icon"></i>
                            <input
                                type="text"
                                name="search"
                                id="searchInput"
                                class="field"
                                value="{{ request('search') }}"
                                placeholder="Mã đơn, tên khách hàng, số điện thoại..."
                            >
                        </div>
                    </div>

                    <div>
                        <label class="field-label">TRẠNG THÁI</label>
                        <select name="status" id="statusFilter" class="field">
                            <option value="">Tất cả</option>
                            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>
                                Chờ xử lý
                            </option>
                            <option value="confirmed" {{ request('status') === 'confirmed' ? 'selected' : '' }}>
                                Đã xác nhận
                            </option>
                            <option value="preparing" {{ request('status') === 'preparing' ? 'selected' : '' }}>
                                Đang chuẩn bị
                            </option>
                            <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>
                                Hoàn thành
                            </option>
                            <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>
                                Đã hủy
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="field-label">THANH TOÁN</label>
                        <select name="payment_status" id="paymentFilter" class="field">
                            <option value="">Tất cả</option>
                            <option value="paid" {{ request('payment_status') === 'paid' ? 'selected' : '' }}>
                                Đã thanh toán
                            </option>
                            <option value="unpaid" {{ request('payment_status') === 'unpaid' ? 'selected' : '' }}>
                                Chưa thanh toán
                            </option>
                            <option value="failed" {{ request('payment_status') === 'failed' ? 'selected' : '' }}>
                                Thất bại
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="field-label">SẮP XẾP</label>
                        <select name="sort" class="field">
                            <option value="newest" {{ request('sort', 'newest') === 'newest' ? 'selected' : '' }}>
                                Mới nhất
                            </option>
                            <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>
                                Cũ nhất
                            </option>
                            <option value="price_high" {{ request('sort') === 'price_high' ? 'selected' : '' }}>
                                Giá cao nhất
                            </option>
                            <option value="price_low" {{ request('sort') === 'price_low' ? 'selected' : '' }}>
                                Giá thấp nhất
                            </option>
                        </select>
                    </div>

                    <div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i>
                            Tìm kiếm
                        </button>
                    </div>

                </div>

            </form>

            <div class="status-tabs">

                <a
                    href="{{ request()->url() }}"
                    class="status-tab {{ !request('status') ? 'active' : '' }}"
                >
                    <i class="bi bi-grid"></i>
                    Tất cả
                </a>

                <a
                    href="{{ request()->url() }}?status=pending"
                    class="status-tab {{ request('status') === 'pending' ? 'active' : '' }}"
                >
                    <i class="bi bi-hourglass"></i>
                    Chờ xử lý
                </a>

                <a
                    href="{{ request()->url() }}?status=confirmed"
                    class="status-tab {{ request('status') === 'confirmed' ? 'active' : '' }}"
                >
                    <i class="bi bi-check2"></i>
                    Đã xác nhận
                </a>

                <a
                    href="{{ request()->url() }}?status=preparing"
                    class="status-tab {{ request('status') === 'preparing' ? 'active' : '' }}"
                >
                    <i class="bi bi-fire"></i>
                    Đang chuẩn bị
                </a>

                <a
                    href="{{ request()->url() }}?status=completed"
                    class="status-tab {{ request('status') === 'completed' ? 'active' : '' }}"
                >
                    <i class="bi bi-check-circle"></i>
                    Hoàn thành
                </a>

                <a
                    href="{{ request()->url() }}?status=cancelled"
                    class="status-tab {{ request('status') === 'cancelled' ? 'active' : '' }}"
                >
                    <i class="bi bi-x-circle"></i>
                    Đã hủy
                </a>

            </div>

        </div>
    </section>

    {{-- ===================== ORDER TABLE ===================== --}}
    <section class="panel orders-panel" id="orders">

        <div class="panel-header">
            <div>
                <div class="panel-title">
                    <span class="panel-title-icon">
                        <i class="bi bi-list-check"></i>
                    </span>
                    Danh sách đơn đặt đồ ăn
                </div>
                <div class="panel-subtitle">
                    Quản lý chi tiết đơn hàng và trạng thái phục vụ
                </div>
            </div>

            <div class="toolbar-right">
                <span class="selected-count" id="selectedCount">
                    0 đã chọn
                </span>

                <button type="button" class="btn btn-light" onclick="refreshOrders()">
                    <i class="bi bi-arrow-clockwise"></i>
                    Làm mới
                </button>
            </div>
        </div>

        @if(isset($orders) && $orders->count() > 0)

            <div class="panel-body" style="padding-bottom:10px;">
                <div class="orders-toolbar">

                    <div class="toolbar-left">
                        <button type="button" class="btn btn-light" onclick="selectAllOrders()">
                            <i class="bi bi-check-square"></i>
                            Chọn tất cả
                        </button>

                        <button type="button" class="btn btn-light" onclick="bulkConfirm()">
                            <i class="bi bi-check2-circle"></i>
                            Xác nhận đã chọn
                        </button>
                    </div>

                    <div class="toolbar-right">
                        <span style="font-size:11px;color:#94a3b8;">
                            {{ $orders->total() ?? $orders->count() }} đơn hàng
                        </span>
                    </div>

                </div>
            </div>

            <div class="table-wrap">

                <table class="order-table">

                    <thead>
                        <tr>
                            <th style="width:45px;">
                                <input
                                    type="checkbox"
                                    class="checkbox"
                                    id="masterCheckbox"
                                    onchange="toggleAll(this)"
                                >
                            </th>

                            <th>Mã đơn</th>
                            <th>Khách hàng</th>
                            <th>Số món</th>
                            <th>Tổng tiền</th>
                            <th>Thanh toán</th>
                            <th>Trạng thái</th>
                            <th>Thời gian</th>
                            <th style="text-align:center;">Thao tác</th>
                        </tr>
                    </thead>

                    <tbody id="ordersBody">

                    @foreach($orders as $order)

                        @php
                            $status = $order->status ?? 'pending';
                            $payment = $order->payment_status ?? 'unpaid';

                            $customerName =
                                $order->customer_name ??
                                optional($order->user)->name ??
                                'Khách hàng';

                            $customerPhone =
                                $order->customer_phone ??
                                optional($order->user)->phone ??
                                '';

                            $total =
                                $order->total_amount ??
                                $order->total ??
                                $order->amount ??
                                0;

                            $items = $order->items ?? $order->details ?? collect();

                            $itemCount = 0;

                            if (is_iterable($items)) {
                                foreach ($items as $item) {
                                    $itemCount += $item->quantity ?? 1;
                                }
                            }

                            $orderCode =
                                $order->order_code ??
                                $order->code ??
                                $order->id;
                        @endphp

                        <tr
                            data-order-id="{{ $order->id }}"
                            data-status="{{ $status }}"
                            data-payment="{{ $payment }}"
                        >

                            <td>
                                <input
                                    type="checkbox"
                                    class="checkbox order-checkbox"
                                    value="{{ $order->id }}"
                                    onchange="updateSelectedCount()"
                                >
                            </td>

                            <td>
                                <a
                                    href="{{ route('admin.food-orders.show', $order->id) }}"
                                    class="order-code"
                                >
                                    #{{ $orderCode }}
                                </a>
                            </td>

                            <td>
                                <div class="customer">
                                    <div class="customer-avatar">
                                        {{ strtoupper(mb_substr($customerName, 0, 1)) }}
                                    </div>

                                    <div>
                                        <div class="customer-name">
                                            {{ $customerName }}
                                        </div>

                                        @if($customerPhone)
                                            <div class="customer-phone">
                                                <i class="bi bi-telephone"></i>
                                                {{ $customerPhone }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <td>
                                <span class="food-count">
                                    <i class="bi bi-basket"></i>
                                    {{ $itemCount }}
                                </span>
                            </td>

                            <td>
                                <span class="money">
                                    {{ number_format($total, 0, ',', '.') }}đ
                                </span>
                            </td>

                            <td>
                                <span class="payment-badge {{ $payment }}">
                                    @if($payment === 'paid')
                                        <i class="bi bi-check-circle"></i>
                                    @elseif($payment === 'failed')
                                        <i class="bi bi-x-circle"></i>
                                    @else
                                        <i class="bi bi-clock"></i>
                                    @endif

                                    {{ $paymentLabels[$payment] ?? ucfirst($payment) }}
                                </span>
                            </td>

                            <td>
                                <span class="status-badge {{ $status }}">
                                    <i class="bi {{ $statusIcons[$status] ?? 'bi-circle' }}"></i>
                                    {{ $statusLabels[$status] ?? ucfirst($status) }}
                                </span>
                            </td>

                            <td>
                                @if($order->created_at)
                                    <div class="date-main">
                                        {{ $order->created_at->format('d/m/Y') }}
                                    </div>
                                    <div class="date-sub">
                                        {{ $order->created_at->format('H:i') }}
                                    </div>
                                @else
                                    <span style="color:#94a3b8;">—</span>
                                @endif
                            </td>

                            <td>
                                <div class="row-actions">

                                    <a
                                        href="{{ route('admin.food-orders.show', $order->id) }}"
                                        class="icon-btn"
                                        title="Xem chi tiết"
                                    >
                                        <i class="bi bi-eye"></i>
                                    </a>

                                    <button
                                        type="button"
                                        class="icon-btn"
                                        title="Xem nhanh"
                                        onclick="openQuickView({{ $order->id }})"
                                    >
                                        <i class="bi bi-search"></i>
                                    </button>

                                    @if(Route::has('admin.food-orders.edit'))
                                        <a
                                            href="{{ route('admin.food-orders.edit', $order->id) }}"
                                            class="icon-btn"
                                            title="Chỉnh sửa"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endif

                                    @if(Route::has('admin.food-orders.destroy'))
                                        <form
                                            method="POST"
                                            action="{{ route('admin.food-orders.destroy', $order->id) }}"
                                            onsubmit="return confirm('Bạn có chắc muốn xóa đơn hàng này?');"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <button
                                                type="submit"
                                                class="icon-btn danger"
                                                title="Xóa"
                                            >
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endif

                                </div>
                            </td>

                        </tr>

                    @endforeach

                    </tbody>

                </table>

            </div>

            <div class="pagination-area">

                <div class="pagination-note">
                    Hiển thị
                    <strong>{{ $orders->firstItem() ?? 1 }}</strong>
                    -
                    <strong>{{ $orders->lastItem() ?? $orders->count() }}</strong>
                    trong tổng số
                    <strong>{{ $orders->total() ?? $orders->count() }}</strong>
                    đơn
                </div>

                @if(method_exists($orders, 'links'))
                    <div>
                        {{ $orders->withQueryString()->links() }}
                    </div>
                @endif

            </div>

        @else

            <div class="empty">
                <div class="empty-icon">
                    <i class="bi bi-receipt"></i>
                </div>

                <h3>
                    Chưa có đơn đồ ăn
                </h3>

                <p>
                    Không tìm thấy đơn hàng phù hợp với điều kiện hiện tại.
                </p>

                <button
                    type="button"
                    class="btn btn-primary mt-3"
                    onclick="resetFilters()"
                >
                    <i class="bi bi-arrow-counterclockwise"></i>
                    Xóa bộ lọc
                </button>
            </div>

        @endif

    </section>

</div>

{{-- ===================== QUICK VIEW MODAL ===================== --}}
<div class="modal-backdrop-custom" id="quickViewModal">

    <div class="custom-modal">

        <div class="modal-head">
            <div class="modal-title">
                <i class="bi bi-receipt-cutoff me-2"></i>
                Xem nhanh đơn hàng
            </div>

            <button
                type="button"
                class="modal-close"
                onclick="closeModal('quickViewModal')"
            >
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="modal-body">

            <div class="mini-order">

                <div class="mini-box">
                    <div class="mini-label">MÃ ĐƠN</div>
                    <div class="mini-value" id="modalOrderCode">#---</div>
                </div>

                <div class="mini-box">
                    <div class="mini-label">TRẠNG THÁI</div>
                    <div class="mini-value" id="modalOrderStatus">---</div>
                </div>

                <div class="mini-box">
                    <div class="mini-label">KHÁCH HÀNG</div>
                    <div class="mini-value" id="modalCustomer">---</div>
                </div>

                <div class="mini-box">
                    <div class="mini-label">TỔNG TIỀN</div>
                    <div class="mini-value" id="modalTotal">---</div>
                </div>

            </div>

            <div style="margin-top:16px;padding:15px;border-radius:12px;background:#f8fafc;">
                <div style="font-size:11px;font-weight:800;color:#475569;">
                    THÔNG TIN
                </div>

                <div style="margin-top:7px;font-size:12px;color:#64748b;line-height:1.7;">
                    Nhấn nút "Xem chi tiết" để mở toàn bộ món ăn,
                    thông tin thanh toán, ghi chú và tiến trình đơn hàng.
                </div>
            </div>

        </div>

        <div class="modal-foot">

            <button
                type="button"
                class="btn btn-light"
                onclick="closeModal('quickViewModal')"
            >
                Đóng
            </button>

            <a
                href="#"
                id="modalDetailLink"
                class="btn btn-primary"
            >
                <i class="bi bi-eye"></i>
                Xem chi tiết
            </a>

        </div>

    </div>

</div>

{{-- ===================== TOAST ===================== --}}
<div class="toast-custom" id="foodToast">

    <div class="toast-icon">
        <i class="bi bi-check2"></i>
    </div>

    <div>
        <div class="toast-title" id="toastTitle">
            Thành công
        </div>

        <div class="toast-text" id="toastText">
            Thao tác đã được thực hiện.
        </div>
    </div>

</div>

<script>
    /* =========================================================
       MOVIEMATE FOOD ORDER JAVASCRIPT
       ========================================================= */

    function getCheckboxes() {
        return Array.from(document.querySelectorAll('.order-checkbox'));
    }

    function updateSelectedCount() {
        const checked = getCheckboxes().filter(item => item.checked);
        const countBox = document.getElementById('selectedCount');

        if (!countBox) {
            return;
        }

        if (checked.length > 0) {
            countBox.style.display = 'inline-flex';
            countBox.textContent = checked.length + ' đã chọn';
        } else {
            countBox.style.display = 'none';
        }

        const master = document.getElementById('masterCheckbox');

        if (master) {
            master.checked =
                checked.length > 0 &&
                checked.length === getCheckboxes().length;

            master.indeterminate =
                checked.length > 0 &&
                checked.length < getCheckboxes().length;
        }
    }

    function toggleAll(master) {
        getCheckboxes().forEach(function (checkbox) {
            checkbox.checked = master.checked;
        });

        updateSelectedCount();
    }

    function selectAllOrders() {
        const master = document.getElementById('masterCheckbox');

        if (master) {
            master.checked = true;
            toggleAll(master);
        }
    }

    function getSelectedIds() {
        return getCheckboxes()
            .filter(item => item.checked)
            .map(item => item.value);
    }

    function bulkConfirm() {
        const ids = getSelectedIds();

        if (ids.length === 0) {
            showToast(
                'Chưa chọn đơn',
                'Bạn hãy chọn ít nhất một đơn hàng.',
                false
            );
            return;
        }

        const confirmed = confirm(
            'Bạn có chắc muốn xác nhận ' +
            ids.length +
            ' đơn hàng đã chọn không?'
        );

        if (!confirmed) {
            return;
        }

        showToast(
            'Đã chọn đơn',
            'Bạn đã chọn ' + ids.length + ' đơn hàng để xử lý.'
        );
    }

    function refreshOrders() {
        window.location.reload();
    }

    function resetFilters() {
        const form = document.getElementById('filterForm');

        if (form) {
            const inputs = form.querySelectorAll('input, select');

            inputs.forEach(function (input) {
                if (input.tagName === 'SELECT') {
                    input.selectedIndex = 0;
                } else {
                    input.value = '';
                }
            });

            form.submit();
        } else {
            window.location.href = window.location.pathname;
        }
    }

    function closeModal(id) {
        const modal = document.getElementById(id);

        if (modal) {
            modal.classList.remove('show');
        }
    }

    function openModal(id) {
        const modal = document.getElementById(id);

        if (modal) {
            modal.classList.add('show');
        }
    }

    function openQuickView(orderId) {
        const row = document.querySelector(
            'tr[data-order-id="' + orderId + '"]'
        );

        if (!row) {
            return;
        }

        const code = row.querySelector('.order-code');
        const customer = row.querySelector('.customer-name');
        const money = row.querySelector('.money');
        const status = row.querySelector('.status-badge');

        document.getElementById('modalOrderCode').textContent =
            code ? code.textContent.trim() : '#' + orderId;

        document.getElementById('modalCustomer').textContent =
            customer ? customer.textContent.trim() : 'Khách hàng';

        document.getElementById('modalTotal').textContent =
            money ? money.textContent.trim() : '0đ';

        document.getElementById('modalOrderStatus').textContent =
            status ? status.textContent.trim() : 'Chưa xác định';

        const detailLink =
            document.getElementById('modalDetailLink');

        if (detailLink) {
            detailLink.href =
                "{{ url('/admin/food-orders') }}/" + orderId;
        }

        openModal('quickViewModal');
    }

    function showToast(title, text, success = true) {
        const toast = document.getElementById('foodToast');
        const titleBox = document.getElementById('toastTitle');
        const textBox = document.getElementById('toastText');
        const icon = toast.querySelector('.toast-icon');

        titleBox.textContent = title;
        textBox.textContent = text;

        if (success) {
            icon.style.background = '#dcfce7';
            icon.style.color = '#16a34a';
            icon.innerHTML = '<i class="bi bi-check2"></i>';
        } else {
            icon.style.background = '#fee2e2';
            icon.style.color = '#dc2626';
            icon.innerHTML = '<i class="bi bi-exclamation-triangle"></i>';
        }

        toast.classList.add('show');

        setTimeout(function () {
            toast.classList.remove('show');
        }, 3000);
    }

    function exportOrders() {
        const table = document.querySelector('.order-table');

        if (!table) {
            showToast(
                'Không có dữ liệu',
                'Không có bảng đơn hàng để xuất.',
                false
            );
            return;
        }

        let csv = [];
        const rows = table.querySelectorAll('tr');

        rows.forEach(function (row) {
            const cells = row.querySelectorAll('th, td');

            const values = Array.from(cells).map(function (cell) {
                return '"' +
                    cell.innerText
                        .replace(/\s+/g, ' ')
                        .replace(/"/g, '""')
                        .trim() +
                    '"';
            });

            csv.push(values.join(','));
        });

        const blob = new Blob(
            ['\ufeff' + csv.join('\n')],
            { type: 'text/csv;charset=utf-8;' }
        );

        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');

        link.href = url;
        link.download =
            'moviemate-food-orders-' +
            new Date().toISOString().slice(0, 10) +
            '.csv';

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        URL.revokeObjectURL(url);

        showToast(
            'Xuất dữ liệu thành công',
            'File CSV đã được tạo trên trình duyệt.'
        );
    }

    document.addEventListener('DOMContentLoaded', function () {

        const searchInput =
            document.getElementById('searchInput');

        if (searchInput) {
            searchInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();

                    const form =
                        document.getElementById('filterForm');

                    if (form) {
                        form.submit();
                    }
                }
            });
        }

        const modal = document.getElementById('quickViewModal');

        if (modal) {
            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModal('quickViewModal');
                }
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeModal('quickViewModal');
            }
        });

    });
</script>

@endsection
