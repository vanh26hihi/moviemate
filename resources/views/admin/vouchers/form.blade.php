@php
    $voucher = $voucher ?? null;
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <div>
        <label class="admin-label">Mã voucher *</label>
        <input type="text" name="code" value="{{ old('code', $voucher?->code) }}" required class="admin-input uppercase" placeholder="VD: MMT10">
        <p class="admin-help">Mã sẽ được tự động lưu dạng chữ in hoa.</p>
    </div>

    <div>
        <label class="admin-label">Tên voucher *</label>
        <input type="text" name="name" value="{{ old('name', $voucher?->name) }}" required class="admin-input" placeholder="VD: Giảm 10% cuối tuần">
    </div>

    <div>
        <label class="admin-label">Loại giảm *</label>
        <select name="discount_type" required class="admin-input">
            <option value="fixed" {{ old('discount_type', $voucher?->discount_type ?? 'fixed') === 'fixed' ? 'selected' : '' }}>Giảm tiền cố định</option>
            <option value="percent" {{ old('discount_type', $voucher?->discount_type) === 'percent' ? 'selected' : '' }}>Giảm theo phần trăm</option>
        </select>
    </div>

    <div>
        <label class="admin-label">Giá trị giảm *</label>
        <input type="number" name="discount_value" min="0" step="1" value="{{ old('discount_value', $voucher?->discount_value) }}" required class="admin-input" placeholder="VD: 20000 hoặc 10">
        <p class="admin-help">Nếu loại là phần trăm, nhập 10 nghĩa là 10%. Giá trị phần trăm tối đa là 100.</p>
    </div>

    <div>
        <label class="admin-label">Đơn tối thiểu</label>
        <input type="number" name="min_order_amount" min="0" step="1000" value="{{ old('min_order_amount', $voucher?->min_order_amount ?? 0) }}" class="admin-input">
    </div>

    <div>
        <label class="admin-label">Giảm tối đa</label>
        <input type="number" name="max_discount_amount" min="0" step="1000" value="{{ old('max_discount_amount', $voucher?->max_discount_amount) }}" class="admin-input" placeholder="Chỉ cần cho voucher phần trăm">
    </div>

    <div>
        <label class="admin-label">Giới hạn lượt dùng</label>
        <input type="number" name="usage_limit" min="1" value="{{ old('usage_limit', $voucher?->usage_limit) }}" class="admin-input" placeholder="Để trống nếu không giới hạn">
        <p class="admin-help">Tổng số lượt sử dụng của voucher trên toàn hệ thống.</p>
    </div>

    <div>
        <label class="admin-label">Giới hạn mỗi tài khoản</label>
        <input type="number" name="per_user_limit" min="1" value="{{ old('per_user_limit', $voucher?->per_user_limit ?? 1) }}" class="admin-input" placeholder="Để trống nếu không giới hạn">
        <p class="admin-help">Số lần tối đa một tài khoản được sử dụng voucher này.</p>
    </div>

    <div>
        <label class="admin-label">Trạng thái *</label>
        <select name="status" required class="admin-input">
            <option value="active" {{ old('status', $voucher?->status ?? 'active') === 'active' ? 'selected' : '' }}>Đang bật</option>
            <option value="inactive" {{ old('status', $voucher?->status) === 'inactive' ? 'selected' : '' }}>Đã tắt</option>
        </select>
    </div>

    <div>
        <label class="admin-label">Bắt đầu</label>
        <input type="datetime-local" name="starts_at" value="{{ old('starts_at', $voucher?->starts_at?->format('Y-m-d\TH:i')) }}" class="admin-input">
    </div>

    <div>
        <label class="admin-label">Kết thúc</label>
        <input type="datetime-local" name="ends_at" value="{{ old('ends_at', $voucher?->ends_at?->format('Y-m-d\TH:i')) }}" class="admin-input">
    </div>
</div>

<div class="mt-8 flex flex-col sm:flex-row justify-end gap-3 border-t app-border pt-5">
    <a href="{{ route('admin.vouchers.index') }}" class="admin-btn-secondary">Hủy</a>
    <button type="submit" class="admin-btn-primary">
        <i class="ph-bold ph-floppy-disk"></i>
        Lưu voucher
    </button>
</div>
