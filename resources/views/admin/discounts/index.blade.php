@extends('layouts.admin')
@section('title', 'Khuyến mãi')
@section('page-title', 'Khuyến mãi')
@section('content')
<div class="admin-page-header"><div><h1 class="admin-page-title sr-only sm:not-sr-only">Khuyến mãi</h1><p class="admin-page-subtitle">Khuyến mãi cấp đơn đặt vé, tách biệt hoàn toàn với bảng giá vé. Mỗi đơn đặt vé áp dụng tối đa một khuyến mãi.</p></div>@can('discounts.manage')<a class="admin-btn-primary" href="{{ route('admin.discounts.create') }}">Thêm khuyến mãi</a>@endcan</div>
<div class="admin-table-wrap">
    <div class="overflow-x-auto">
    <table class="admin-table min-w-[980px]">
        <thead><tr><th>Mã</th><th>Ưu đãi</th><th>Phạm vi</th><th>Lượt dùng</th><th>Hiệu lực</th><th>Trạng thái</th><th></th></tr></thead>
        <tbody>
        @forelse($discounts as $discount)
            <tr>
                <td><strong>{{ $discount->code }}</strong><span class="block text-xs app-muted">{{ $discount->name }}</span></td>
                <td>{{ $discount->type === 'percentage' ? $discount->discount_percent.'%'.($discount->maximum_discount_vnd ? ' · tối đa '.number_format($discount->maximum_discount_vnd, 0, ',', '.').' VNĐ' : ' · không giới hạn riêng') : number_format($discount->discount_amount_vnd, 0, ',', '.').' VNĐ cố định' }}</td>
                <td>
                    @if($discount->cinemas->isEmpty())
                        Toàn hệ thống
                    @elseif($hasGlobalPromotionAccess)
                        {{ $discount->cinemas->pluck('name')->join(', ') }}
                    @else
                        {{ $discount->cinemas->firstWhere('id', $promotionAdminCinemaId)?->name ?? 'Chi nhánh hiện tại' }}
                        @if($discount->cinemas->count() > 1) + chi nhánh khác @endif
                    @endif
                    @unless($discount->admin_can_manage)<span class="block text-xs app-muted">Chỉ xem</span>@endunless
                </td>
                <td>{{ $discount->active_usage_count }} / {{ $discount->global_usage_limit ?? '∞' }}@if($discount->usages_exists)<span class="block text-xs app-muted">Đã phát sinh sử dụng · nội dung đã khóa</span>@endif</td>
                <td><span class="block">Từ {{ $discount->starts_at?->format('d/m/Y H:i') ?? 'ngay' }}</span><span class="block text-xs app-muted">{{ $discount->ends_at ? 'Có hiệu lực trước '.$discount->ends_at->format('d/m/Y H:i') : 'Không giới hạn thời điểm kết thúc' }}</span></td>
                <td>{{ $discount->is_active && !$discount->archived_at ? 'Đang áp dụng' : 'Đã lưu trữ' }}</td>
                <td>@can('discounts.manage') @if($discount->admin_can_manage)<a class="font-bold text-brand-start" href="{{ route('admin.discounts.edit', $discount) }}">{{ $discount->usages_exists ? 'Quản lý vòng đời' : 'Chỉnh sửa khuyến mãi' }}</a>@endif @endcan</td>
            </tr>
        @empty
            <tr><td colspan="7" class="py-10 text-center app-muted"><p>Chưa có khuyến mãi trong phạm vi bạn được phép xem.</p>@can('discounts.manage')<a class="mt-3 inline-flex font-bold text-brand-start" href="{{ route('admin.discounts.create') }}">Tạo khuyến mãi</a>@endcan</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>
{{ $discounts->links() }}
@endsection
