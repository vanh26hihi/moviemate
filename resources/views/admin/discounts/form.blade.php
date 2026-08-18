@extends('layouts.admin')
@section('title', $discount->exists ? 'Sửa khuyến mãi' : 'Thêm khuyến mãi')
@section('page-title', $discount->exists ? 'Sửa khuyến mãi' : 'Thêm khuyến mãi')
@section('content')
@php
    $used = $discount->exists && (bool) ($discount->usages_exists ?? false);
    $selectedType = old('type', $discount->type ?: 'fixed');
@endphp
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title sr-only sm:not-sr-only">{{ $discount->exists ? 'Sửa khuyến mãi' : 'Thêm khuyến mãi' }}</h1>
        <p class="admin-page-subtitle">Định nghĩa ưu đãi cấp đơn đặt vé; giá vé trước khuyến mãi vẫn do bảng giá và snapshot suất chiếu quyết định.</p>
    </div>
</div>
<form method="POST" action="{{ $discount->exists ? route('admin.discounts.update', $discount) : route('admin.discounts.store') }}" class="admin-form-card max-w-5xl">
@csrf @if($discount->exists)@method('PUT')@endif
@if($used)<div class="mb-5 rounded-xl border border-warning/30 bg-warning/10 p-4 text-sm text-warning" role="status"><strong>Đã phát sinh sử dụng — nội dung khuyến mãi đã được khóa.</strong><span class="mt-1 block">Mọi lượt sử dụng, kể cả lượt đã giải phóng, đều giữ nguyên định nghĩa kinh doanh và phạm vi chi nhánh; chỉ trạng thái vòng đời có thể thay đổi.</span></div>@endif
<div class="mb-5 rounded-xl border app-border p-4 text-sm app-muted"><p>Mỗi đơn đặt vé áp dụng tối đa một khuyến mãi.</p><p class="mt-1">Đơn tối thiểu được tính trên tổng tiền vé + đồ ăn trước khuyến mãi.</p><p class="mt-1">Thời gian hiệu lực được kiểm tra theo giờ địa phương của chi nhánh nơi đặt vé; tại đúng thời điểm kết thúc, khuyến mãi không còn hiệu lực.</p></div>
<div class="grid gap-5 md:grid-cols-2">
<div><label class="admin-label" for="promotion-code">Mã khuyến mãi</label><input id="promotion-code" name="code" value="{{ old('code', $discount->code) }}" class="admin-input uppercase" required maxlength="50" pattern="[A-Za-z0-9_-]+" data-validation-url="{{ route('admin.validation.field') }}" data-validation-rule="promotion.code" data-validation-record="{{ $discount->exists ? $discount->getKey() : '' }}" @disabled($used)></div>
<div><label class="admin-label" for="promotion-name">Tên chương trình</label><input id="promotion-name" name="name" value="{{ old('name', $discount->name) }}" class="admin-input" required @disabled($used)></div>
<div><label class="admin-label" for="promotion-type">Loại giảm</label><select id="promotion-type" name="type" class="admin-input" @disabled($used)><option value="fixed" @selected($selectedType==='fixed')>Số tiền cố định</option><option value="percentage" @selected($selectedType==='percentage')>Phần trăm</option></select></div>
<div data-fixed-field @if($selectedType === 'percentage') hidden @endif><label class="admin-label" for="discount-amount">Số tiền giảm (VNĐ)</label><input id="discount-amount" type="number" min="1" data-validation-required-if="type:fixed" data-promotion-name="discount_amount_vnd" @if($selectedType === 'fixed') name="discount_amount_vnd" @endif value="{{ old('discount_amount_vnd',$discount->discount_amount_vnd) }}" class="admin-input" @disabled($used)></div>
<div data-percentage-field @if($selectedType !== 'percentage') hidden @endif><label class="admin-label" for="discount-percent">Tỷ lệ giảm (%)</label><input id="discount-percent" type="number" min="1" max="100" data-validation-required-if="type:percentage" data-promotion-name="discount_percent" @if($selectedType === 'percentage') name="discount_percent" @endif value="{{ old('discount_percent',$discount->discount_percent) }}" class="admin-input" @disabled($used)></div>
<div data-percentage-field @if($selectedType !== 'percentage') hidden @endif><label class="admin-label" for="maximum-discount">Mức giảm tối đa (VNĐ)</label><input id="maximum-discount" type="number" min="1" data-promotion-name="maximum_discount_vnd" @if($selectedType === 'percentage') name="maximum_discount_vnd" @endif value="{{ old('maximum_discount_vnd',$discount->maximum_discount_vnd) }}" class="admin-input" aria-describedby="maximum-discount-help" @disabled($used)><p id="maximum-discount-help" class="mt-1 text-xs app-muted">Để trống nếu không đặt giới hạn riêng. Giá trị 0 không có nghĩa là không giới hạn.</p></div>
<div><label class="admin-label" for="minimum-order">Đơn tối thiểu (VNĐ)</label><input id="minimum-order" type="number" min="0" name="minimum_order_vnd" value="{{ old('minimum_order_vnd',$discount->minimum_order_vnd ?? 0) }}" class="admin-input" aria-describedby="minimum-order-help" required @disabled($used)><p id="minimum-order-help" class="mt-1 text-xs app-muted">Tính trên tổng tiền vé + đồ ăn trước khuyến mãi.</p></div>
<div><label class="admin-label" for="promotion-start">Áp dụng từ (giờ địa phương chi nhánh)</label><input id="promotion-start" type="datetime-local" name="starts_at" value="{{ old('starts_at',$discount->starts_at?->format('Y-m-d\TH:i')) }}" class="admin-input" @disabled($used)></div>
<div><label class="admin-label" for="promotion-end">Có hiệu lực trước</label><input id="promotion-end" type="datetime-local" name="ends_at" value="{{ old('ends_at',$discount->ends_at?->format('Y-m-d\TH:i')) }}" class="admin-input" aria-describedby="promotion-end-help" @disabled($used)><p id="promotion-end-help" class="mt-1 text-xs app-muted">Tại đúng thời điểm kết thúc, khuyến mãi không còn hiệu lực.</p></div>
<div><label class="admin-label" for="global-usage-limit">Tổng lượt</label><input id="global-usage-limit" type="number" min="1" name="global_usage_limit" value="{{ old('global_usage_limit',$discount->global_usage_limit) }}" class="admin-input" @disabled($used)></div>
<div><label class="admin-label" for="per-user-usage-limit">Lượt mỗi tài khoản</label><input id="per-user-usage-limit" type="number" min="1" name="per_user_usage_limit" value="{{ old('per_user_usage_limit',$discount->per_user_usage_limit) }}" class="admin-input" @disabled($used)></div>
<div><label class="admin-label" for="promotion-status">Trạng thái</label><select id="promotion-status" name="is_active" class="admin-input"><option value="1" @selected(old('is_active',$discount->is_active ?? true))>Đang áp dụng</option><option value="0" @selected(!old('is_active',$discount->is_active ?? true))>Tạm ngừng</option></select></div>
<fieldset class="md:col-span-2" @disabled($used)><legend class="admin-label">{{ $canCreateGlobalPromotion ? 'Chi nhánh (để trống = toàn hệ thống)' : 'Chi nhánh áp dụng (bắt buộc)' }}</legend><div class="grid gap-2 sm:grid-cols-2">@foreach($cinemas as $cinema)<label class="flex gap-2"><input type="checkbox" name="cinema_ids[]" value="{{ $cinema->id }}" @checked(in_array($cinema->id, old('cinema_ids',$discount->cinemas?->pluck('id')->all() ?? [])))>{{ $cinema->name }}</label>@endforeach</div>@unless($canCreateGlobalPromotion)<p class="mt-2 text-xs app-muted">Manager chỉ có thể lưu khuyến mãi cho chi nhánh đang chọn; phạm vi toàn hệ thống chỉ do Global Admin quản lý.</p>@endunless</fieldset>
<div class="md:col-span-2 flex flex-wrap gap-4">@foreach(['registered_users_only'=>'Chỉ khách đăng nhập','first_order_only'=>'Chỉ đơn đầu tiên'] as $field=>$label)<label class="flex gap-2"><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field,$discount->{$field})) @disabled($used)>{{ $label }}</label>@endforeach</div>
<div class="md:col-span-2"><label class="admin-label" for="promotion-description">Mô tả</label><textarea id="promotion-description" name="description" class="admin-input" rows="3" @disabled($used)>{{ old('description',$discount->description) }}</textarea></div>
</div>
<x-validation-summary class="mt-5" :errors="$errors"/><div class="mt-6 flex gap-3"><button class="admin-btn-primary">{{ $used ? 'Cập nhật trạng thái' : 'Lưu khuyến mãi' }}</button><a class="admin-btn-secondary" href="{{ route('admin.discounts.index') }}">Quay lại</a></div>
</form>
@if($discount->exists && !$discount->archived_at)<form method="POST" action="{{ route('admin.discounts.archive',$discount) }}" class="mx-auto mt-4 max-w-5xl">@csrf @method('PATCH')<button class="admin-btn-secondary">Lưu trữ khuyến mãi</button></form>@endif
<script>
document.addEventListener('DOMContentLoaded', () => {
    const type = document.getElementById('promotion-type');
    const sync = () => {
        const percentage = type?.value === 'percentage';
        document.querySelectorAll('[data-fixed-field]').forEach((field) => {
            field.hidden = percentage;
            field.querySelectorAll('input').forEach((input) => {
                input.disabled = percentage || @json($used);
                input.name = input.disabled ? '' : input.dataset.promotionName;
            });
        });
        document.querySelectorAll('[data-percentage-field]').forEach((field) => {
            field.hidden = !percentage;
            field.querySelectorAll('input').forEach((input) => {
                input.disabled = !percentage || @json($used);
                input.name = input.disabled ? '' : input.dataset.promotionName;
            });
        });
    };
    type?.addEventListener('change', sync);
    sync();
});
</script>
@endsection
