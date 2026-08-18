@extends('layouts.admin')
@section('title', 'Bảng giá v'.$version->version_number)
@section('page-title', 'Bảng giá v'.$version->version_number)
@section('suppress-global-validation-summary', '1')
@section('content')
@php
    $statusLabel = match($version->status) {
        'draft' => 'Đang soạn',
        'published' => 'Đã phát hành',
        'retired' => 'Đã ngừng sử dụng',
    };
    $dimensionLabels = [
        'seat_type' => 'Loại vé', 'room_type' => 'Loại phòng', 'time_window' => 'Khung giờ',
        'weekend' => 'Cuối tuần', 'holiday' => 'Ngày lễ', 'cinema' => 'Chi nhánh', 'room' => 'Phòng',
    ];
    $weekdayLabels = [1 => 'Thứ 2', 2 => 'Thứ 3', 3 => 'Thứ 4', 4 => 'Thứ 5', 5 => 'Thứ 6', 6 => 'Thứ 7', 7 => 'Chủ nhật'];
    $seatAdjustments = $version->adjustments->where('dimension', 'seat_type')->keyBy(fn($item) => (int) $item->seat_type_id);
    $contextAdjustments = $version->adjustments->where('dimension', '!=', 'seat_type');
@endphp

<div class="admin-page-header items-start">
    <div>
        <a class="text-sm font-bold text-brand-start" href="{{ route('admin.price-books.index') }}">← Tất cả bảng giá</a>
        <h1 class="admin-page-title mt-2">Bảng giá v{{ $version->version_number }}</h1>
        <p class="admin-page-subtitle">{{ $priceBook->name }}</p>
    </div>
    <div class="text-right">
        <span class="status-badge border app-border">{{ $statusLabel }}</span>
        <p class="mt-2 text-xs app-muted">Mã trạng thái: {{ $version->status }}</p>
    </div>
</div>

<x-validation-summary class="mb-5" :errors="$errors" heading="Chưa thể lưu bảng giá."/>

@if($version->status === 'draft')
    <div class="mb-6 rounded-2xl border border-brand-start/30 bg-brand-start/10 p-4 text-sm">
        <p class="font-bold app-heading">Bạn đang chuẩn bị một bảng giá mới; khách hàng chưa thấy các thay đổi này.</p>
        <p class="mt-1 app-muted">Sao chép độc lập từ phiên bản đã phát hành giúp bảng giá cũ không thay đổi. Hãy nhập giá bán, kiểm tra phụ thu rồi mới áp dụng.</p>
        <p class="mt-2 app-muted">Nếu bạn chỉ muốn đổi giá bên trong thời gian của bảng đang áp dụng, hãy quay lại danh sách, mở phiên bản <strong>Đã phát hành</strong> và dùng mục <strong>Thay đổi giá an toàn</strong>.</p>
    </div>
@elseif($version->status === 'published')
    <div class="mb-6 rounded-2xl border border-success/30 bg-success/10 p-4 text-sm">
        <p class="font-bold app-heading">Bảng giá đã phát hành được khóa và chỉ có thể xem.</p>
        <p class="mt-1 app-muted">Giá đã lưu cho các suất chiếu không thay đổi khi bạn tạo hoặc ngừng dùng một bảng giá khác.</p>
    </div>
@else
    <div class="mb-6 rounded-2xl border app-border p-4 text-sm">
        <p class="font-bold app-heading">Bảng giá đã ngừng sử dụng và chỉ còn để tra cứu.</p>
        <p class="mt-1 app-muted">Giá của các suất chiếu trước đây vẫn được giữ nguyên.</p>
    </div>
@endif

@include('admin.price-books._ticket_prices')

@include('admin.price-books._advanced_adjustments')

<div class="mt-6">@include('admin.price-books._preview')</div>

@if($canManagePriceBook && $version->status === 'draft')
    <section class="cinema-card mt-6 p-5 sm:p-6" aria-labelledby="apply-price-book-title">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-start">Bước 3</p>
        <h2 id="apply-price-book-title" class="mt-1 text-xl font-extrabold app-heading">Kiểm tra và áp dụng</h2>
        <p class="mt-1 max-w-3xl text-sm app-muted">Hệ thống sẽ kiểm tra thời gian bị chồng lấn, phụ thu trùng nhau và mọi trường hợp có thể tạo giá không hợp lệ. Sau khi áp dụng, bảng giá sẽ được khóa.</p>
        <form class="mt-5" method="POST" action="{{ route('admin.price-books.versions.publish', $version) }}">
            @csrf
            <button class="admin-btn-primary" onclick="return confirm('Áp dụng và khóa bảng giá này? Bạn sẽ không thể sửa lại sau khi xác nhận.')">Kiểm tra và áp dụng bảng giá</button>
            <span class="ml-3 text-xs app-muted">Phát hành phiên bản</span>
        </form>
    </section>
@elseif($canManagePriceBook && $version->status === 'published')
    <details class="cinema-card mt-6 overflow-hidden">
        <summary class="cursor-pointer p-5 font-bold app-muted sm:p-6">Công cụ kỹ thuật nâng cao</summary>
        <div class="grid gap-6 border-t app-border p-5 sm:p-6 xl:grid-cols-2">
            <form class="rounded-2xl border app-border p-4" method="POST" action="{{ route('admin.price-books.versions.copy', $version) }}">
                @csrf
                <h2 class="text-lg font-extrabold app-heading">Sao chép thành bản nháp</h2>
                <p class="mt-1 text-sm app-muted">Dành cho cấu hình phụ thu nâng cao. Bản nháp không thể phát hành nếu chồng lấn lịch.</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div><label class="admin-label" for="copy-from">Bắt đầu áp dụng</label><input class="admin-input" id="copy-from" type="date" name="effective_from"></div>
                    <div><label class="admin-label" for="copy-end">Áp dụng đến hết</label><input class="admin-input" id="copy-end" type="date" name="effective_end_date"></div>
                </div>
                <button class="admin-btn-secondary mt-5">Tạo bản nháp nâng cao</button>
            </form>

            <form class="rounded-2xl border app-border p-4" method="POST" action="{{ route('admin.price-books.versions.retire', $version) }}">
                @csrf
                <h2 class="text-lg font-extrabold app-heading">Ngừng mà không tạo lịch thay thế</h2>
                <p class="mt-1 text-sm app-muted">Có thể tạo khoảng trống không có giá. Chỉ dùng khi chủ động dừng toàn bộ lần tính giá trong kỳ này.</p>
                <button class="admin-btn-secondary mt-5" onclick="return confirm('Ngừng bảng giá mà không tạo lịch thay thế?')">Ngừng áp dụng</button>
            </form>
        </div>
    </details>
@endif
@endsection
