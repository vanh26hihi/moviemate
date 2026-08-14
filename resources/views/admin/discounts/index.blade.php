@extends('layouts.admin')
@section('title', 'Mã giảm giá')
@section('page-title', 'Mã giảm giá')
@section('content')
<div class="admin-page-header"><div><h1 class="admin-page-title">Mã giảm giá</h1><p class="admin-page-subtitle">Khuyến mãi cấp đơn hàng, tách biệt hoàn toàn với bảng giá vé.</p></div>@can('discounts.manage')<a class="admin-btn-primary" href="{{ route('admin.discounts.create') }}">Thêm mã</a>@endcan</div>
<div class="admin-table-wrap">
    <table class="admin-table">
        <thead><tr><th>Mã</th><th>Ưu đãi</th><th>Phạm vi</th><th>Lượt dùng</th><th>Hiệu lực</th><th>Trạng thái</th><th></th></tr></thead>
        <tbody>
        @forelse($discounts as $discount)
            <tr>
                <td><strong>{{ $discount->code }}</strong><span class="block text-xs app-muted">{{ $discount->name }}</span></td>
                <td>{{ $discount->type === 'percentage' ? $discount->discount_percent.'%' : number_format($discount->discount_amount_vnd, 0, ',', '.').' VNĐ' }}</td>
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
                <td>{{ $discount->active_usage_count }} / {{ $discount->global_usage_limit ?? '∞' }}</td>
                <td>{{ $discount->starts_at?->format('d/m/Y H:i') ?? 'Ngay' }} – {{ $discount->ends_at?->format('d/m/Y H:i') ?? 'Không hạn' }}</td>
                <td>{{ $discount->is_active && !$discount->archived_at ? 'Đang áp dụng' : 'Đã lưu trữ' }}</td>
                <td>@can('discounts.manage') @if($discount->admin_can_manage)<a class="text-brand-start" href="{{ route('admin.discounts.edit', $discount) }}">Sửa</a>@endif @endcan</td>
            </tr>
        @empty
            <tr><td colspan="7">Chưa có mã giảm giá.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $discounts->links() }}
@endsection
