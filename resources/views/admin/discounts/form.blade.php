@extends('layouts.admin')
@section('title', $discount->exists ? 'Sửa mã giảm giá' : 'Thêm mã giảm giá')
@section('page-title', $discount->exists ? 'Sửa mã giảm giá' : 'Thêm mã giảm giá')
@section('content')
@php($used = $discount->exists && $discount->usages()->exists())
<form method="POST" action="{{ $discount->exists ? route('admin.discounts.update', $discount) : route('admin.discounts.store') }}" class="admin-form-card max-w-5xl">
@csrf @if($discount->exists)@method('PUT')@endif
@if($used)<div class="mb-5 rounded-xl border border-warning/30 bg-warning/10 p-4 text-sm text-warning">Khuyến mãi đã có lịch sử sử dụng. Định nghĩa business và phạm vi chi nhánh đã được khóa; chỉ trạng thái/lưu trữ có thể thay đổi.</div>@endif
<div class="grid gap-5 md:grid-cols-2">
<div><label class="admin-label">Mã</label><input name="code" value="{{ old('code', $discount->code) }}" class="admin-input uppercase" required @disabled($used)></div>
<div><label class="admin-label">Tên chương trình</label><input name="name" value="{{ old('name', $discount->name) }}" class="admin-input" required @disabled($used)></div>
<div><label class="admin-label">Loại giảm</label><select id="promotion-type" name="type" class="admin-input" @disabled($used)><option value="fixed" @selected(old('type',$discount->type)==='fixed')>Số tiền cố định</option><option value="percentage" @selected(old('type',$discount->type)==='percentage')>Phần trăm</option></select></div>
<div data-fixed-field><label class="admin-label">Số tiền giảm (VNĐ)</label><input type="number" min="1" name="discount_amount_vnd" value="{{ old('discount_amount_vnd',$discount->discount_amount_vnd) }}" class="admin-input" @disabled($used)></div>
<div data-percentage-field><label class="admin-label">Tỷ lệ giảm (%)</label><input type="number" min="1" max="100" name="discount_percent" value="{{ old('discount_percent',$discount->discount_percent) }}" class="admin-input" @disabled($used)></div>
<div data-percentage-field><label class="admin-label">Giảm tối đa (VNĐ, để trống = không cap)</label><input type="number" min="1" name="maximum_discount_vnd" value="{{ old('maximum_discount_vnd',$discount->maximum_discount_vnd) }}" class="admin-input" @disabled($used)></div>
<div><label class="admin-label">Đơn tối thiểu (VNĐ)</label><input type="number" min="0" name="minimum_order_vnd" value="{{ old('minimum_order_vnd',$discount->minimum_order_vnd ?? 0) }}" class="admin-input" required @disabled($used)></div>
<div><label class="admin-label">Bắt đầu (giờ địa phương chi nhánh)</label><input type="datetime-local" name="starts_at" value="{{ old('starts_at',$discount->starts_at?->format('Y-m-d\TH:i')) }}" class="admin-input" @disabled($used)></div>
<div><label class="admin-label">Kết thúc (exclusive)</label><input type="datetime-local" name="ends_at" value="{{ old('ends_at',$discount->ends_at?->format('Y-m-d\TH:i')) }}" class="admin-input" @disabled($used)></div>
<div><label class="admin-label">Tổng lượt</label><input type="number" min="1" name="global_usage_limit" value="{{ old('global_usage_limit',$discount->global_usage_limit) }}" class="admin-input" @disabled($used)></div>
<div><label class="admin-label">Lượt mỗi tài khoản</label><input type="number" min="1" name="per_user_usage_limit" value="{{ old('per_user_usage_limit',$discount->per_user_usage_limit) }}" class="admin-input" @disabled($used)></div>
<div><label class="admin-label">Trạng thái</label><select name="is_active" class="admin-input"><option value="1" @selected(old('is_active',$discount->is_active ?? true))>Đang áp dụng</option><option value="0" @selected(!old('is_active',$discount->is_active ?? true))>Tạm ngừng</option></select></div>
<fieldset class="md:col-span-2" @disabled($used)><legend class="admin-label">{{ $canCreateGlobalPromotion ? 'Chi nhánh (để trống = toàn hệ thống)' : 'Chi nhánh áp dụng (bắt buộc)' }}</legend><div class="grid gap-2 sm:grid-cols-2">@foreach($cinemas as $cinema)<label class="flex gap-2"><input type="checkbox" name="cinema_ids[]" value="{{ $cinema->id }}" @checked(in_array($cinema->id, old('cinema_ids',$discount->cinemas?->pluck('id')->all() ?? [])))>{{ $cinema->name }}</label>@endforeach</div>@unless($canCreateGlobalPromotion)<p class="mt-2 text-xs app-muted">Manager chỉ có thể lưu mã cho chi nhánh đang chọn; phạm vi toàn hệ thống chỉ do Admin quản lý.</p>@endunless</fieldset>
<div class="md:col-span-2 flex flex-wrap gap-4">@foreach(['registered_users_only'=>'Chỉ khách đăng nhập','first_order_only'=>'Chỉ đơn đầu tiên'] as $field=>$label)<label class="flex gap-2"><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field,$discount->{$field})) @disabled($used)>{{ $label }}</label>@endforeach</div>
<div class="md:col-span-2"><label class="admin-label">Mô tả</label><textarea name="description" class="admin-input" rows="3" @disabled($used)>{{ old('description',$discount->description) }}</textarea></div>
</div>
<x-validation-summary class="mt-5" :errors="$errors"/><div class="mt-6 flex gap-3"><button class="admin-btn-primary">Lưu</button><a class="admin-btn-secondary" href="{{ route('admin.discounts.index') }}">Quay lại</a></div>
</form>
@if($discount->exists && !$discount->archived_at)<form method="POST" action="{{ route('admin.discounts.archive',$discount) }}" class="mx-auto mt-4 max-w-5xl">@csrf @method('PATCH')<button class="admin-btn-secondary">Lưu trữ mã</button></form>@endif
<script>
document.addEventListener('DOMContentLoaded', () => {
    const type = document.getElementById('promotion-type');
    const sync = () => {
        const percentage = type?.value === 'percentage';
        document.querySelectorAll('[data-fixed-field]').forEach((field) => {
            field.hidden = percentage;
            field.querySelectorAll('input').forEach((input) => input.disabled = percentage || @json($used));
        });
        document.querySelectorAll('[data-percentage-field]').forEach((field) => {
            field.hidden = !percentage;
            field.querySelectorAll('input').forEach((input) => input.disabled = !percentage || @json($used));
        });
    };
    type?.addEventListener('change', sync);
    sync();
});
</script>
@endsection
