@extends('layouts.admin')
@section('title', 'Bảng giá vé')
@section('page-title', 'Bảng giá vé')
@section('content')
<div class="admin-page-header items-start">
    <div>
        <h1 class="admin-page-title">Bảng giá vé</h1>
        <p class="admin-page-subtitle">{{ $canManagePriceBook ? 'Xem giá khách hàng sẽ trả, kiểm tra phụ thu và chuẩn bị bảng giá tiếp theo theo từng bước.' : 'Xem bảng giá áp dụng tại chi nhánh hiện tại và kiểm tra giá theo bối cảnh vận hành. Phiên bản đã phát hành là chỉ đọc.' }}</p>
    </div>
    @unless($canManagePriceBook)
        <span class="status-badge border app-border">Chỉ xem · chi nhánh hiện tại</span>
    @endunless
</div>

<section aria-labelledby="price-list-title">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 id="price-list-title" class="text-xl font-extrabold app-heading">Các bảng giá</h2>
            <p class="mt-1 text-sm app-muted">{{ $priceBook->name }} · Mã kỹ thuật {{ $priceBook->code }}</p>
        </div>
        <p class="max-w-xl text-sm app-muted">{{ $canManagePriceBook ? 'Bảng giá đã áp dụng được khóa để bảo vệ giá của các suất chiếu. Muốn đổi giá, hãy mở bảng đang dùng và tạo một bảng mới.' : 'Bảng giá đã phát hành là dữ liệu dùng chung toàn chuỗi và chỉ được xem trong phạm vi chi nhánh hiện tại.' }}</p>
    </div>

    <div class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
        @forelse($versions as $version)
            @php
                $statusLabel = match($version->status) {
                    'draft' => 'Đang soạn',
                    'published' => 'Đã phát hành',
                    'retired' => 'Đã ngừng sử dụng',
                };
                $statusTone = match($version->status) {
                    'draft' => 'border-warning/40 bg-warning/10 text-warning',
                    'published' => 'border-success/40 bg-success/10 text-success',
                    default => 'border app-border app-muted',
                };
            @endphp
            <article class="cinema-card flex h-full flex-col p-5 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] app-muted">Bảng giá v{{ $version->version_number }}</p>
                        <h3 class="mt-1 text-xl font-extrabold app-heading">{{ $statusLabel }}</h3>
                    </div>
                    <span class="status-badge {{ $statusTone }}">{{ $version->status === 'draft' ? 'Chưa áp dụng' : ($version->status === 'published' ? 'Đã phát hành' : $statusLabel) }}</span>
                </div>

                <dl class="mt-5 grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="app-muted">Giá vé thường</dt>
                        <dd class="mt-1 text-2xl font-black tabular-nums app-heading">{{ $version->base_price_vnd === null ? 'Chưa đặt' : number_format($version->base_price_vnd, 0, ',', '.').' ₫' }}</dd>
                    </div>
                    <div>
                        <dt class="app-muted">Phụ thu theo điều kiện</dt>
                        <dd class="mt-1 text-2xl font-black tabular-nums app-heading">{{ $version->contextual_adjustments_count }}</dd>
                    </div>
                    <div>
                        <dt class="app-muted">Bắt đầu</dt>
                        <dd class="mt-1 font-bold">{{ $version->effective_from?->format('d/m/Y') ?? 'Chưa chọn' }}</dd>
                    </div>
                    <div>
                        <dt class="app-muted">Kết thúc</dt>
                        <dd class="mt-1 font-bold">{{ $version->effective_until ? $version->effective_until->copy()->subDay()->format('d/m/Y') : 'Không giới hạn' }}</dd>
                    </div>
                </dl>

                <a class="admin-btn-secondary mt-6 w-full justify-center" href="{{ route('admin.price-books.versions.show', $version) }}">
                    {{ $version->status === 'draft' && $canManagePriceBook ? 'Tiếp tục thiết lập' : 'Xem bảng giá' }}
                </a>
            </article>
        @empty
            <div class="cinema-card p-8 text-center app-muted lg:col-span-2 2xl:col-span-3">
                {{ $canManagePriceBook ? 'Chưa có bảng giá. Cần khởi tạo dữ liệu nền trước khi cấu hình.' : 'Hiện chưa có bảng giá đã phát hành trong phạm vi chi nhánh hiện tại. Liên hệ Global Admin để cấu hình bảng giá.' }}
            </div>
        @endforelse
    </div>
</section>

<div class="mt-6">@include('admin.price-books._preview')</div>
@endsection
