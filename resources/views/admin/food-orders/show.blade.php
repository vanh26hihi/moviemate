@extends('layouts.admin')

@section('title', 'Chi tiết đơn đồ ăn')

@section('content')
<style>
    /* =========================================================
       MOVIEMATE - FOOD ORDER ADMIN
       SHOW / DETAIL PAGE
       ========================================================= */

    .food-detail-page {
        min-height: 100vh;
        padding: 20px;
        background: #f5f7fb;
        color: #172033;
    }

    .detail-top {
        margin-bottom: 20px;
    }

    .back-link {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        color: #64748b;
        text-decoration: none;
        font-size: 12px;
        font-weight: 700;
    }

    .back-link:hover {
        color: #4f46e5;
    }

    .order-head {
        margin-top: 13px;
        padding: 25px;
        border: 1px solid #e8edf5;
        border-radius: 20px;
        background: #fff;
        box-shadow: 0 8px 28px rgba(15,23,42,.05);
    }

    .order-head-left {
        min-width: 0;
    }

    .order-kicker {
        color: #94a3b8;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .4px;
    }

    .order-title {
        margin: 5px 0 0;
        color: #111827;
        font-size: 27px;
        font-weight: 850;
        letter-spacing: -.4px;
    }

    .order-subtitle {
        margin-top: 7px;
        color: #94a3b8;
        font-size: 12px;
    }

    .status-large {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 13px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
        white-space: nowrap;
    }

    .status-large.pending { color:#92400e; background:#fef3c7; }
    .status-large.confirmed { color:#1d4ed8; background:#dbeafe; }
    .status-large.preparing { color:#7e22ce; background:#f3e8ff; }
    .status-large.completed { color:#166534; background:#dcfce7; }
    .status-large.cancelled { color:#991b1b; background:#fee2e2; }

    .detail-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.7fr) minmax(300px, .8fr);
        gap: 20px;
        align-items: start;
    }

    .detail-card {
        margin-bottom: 20px;
        border: 1px solid #e8edf5;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 7px 25px rgba(15,23,42,.05);
        overflow: hidden;
    }

    .detail-card:last-child {
        margin-bottom: 0;
    }

    .card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 18px 20px;
        border-bottom: 1px solid #eef2f7;
    }

    .card-title {
        display: flex;
        align-items: center;
        gap: 9px;
        color: #172033;
        font-size: 14px;
        font-weight: 800;
    }

    .card-icon {
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        color: #4f46e5;
        background: #eef2ff;
    }

    .card-desc {
        margin-top: 3px;
        color: #94a3b8;
        font-size: 10px;
    }

    .card-body {
        padding: 20px;
    }

    .customer-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .info-box {
        padding: 14px;
        border: 1px solid #eef2f7;
        border-radius: 12px;
        background: #fbfcfe;
    }

    .info-label {
        color: #94a3b8;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .info-value {
        margin-top: 5px;
        color: #334155;
        font-size: 13px;
        font-weight: 800;
        word-break: break-word;
    }

    .info-value.light {
        font-weight: 600;
    }

    .items-wrap {
        overflow-x: auto;
    }

    .items-table {
        width: 100%;
        min-width: 760px;
        border-collapse: collapse;
    }

    .items-table th {
        padding: 12px 15px;
        border-bottom: 1px solid #e8edf5;
        background: #fafbfc;
        color: #64748b;
        font-size: 10px;
        font-weight: 800;
        text-align: left;
        text-transform: uppercase;
    }

    .items-table td {
        padding: 15px;
        border-bottom: 1px solid #f0f2f6;
        vertical-align: middle;
        color: #334155;
        font-size: 12px;
    }

    .food-cell {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .food-img {
        width: 60px;
        height: 60px;
        flex: 0 0 60px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 11px;
        background: #f1f5f9;
        color: #94a3b8;
        font-size: 20px;
    }

    .food-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .food-name {
        color: #1e293b;
        font-size: 13px;
        font-weight: 800;
    }

    .food-meta {
        margin-top: 4px;
        color: #94a3b8;
        font-size: 10px;
    }

    .quantity {
        display: inline-flex;
        min-width: 35px;
        min-height: 31px;
        align-items: center;
        justify-content: center;
        padding: 0 9px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #f8fafc;
        color: #475569;
        font-weight: 800;
    }

    .price {
        color: #475569;
        font-weight: 700;
        white-space: nowrap;
    }

    .subtotal {
        color: #111827;
        font-weight: 850;
        white-space: nowrap;
    }

    .summary {
        padding: 16px;
        border-radius: 13px;
        background: #f8fafc;
    }

    .summary-line {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        padding: 8px 0;
        color: #64748b;
        font-size: 12px;
    }

    .summary-line strong {
        color: #334155;
    }

    .summary-divider {
        height: 1px;
        margin: 8px 0;
        background: #e2e8f0;
    }

    .summary-total {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        padding-top: 10px;
    }

    .summary-total span {
        color: #334155;
        font-size: 14px;
        font-weight: 800;
    }

    .summary-total strong {
        color: #4f46e5;
        font-size: 21px;
        font-weight: 900;
        white-space: nowrap;
    }

    .payment-box {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px;
        border-radius: 12px;
    }

    .payment-box.paid {
        border: 1px solid #bbf7d0;
        background: #f0fdf4;
    }

    .payment-box.unpaid {
        border: 1px solid #fde68a;
        background: #fffbeb;
    }

    .payment-box.failed {
        border: 1px solid #fecaca;
        background: #fef2f2;
    }

    .payment-icon {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        background: #fff;
    }

    .payment-box.paid .payment-icon { color:#16a34a; }
    .payment-box.unpaid .payment-icon { color:#d97706; }
    .payment-box.failed .payment-icon { color:#dc2626; }

    .payment-title {
        color: #334155;
        font-size: 12px;
        font-weight: 800;
    }

    .payment-desc {
        margin-top: 3px;
        color: #64748b;
        font-size: 10px;
    }

    .status-box {
        padding: 17px;
        border-radius: 13px;
        background: #f8fafc;
    }

    .status-select {
        width: 100%;
        min-height: 43px;
        padding: 0 11px;
        border: 1px solid #dfe5ed;
        border-radius: 10px;
        outline: none;
        background: #fff;
        color: #334155;
        font-size: 12px;
    }

    .status-select:focus {
        border-color: #818cf8;
        box-shadow: 0 0 0 3px rgba(99,102,241,.10);
    }

    .btn {
        min-height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        padding: 0 14px;
        border: 0;
        border-radius: 10px;
        font-size: 11px;
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
    }

    .btn-light {
        color: #475569;
        border: 1px solid #e2e8f0;
        background: #fff;
    }

    .btn-light:hover {
        color: #334155;
        background: #f8fafc;
    }

    .btn-danger {
        color: #fff;
        background: #dc2626;
    }

    .btn-success {
        color: #fff;
        background: #16a34a;
    }

    .w-full {
        width: 100%;
    }

    .timeline {
        position: relative;
        padding-left: 31px;
    }

    .timeline::before {
        content: "";
        position: absolute;
        left: 8px;
        top: 8px;
        bottom: 8px;
        width: 2px;
        background: #e2e8f0;
    }

    .timeline-item {
        position: relative;
        padding-bottom: 22px;
    }

    .timeline-item:last-child {
        padding-bottom: 0;
    }

    .timeline-dot {
        position: absolute;
        left: -31px;
        top: 1px;
        width: 18px;
        height: 18px;
        border: 4px solid #fff;
        border-radius: 50%;
        background: #e2e8f0;
        box-shadow: 0 0 0 1px #cbd5e1;
    }

    .timeline-item.active .timeline-dot {
        background: #4f46e5;
        box-shadow: 0 0 0 1px #6366f1;
    }

    .timeline-item.active::after {
        content: "";
        position: absolute;
        left: -26px;
        top: 6px;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #fff;
        opacity: .9;
    }

    .timeline-title {
        color: #334155;
        font-size: 12px;
        font-weight: 800;
    }

    .timeline-text {
        margin-top: 4px;
        color: #94a3b8;
        font-size: 10px;
        line-height: 1.5;
    }

    .note {
        padding: 14px;
        border: 1px solid #fde68a;
        border-radius: 12px;
        background: #fffbeb;
        color: #92400e;
        font-size: 11px;
        line-height: 1.7;
    }

    .danger-note {
        padding: 14px;
        border: 1px solid #fecaca;
        border-radius: 12px;
        background: #fef2f2;
        color: #991b1b;
        font-size: 11px;
        line-height: 1.7;
    }

    .action-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 9px;
    }

    .metadata-list {
        display: flex;
        flex-direction: column;
        gap: 0;
    }

    .metadata-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        padding: 11px 0;
        border-bottom: 1px solid #f0f2f6;
        font-size: 11px;
    }

    .metadata-row:last-child {
        border-bottom: 0;
        padding-bottom: 0;
    }

    .metadata-row:first-child {
        padding-top: 0;
    }

    .metadata-label {
        color: #94a3b8;
    }

    .metadata-value {
        color: #475569;
        font-weight: 800;
        text-align: right;
    }

    .print-summary {
        display: none;
    }

    @media print {
        .food-detail-page {
            padding: 0;
            background: #fff;
        }

        .no-print {
            display: none !important;
        }

        .detail-layout {
            display: block;
        }

        .detail-card {
            box-shadow: none;
            break-inside: avoid;
        }

        .print-summary {
            display: block;
        }
    }

    @media (max-width: 1000px) {
        .detail-layout {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 650px) {
        .food-detail-page {
            padding: 10px;
        }

        .order-head {
            padding: 18px;
        }

        .order-title {
            font-size: 21px;
        }

        .customer-grid {
            grid-template-columns: 1fr;
        }

        .action-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

@php
    $status = $order->status ?? 'pending';

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

    $paymentStatus = $order->payment_status ?? 'unpaid';

    $paymentLabels = [
        'paid' => 'Đã thanh toán',
        'unpaid' => 'Chưa thanh toán',
        'failed' => 'Thanh toán thất bại',
    ];

    $items = $order->items ?? $order->details ?? collect();

    $subtotal = 0;

    foreach ($items as $item) {
        $food = $item->food ?? $item->foodItem ?? null;

        $itemPrice =
            $item->price ??
            $item->unit_price ??
            optional($food)->price ??
            0;

        $itemQuantity = $item->quantity ?? 1;

        $subtotal += $itemPrice * $itemQuantity;
    }

    $serviceFee = $order->service_fee ?? 0;
    $discount = $order->discount ?? 0;

    $totalAmount =
        $order->total_amount ??
        $order->total ??
        $order->amount ??
        ($subtotal + $serviceFee - $discount);

    $orderCode =
        $order->order_code ??
        $order->code ??
        $order->id;

    $customerName =
        $order->customer_name ??
        optional($order->user)->name ??
        'Khách hàng';

    $customerPhone =
        $order->customer_phone ??
        optional($order->user)->phone ??
        'Chưa cập nhật';

    $customerEmail =
        $order->customer_email ??
        optional($order->user)->email ??
        'Chưa cập nhật';
@endphp

<div class="food-detail-page">

    {{-- ===================== HEADER ===================== --}}
    <div class="detail-top no-print">

        <a
            href="{{ route('admin.food-orders.index') }}"
            class="back-link"
        >
            <i class="bi bi-arrow-left"></i>
            Quay lại danh sách đơn
        </a>

    </div>

    <section class="order-head">

        <div class="row align-items-center">

            <div class="col-md-8">

                <div class="order-head-left">

                    <div class="order-kicker">
                        Chi tiết đơn đặt đồ ăn
                    </div>

                    <h1 class="order-title">
                        Đơn hàng #{{ $orderCode }}
                    </h1>

                    <div class="order-subtitle">

                        @if($order->created_at)
                            Đặt lúc
                            <strong>
                                {{ $order->created_at->format('H:i - d/m/Y') }}
                            </strong>
                        @endif

                        @if($order->updated_at)
                            · Cập nhật
                            <strong>
                                {{ $order->updated_at->format('H:i - d/m/Y') }}
                            </strong>
                        @endif

                    </div>

                </div>

            </div>

            <div class="col-md-4 mt-3 mt-md-0">

                <div class="d-flex justify-content-md-end align-items-center gap-2 flex-wrap">

                    <span class="status-large {{ $status }}">
                        <i class="bi {{ $statusIcons[$status] ?? 'bi-circle' }}"></i>
                        {{ $statusLabels[$status] ?? ucfirst($status) }}
                    </span>

                    <button
                        type="button"
                        class="btn btn-light no-print"
                        onclick="window.print()"
                    >
                        <i class="bi bi-printer"></i>
                        In đơn
                    </button>

                </div>

            </div>

        </div>

    </section>

    {{-- ===================== MAIN LAYOUT ===================== --}}
    <div class="detail-layout mt-4">

        {{-- ===================== LEFT ===================== --}}
        <main>

            {{-- CUSTOMER --}}
            <section class="detail-card">

                <div class="card-head">

                    <div>

                        <div class="card-title">

                            <span class="card-icon">
                                <i class="bi bi-person"></i>
                            </span>

                            Thông tin khách hàng

                        </div>

                        <div class="card-desc">
                            Thông tin người đặt đơn đồ ăn
                        </div>

                    </div>

                </div>

                <div class="card-body">

                    <div class="customer-grid">

                        <div class="info-box">

                            <div class="info-label">
                                Họ và tên
                            </div>

                            <div class="info-value">
                                {{ $customerName }}
                            </div>

                        </div>

                        <div class="info-box">

                            <div class="info-label">
                                Số điện thoại
                            </div>

                            <div class="info-value">
                                {{ $customerPhone }}
                            </div>

                        </div>

                        <div class="info-box">

                            <div class="info-label">
                                Email
                            </div>

                            <div class="info-value light">
                                {{ $customerEmail }}
                            </div>

                        </div>

                        <div class="info-box">

                            <div class="info-label">
                                Tài khoản
                            </div>

                            <div class="info-value">

                                @if($order->user)
                                    {{ $order->user->name }}
                                @else
                                    Khách vãng lai
                                @endif

                            </div>

                        </div>

                    </div>

                </div>

            </section>

            {{-- FOOD ITEMS --}}
            <section class="detail-card">

                <div class="card-head">

                    <div>

                        <div class="card-title">

                            <span class="card-icon">
                                <i class="bi bi-basket"></i>
                            </span>

                            Danh sách món ăn

                        </div>

                        <div class="card-desc">
                            Các món khách hàng đã đặt trong đơn
                        </div>

                    </div>

                    <span class="badge bg-light text-secondary">
                        {{ $items->count() }} loại món
                    </span>

                </div>

                @if($items->count() > 0)

                    <div class="items-wrap">

                        <table class="items-table">

                            <thead>

                                <tr>
                                    <th>Món ăn</th>
                                    <th>Số lượng</th>
                                    <th>Đơn giá</th>
                                    <th>Thành tiền</th>
                                </tr>

                            </thead>

                            <tbody>

                            @foreach($items as $item)

                                @php

                                    $food =
                                        $item->food ??
                                        $item->foodItem ??
                                        null;

                                    $itemName =
                                        $item->food_name ??
                                        $item->name ??
                                        optional($food)->name ??
                                        'Món ăn';

                                    $itemImage =
                                        $item->image ??
                                        optional($food)->image ??
                                        optional($food)->thumbnail ??
                                        null;

                                    $itemPrice =
                                        $item->price ??
                                        $item->unit_price ??
                                        optional($food)->price ??
                                        0;

                                    $quantity =
                                        $item->quantity ??
                                        1;

                                    $lineTotal =
                                        $itemPrice * $quantity;

                                @endphp

                                <tr>

                                    <td>

                                        <div class="food-cell">

                                            <div class="food-img">

                                                @if($itemImage)

                                                    <img
                                                        src="{{ asset($itemImage) }}"
                                                        alt="{{ $itemName }}"
                                                        onerror="this.style.display='none';"
                                                    >

                                                @else

                                                    <i class="bi bi-cup-straw"></i>

                                                @endif

                                            </div>

                                            <div>

                                                <div class="food-name">
                                                    {{ $itemName }}
                                                </div>

                                                <div class="food-meta">

                                                    @if($item->description ?? false)
                                                        {{ $item->description }}
                                                    @else
                                                        Đồ ăn / thức uống tại rạp
                                                    @endif

                                                </div>

                                            </div>

                                        </div>

                                    </td>

                                    <td>

                                        <span class="quantity">
                                            x{{ $quantity }}
                                        </span>

                                    </td>

                                    <td>

                                        <span class="price">
                                            {{ number_format($itemPrice, 0, ',', '.') }}đ
                                        </span>

                                    </td>

                                    <td>

                                        <span class="subtotal">
                                            {{ number_format($lineTotal, 0, ',', '.') }}đ
                                        </span>

                                    </td>

                                </tr>

                            @endforeach

                            </tbody>

                        </table>

                    </div>

                @else

                    <div class="card-body">

                        <div style="padding:45px 10px;text-align:center;">

                            <div style="font-size:32px;color:#cbd5e1;">
                                <i class="bi bi-basket"></i>
                            </div>

                            <div style="margin-top:10px;color:#64748b;font-size:13px;font-weight:700;">
                                Đơn hàng chưa có món ăn
                            </div>

                        </div>

                    </div>

                @endif

            </section>

            {{-- NOTE --}}
            @if(
                !empty($order->note) ||
                !empty($order->customer_note) ||
                !empty($order->notes)
            )

                <section class="detail-card">

                    <div class="card-head">

                        <div class="card-title">

                            <span class="card-icon">
                                <i class="bi bi-chat-left-text"></i>
                            </span>

                            Ghi chú của khách hàng

                        </div>

                    </div>

                    <div class="card-body">

                        <div class="note">

                            <i class="bi bi-info-circle me-1"></i>

                            {{ $order->note ?? $order->customer_note ?? $order->notes }}

                        </div>

                    </div>

                </section>

            @endif

            {{-- PRINT SUMMARY --}}
            <section class="detail-card print-summary">

                <div class="card-head">
                    <div class="card-title">
                        Tổng thanh toán
                    </div>
                </div>

                <div class="card-body">

                    <strong>
                        {{ number_format($totalAmount, 0, ',', '.') }}đ
                    </strong>

                </div>

            </section>

        </main>

        {{-- ===================== RIGHT ===================== --}}
        <aside>

            {{-- UPDATE STATUS --}}
            <section class="detail-card no-print">

                <div class="card-head">

                    <div>

                        <div class="card-title">

                            <span class="card-icon">
                                <i class="bi bi-arrow-repeat"></i>
                            </span>

                            Cập nhật trạng thái

                        </div>

                        <div class="card-desc">
                            Điều chỉnh tiến trình phục vụ
                        </div>

                    </div>

                </div>

                <div class="card-body">

                    <div class="status-box">

                        @if(Route::has('admin.food-orders.update-status'))

                            <form
                                action="{{ route('admin.food-orders.update-status', $order->id) }}"
                                method="POST"
                                id="statusForm"
                            >

                                @csrf
                                @method('PATCH')

                                <label
                                    for="status"
                                    style="display:block;margin-bottom:7px;color:#475569;font-size:10px;font-weight:800;"
                                >
                                    TRẠNG THÁI MỚI
                                </label>

                                <select
                                    name="status"
                                    id="status"
                                    class="status-select"
                                >

                                    <option
                                        value="pending"
                                        {{ $status === 'pending' ? 'selected' : '' }}
                                    >
                                        Chờ xử lý
                                    </option>

                                    <option
                                        value="confirmed"
                                        {{ $status === 'confirmed' ? 'selected' : '' }}
                                    >
                                        Đã xác nhận
                                    </option>

                                    <option
                                        value="preparing"
                                        {{ $status === 'preparing' ? 'selected' : '' }}
                                    >
                                        Đang chuẩn bị
                                    </option>

                                    <option
                                        value="completed"
                                        {{ $status === 'completed' ? 'selected' : '' }}
                                    >
                                        Hoàn thành
                                    </option>

                                    <option
                                        value="cancelled"
                                        {{ $status === 'cancelled' ? 'selected' : '' }}
                                    >
                                        Đã hủy
                                    </option>

                                </select>

                                <button
                                    type="submit"
                                    class="btn btn-primary w-full"
                                    style="margin-top:10px;"
                                >
                                    <i class="bi bi-check2-circle"></i>
                                    Lưu trạng thái
                                </button>

                            </form>

                        @else

                            <div style="font-size:11px;color:#64748b;line-height:1.6;">
                                Route cập nhật trạng thái chưa được cấu hình.
                            </div>

                        @endif

                    </div>

                </div>

            </section>

            {{-- SUMMARY --}}
            <section class="detail-card">

                <div class="card-head">

                    <div class="card-title">

                        <span class="card-icon">
                            <i class="bi bi-calculator"></i>
                        </span>

                        Tổng thanh toán

                    </div>

                </div>

                <div class="card-body">

                    <div class="summary">

                        <div class="summary-line">

                            <span>
                                Tiền món ăn
                            </span>

                            <strong>
                                {{ number_format($subtotal, 0, ',', '.') }}đ
                            </strong>

                        </div>

                        <div class="summary-line">

                            <span>
                                Phí dịch vụ
                            </span>

                            <strong>
                                {{ number_format($serviceFee, 0, ',', '.') }}đ
                            </strong>

                        </div>

                        <div class="summary-line">

                            <span>
                                Giảm giá
                            </span>

                            <strong>
                                -{{ number_format($discount, 0, ',', '.') }}đ
                            </strong>

                        </div>

                        <div class="summary-divider"></div>

                        <div class="summary-total">

                            <span>
                                Tổng cộng
                            </span>

                            <strong>
                                {{ number_format($totalAmount, 0, ',', '.') }}đ
                            </strong>

                        </div>

                    </div>

                </div>

            </section>

            {{-- PAYMENT --}}
            <section class="detail-card">

                <div class="card-head">

                    <div class="card-title">

                        <span class="card-icon">
                            <i class="bi bi-credit-card"></i>
                        </span>

                        Thanh toán

                    </div>

                </div>

                <div class="card-body">

                    <div class="payment-box {{ $paymentStatus }}">

                        <div class="payment-icon">

                            @if($paymentStatus === 'paid')
                                <i class="bi bi-check-circle-fill"></i>
                            @elseif($paymentStatus === 'failed')
                                <i class="bi bi-x-circle-fill"></i>
                            @else
                                <i class="bi bi-clock-fill"></i>
                            @endif

                        </div>

                        <div>

                            <div class="payment-title">
                                {{ $paymentLabels[$paymentStatus] ?? ucfirst($paymentStatus) }}
                            </div>

                            <div class="payment-desc">

                                @if($paymentStatus === 'paid')
                                    Giao dịch đã được xác nhận.
                                @elseif($paymentStatus === 'failed')
                                    Giao dịch chưa thành công.
                                @else
                                    Đang chờ khách hàng thanh toán.
                                @endif

                            </div>

                        </div>

                    </div>

                    <div class="metadata-list" style="margin-top:15px;">

                        <div class="metadata-row">

                            <span class="metadata-label">
                                Phương thức
                            </span>

                            <span class="metadata-value">
                                {{ $order->payment_method ?? 'Chưa xác định' }}
                            </span>

                        </div>

                        <div class="metadata-row">

                            <span class="metadata-label">
                                Mã giao dịch
                            </span>

                            <span class="metadata-value">
                                {{ $order->transaction_id ?? '—' }}
                            </span>

                        </div>

                    </div>

                </div>

            </section>

            {{-- TIMELINE --}}
            <section class="detail-card">

                <div class="card-head">

                    <div>

                        <div class="card-title">

                            <span class="card-icon">
                                <i class="bi bi-clock-history"></i>
                            </span>

                            Tiến trình đơn

                        </div>

                        <div class="card-desc">
                            Trạng thái xử lý đơn hàng
                        </div>

                    </div>

                </div>

                <div class="card-body">

                    <div class="timeline">

                        <div class="timeline-item
                            {{ in_array($status, ['pending','confirmed','preparing','completed']) ? 'active' : '' }}
                        ">

                            <div class="timeline-dot"></div>

                            <div class="timeline-title">
                                Tiếp nhận đơn
                            </div>

                            <div class="timeline-text">
                                Hệ thống đã ghi nhận yêu cầu đặt đồ ăn.
                            </div>

                        </div>

                        <div class="timeline-item
                            {{ in_array($status, ['confirmed','preparing','completed']) ? 'active' : '' }}
                        ">

                            <div class="timeline-dot"></div>

                            <div class="timeline-title">
                                Xác nhận đơn
                            </div>

                            <div class="timeline-text">
                                Nhân viên rạp xác nhận các món trong đơn.
                            </div>

                        </div>

                        <div class="timeline-item
                            {{ in_array($status, ['preparing','completed']) ? 'active' : '' }}
                        ">

                            <div class="timeline-dot"></div>

                            <div class="timeline-title">
                                Đang chuẩn bị
                            </div>

                            <div class="timeline-text">
                                Nhân viên đang chuẩn bị đồ ăn và thức uống.
                            </div>

                        </div>

                        <div class="timeline-item
                            {{ $status === 'completed' ? 'active' : '' }}
                        ">

                            <div class="timeline-dot"></div>

                            <div class="timeline-title">
                                Hoàn thành
                            </div>

                            <div class="timeline-text">
                                Đơn đã được phục vụ cho khách hàng.
                            </div>

                        </div>

                    </div>

                    @if($status === 'cancelled')

                        <div class="danger-note" style="margin-top:18px;">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Đơn hàng này đã bị hủy.
                        </div>

                    @endif

                </div>

            </section>

            {{-- ORDER METADATA --}}
            <section class="detail-card">

                <div class="card-head">

                    <div class="card-title">

                        <span class="card-icon">
                            <i class="bi bi-info-circle"></i>
                        </span>

                        Thông tin đơn

                    </div>

                </div>

                <div class="card-body">

                    <div class="metadata-list">

                        <div class="metadata-row">

                            <span class="metadata-label">
                                Mã đơn
                            </span>

                            <span class="metadata-value">
                                #{{ $orderCode }}
                            </span>

                        </div>

                        <div class="metadata-row">

                            <span class="metadata-label">
                                ID
                            </span>

                            <span class="metadata-value">
                                {{ $order->id }}
                            </span>

                        </div>

                        <div class="metadata-row">

                            <span class="metadata-label">
                                Ngày tạo
                            </span>

                            <span class="metadata-value">

                                @if($order->created_at)
                                    {{ $order->created_at->format('d/m/Y H:i') }}
                                @else
                                    —
                                @endif

                            </span>

                        </div>

                        <div class="metadata-row">

                            <span class="metadata-label">
                                Cập nhật
                            </span>

                            <span class="metadata-value">

                                @if($order->updated_at)
                                    {{ $order->updated_at->format('d/m/Y H:i') }}
                                @else
                                    —
                                @endif

                            </span>

                        </div>

                    </div>

                </div>

            </section>

            {{-- ACTIONS --}}
            <section class="detail-card no-print">

                <div class="card-head">

                    <div class="card-title">

                        <span class="card-icon">
                            <i class="bi bi-lightning"></i>
                        </span>

                        Thao tác nhanh

                    </div>

                </div>

                <div class="card-body">

                    <div class="action-grid">

                        <a
                            href="{{ route('admin.food-orders.index') }}"
                            class="btn btn-light"
                        >
                            <i class="bi bi-arrow-left"></i>
                            Danh sách
                        </a>

                        @if(Route::has('admin.food-orders.edit'))

                            <a
                                href="{{ route('admin.food-orders.edit', $order->id) }}"
                                class="btn btn-primary"
                            >
                                <i class="bi bi-pencil"></i>
                                Chỉnh sửa
                            </a>

                        @endif

                    </div>

                    @if(Route::has('admin.food-orders.destroy'))

                        <form
                            action="{{ route('admin.food-orders.destroy', $order->id) }}"
                            method="POST"
                            style="margin-top:9px;"
                            onsubmit="return confirmDeleteOrder();"
                        >

                            @csrf
                            @method('DELETE')

                            <button
                                type="submit"
                                class="btn btn-danger w-full"
                            >
                                <i class="bi bi-trash"></i>
                                Xóa đơn hàng
                            </button>

                        </form>

                    @endif

                </div>

            </section>

        </aside>

    </div>

</div>

<script>
    /* =========================================================
       SHOW PAGE JAVASCRIPT
       ========================================================= */

    function confirmDeleteOrder() {
        return confirm(
            'Bạn có chắc chắn muốn xóa đơn hàng này không?\n\n' +
            'Thao tác này có thể không thể hoàn tác.'
        );
    }

    document.addEventListener('DOMContentLoaded', function () {

        const statusForm =
            document.getElementById('statusForm');

        if (statusForm) {

            statusForm.addEventListener('submit', function (event) {

                const status =
                    document.getElementById('status').value;

                const labels = {
                    pending: 'Chờ xử lý',
                    confirmed: 'Đã xác nhận',
                    preparing: 'Đang chuẩn bị',
                    completed: 'Hoàn thành',
                    cancelled: 'Đã hủy'
                };

                if (status === 'cancelled') {

                    const confirmed = confirm(
                        'Bạn đang chuyển đơn hàng sang trạng thái ĐÃ HỦY.\n\n' +
                        'Bạn có chắc chắn muốn tiếp tục không?'
                    );

                    if (!confirmed) {
                        event.preventDefault();
                        return;
                    }

                }

                if (status === 'completed') {

                    const confirmed = confirm(
                        'Xác nhận đơn hàng đã HOÀN THÀNH?\n\n' +
                        'Trạng thái mới: ' +
                        labels[status]
                    );

                    if (!confirmed) {
                        event.preventDefault();
                        return;
                    }

                }

            });

        }

    });
</script>

@endsection
