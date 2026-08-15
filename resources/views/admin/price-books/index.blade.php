@extends('layouts.admin')
@section('title', 'Bảng giá')
@section('page-title', 'Bảng giá')
@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title sr-only sm:not-sr-only">Bảng giá</h1>
        <p class="admin-page-subtitle">{{ $canManagePriceBook ? 'Một giá cơ sở toàn chuỗi theo từng phiên bản; loại ghế, loại phòng, thời điểm, chi nhánh và phòng chỉ là điều chỉnh giá.' : 'Xem bảng giá áp dụng tại chi nhánh hiện tại và kiểm tra giá theo bối cảnh vận hành. Phiên bản đã phát hành là chỉ đọc.' }}</p>
    </div>
    @unless($canManagePriceBook)<span class="status-badge border app-border">Chỉ xem · chi nhánh hiện tại</span>@endunless
</div>

<section class="admin-table-wrap" aria-labelledby="version-list-title">
    <div class="border-b app-border p-5"><h2 id="version-list-title" class="text-xl font-extrabold app-heading">Phiên bản bảng giá</h2><p class="mt-1 text-sm app-muted">{{ $priceBook->name }} · {{ $priceBook->code }}</p></div>
    <div class="overflow-x-auto">
        <table class="admin-table min-w-[760px]">
            <thead><tr><th>Phiên bản</th><th>Trạng thái</th><th>Hiệu lực</th><th class="text-right">Giá cơ sở toàn chuỗi</th><th class="text-right">Điều chỉnh</th><th></th></tr></thead>
            <tbody>
            @forelse($versions as $version)
                @php($statusLabel = ['draft'=>'Bản nháp','published'=>'Đã phát hành','retired'=>'Đã ngừng sử dụng'][$version->status])
                <tr>
                    <td class="font-extrabold">v{{ $version->version_number }}</td>
                    <td><span class="status-badge border app-border">{{ $statusLabel }}</span></td>
                    <td><span class="block">Từ {{ $version->effective_from?->format('d/m/Y') ?? 'Chưa đặt' }}</span><span class="block text-xs app-muted">{{ $version->effective_until ? 'Đến trước ngày '.$version->effective_until->format('d/m/Y') : 'Không giới hạn ngày kết thúc' }}</span></td>
                    <td class="text-right font-bold tabular-nums">{{ $version->base_price_vnd === null ? 'Chưa đặt' : number_format($version->base_price_vnd, 0, ',', '.').' ₫' }}</td>
                    <td class="text-right">{{ $version->adjustments_count }}</td>
                    <td class="text-right"><a class="font-bold text-brand-start" href="{{ route('admin.price-books.versions.show', $version) }}">Mở workspace</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-10 text-center app-muted">@if($canManagePriceBook)Chưa có phiên bản bảng giá. Cần khởi tạo phiên bản nền trước khi cấu hình và phát hành.@else Hiện chưa có bảng giá đã phát hành trong phạm vi chi nhánh hiện tại. Liên hệ Global Admin để cấu hình bảng giá.@endif</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<div class="mt-6">@include('admin.price-books._preview')</div>
@endsection
