@extends('layouts.admin')

@section('title', 'Quản lý voucher')
@section('page-title', 'Quản lý voucher')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Quản lý voucher</h1>
        <p class="admin-page-subtitle">Tạo và quản lý mã giảm giá dùng ở bước thanh toán.</p>
    </div>
    <a href="{{ route('admin.vouchers.create') }}" class="admin-btn-primary">
        <i class="ph-bold ph-plus"></i>
        Thêm voucher
    </a>
</div>

<div class="admin-toolbar">
    <form method="GET" action="{{ route('admin.vouchers.index') }}" class="grid w-full grid-cols-1 md:grid-cols-[1fr_180px_auto] gap-3">
        <label class="relative">
            <i class="ph ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 app-text-muted"></i>
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Tìm mã hoặc tên voucher..." class="admin-input pl-11">
        </label>
        <select name="status" class="admin-input">
            <option value="">Tất cả trạng thái</option>
            <option value="active" {{ ($status ?? '') === 'active' ? 'selected' : '' }}>Đang bật</option>
            <option value="inactive" {{ ($status ?? '') === 'inactive' ? 'selected' : '' }}>Đã tắt</option>
        </select>
        <button type="submit" class="admin-btn-primary">
            <i class="ph-bold ph-funnel"></i>
            Lọc
        </button>
    </form>
</div>

<div class="admin-table-card">
    <div class="overflow-x-auto">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Mã</th>
                    <th>Tên</th>
                    <th>Giảm</th>
                    <th>Đơn tối thiểu</th>
                    <th>Lượt dùng</th>
                    <th>Mỗi tài khoản</th>
                    <th>Trạng thái</th>
                    <th class="text-right">Hành động</th>
                </tr>
            </thead>
            <tbody>
                @forelse($vouchers as $voucher)
                    <tr>
                        <td><span class="font-mono font-extrabold text-brand-start">{{ $voucher->code }}</span></td>
                        <td class="font-bold app-text">{{ $voucher->name }}</td>
                        <td>
                            @if($voucher->discount_type === 'percent')
                                {{ rtrim(rtrim(number_format($voucher->discount_value, 2, ',', '.'), '0'), ',') }}%
                                @if($voucher->max_discount_amount)
                                    <span class="block text-xs app-muted">Tối đa {{ number_format($voucher->max_discount_amount, 0, ',', '.') }}đ</span>
                                @endif
                            @else
                                {{ number_format($voucher->discount_value, 0, ',', '.') }}đ
                            @endif
                        </td>
                        <td>{{ number_format($voucher->min_order_amount, 0, ',', '.') }}đ</td>
                        <td>{{ number_format($voucher->used_count) }}{{ $voucher->usage_limit ? ' / '.number_format($voucher->usage_limit) : '' }}</td>
                        <td>{{ $voucher->per_user_limit ? number_format($voucher->per_user_limit).' lần' : 'Không giới hạn' }}</td>
                        <td>
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold {{ $voucher->status === 'active' ? 'bg-success/10 text-success' : 'bg-error/10 text-error' }}">
                                {{ $voucher->status === 'active' ? 'Đang bật' : 'Đã tắt' }}
                            </span>
                        </td>
                        <td>
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.vouchers.edit', $voucher) }}" class="admin-btn-warning admin-action-btn" title="Sửa" aria-label="Sửa" data-tooltip="Sửa">
                                    <i class="ph ph-pencil-simple"></i>
                                </a>
                                <form action="{{ route('admin.vouchers.destroy', $voucher) }}" method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa voucher này?');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="admin-btn-danger admin-action-btn" title="Xóa" aria-label="Xóa" data-tooltip="Xóa">
                                        <i class="ph ph-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="admin-empty">
                            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-start/10 text-brand-start">
                                <i class="ph-fill ph-ticket text-3xl"></i>
                            </div>
                            Chưa có voucher nào.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t app-border px-5 py-4">
        {{ $vouchers->links() }}
    </div>
</div>
@endsection
