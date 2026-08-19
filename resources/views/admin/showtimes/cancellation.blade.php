@extends('layouts.admin')

@section('title', 'Xử lý hủy suất chiếu - MovieMate')
@section('page-title', 'Xử lý hủy suất chiếu')

@section('content')
<div class="mx-auto max-w-5xl space-y-6" data-showtime-cancellation-review>
    <header>
        <a href="{{ route('admin.showtimes.show', $showtime) }}" class="inline-flex items-center gap-2 text-sm font-bold text-brand-start"><i class="ph-bold ph-arrow-left" aria-hidden="true"></i>Quay lại chi tiết suất chiếu</a>
        <p class="mt-5 text-xs font-extrabold uppercase tracking-[0.18em] text-error">Tác vụ có ảnh hưởng đến khách hàng</p>
        <h1 class="admin-page-title mt-2">Xác nhận hủy suất chiếu</h1>
        <p class="admin-page-subtitle">{{ $showtime->movie->title }} · {{ $showtime->show_date->format('d/m/Y') }} {{ \Carbon\Carbon::parse($showtime->show_time)->format('H:i') }} · {{ $showtime->cinema->name }} · {{ $showtime->room->name }}</p>
    </header>

    @if($errors->any())
        <section id="cancellation-error-summary" role="alert" tabindex="-1" class="rounded-2xl border border-error/40 bg-error/10 p-5 focus:outline-none focus:ring-2 focus:ring-error" aria-labelledby="cancellation-error-title">
            <h2 id="cancellation-error-title" class="font-extrabold text-error">Chưa thể hủy suất chiếu</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm app-text">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </section>
        <script>document.addEventListener('DOMContentLoaded',()=>document.getElementById('cancellation-error-summary')?.focus());</script>
    @endif

    <section class="cinema-card p-5 sm:p-6" aria-labelledby="impact-summary-title">
        <div class="flex items-start gap-3">
            <i class="ph-duotone ph-warning-octagon mt-0.5 text-3xl text-error" aria-hidden="true"></i>
            <div><h2 id="impact-summary-title" class="text-xl font-extrabold app-heading">Tác động được tính lại từ dữ liệu hiện tại</h2><p class="mt-1 text-sm app-muted">Hệ thống sẽ khóa và phân loại lại toàn bộ đơn trong transaction khi bạn xác nhận.</p></div>
        </div>
        <dl class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-xl app-secondary p-4"><dt class="text-sm app-muted">Tổng đơn</dt><dd class="mt-1 text-2xl font-extrabold app-text">{{ $impact['booking_count'] }}</dd></div>
            <div class="rounded-xl app-secondary p-4"><dt class="text-sm app-muted">Đơn chờ thanh toán</dt><dd class="mt-1 text-2xl font-extrabold app-text">{{ $impact['pending_count'] }}</dd></div>
            <div class="rounded-xl app-secondary p-4"><dt class="text-sm app-muted">Đơn đã thanh toán</dt><dd class="mt-1 text-2xl font-extrabold text-error">{{ $impact['paid_count'] }}</dd></div>
            <div class="rounded-xl app-secondary p-4"><dt class="text-sm app-muted">Ghế lịch sử</dt><dd class="mt-1 text-2xl font-extrabold app-text">{{ $impact['seat_count'] }}</dd></div>
            <div class="rounded-xl border border-error/30 bg-error/10 p-4 sm:col-span-2 xl:col-span-1"><dt class="text-sm text-error">Dự kiến cần hoàn</dt><dd class="mt-1 text-xl font-extrabold text-error">{{ number_format($impact['refund_amount'], 0, ',', '.') }} VNĐ</dd></div>
            <div class="rounded-xl app-secondary p-4"><dt class="text-sm app-muted">Vé đã tạo / đã in</dt><dd class="mt-1 text-xl font-extrabold app-text">{{ $impact['admission_ticket_count'] }} / {{ $impact['printed_ticket_count'] }}</dd></div>
            <div class="rounded-xl app-secondary p-4"><dt class="text-sm app-muted">Đơn có đồ ăn</dt><dd class="mt-1 text-xl font-extrabold app-text">{{ $impact['food_booking_count'] }}</dd></div>
            <div class="rounded-xl app-secondary p-4"><dt class="text-sm app-muted">Phiếu đồ ăn / đã in</dt><dd class="mt-1 text-xl font-extrabold app-text">{{ $impact['voucher_count'] }} / {{ $impact['printed_voucher_count'] }}</dd></div>
        </dl>
        <div class="mt-5 grid gap-3 md:grid-cols-2">
            <p class="rounded-xl bg-warning/10 p-4 text-sm app-text"><strong>Đơn chưa thanh toán:</strong> bị hủy, khóa ghế được giải phóng, không phát sinh hoàn tiền.</p>
            <p class="rounded-xl bg-error/10 p-4 text-sm app-text"><strong>Đơn đã thanh toán:</strong> Payment gốc được giữ nguyên và tạo nghĩa vụ hoàn tiền thủ công.</p>
        </div>
    </section>

    <form method="POST" action="{{ route('admin.showtimes.destroy', $showtime) }}" class="cinema-card p-5 sm:p-6" data-submit-once>
        @csrf
        @method('DELETE')
        <h2 class="text-xl font-extrabold app-heading">Lý do và xác nhận</h2>
        <div class="mt-5 space-y-5">
            <div>
                <label for="reason_code" class="admin-label">Lý do hủy <span class="text-error">*</span></label>
                <select id="reason_code" name="reason_code" class="admin-input" required aria-describedby="reason-code-help @error('reason_code') reason-code-error @enderror">
                    <option value="">Chọn lý do</option>
                    @foreach($reasons as $code => $label)<option value="{{ $code }}" @selected(old('reason_code') === $code)>{{ $label }}</option>@endforeach
                </select>
                <p id="reason-code-help" class="mt-1 text-xs app-muted">Lý do này xuất hiện trong nhật ký vận hành; khách hàng chỉ thấy thông báo rạp đã hủy suất.</p>
                @error('reason_code')<p id="reason-code-error" class="mt-1 text-sm font-bold text-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="reason_note" class="admin-label">Ghi chú vận hành</label>
                <textarea id="reason_note" name="reason_note" rows="4" maxlength="500" class="admin-input" aria-describedby="reason-note-help @error('reason_note') reason-note-error @enderror">{{ old('reason_note') }}</textarea>
                <p id="reason-note-help" class="mt-1 text-xs app-muted">Tối đa 500 ký tự; bắt buộc khi chọn “Lý do khác”. Không nhập dữ liệu thanh toán nhạy cảm.</p>
                @error('reason_note')<p id="reason-note-error" class="mt-1 text-sm font-bold text-error">{{ $message }}</p>@enderror
            </div>
            <label class="flex items-start gap-3 rounded-xl border app-border p-4">
                <input type="checkbox" name="confirm_cancellation" value="1" class="mt-1 h-5 w-5 rounded border app-border" @checked(old('confirm_cancellation')) required>
                <span class="text-sm app-text"><strong>Tôi xác nhận hủy suất chiếu này.</strong><span class="mt-1 block app-muted">Tôi hiểu đơn đã thanh toán sẽ cần xử lý hoàn tiền bên ngoài hệ thống và vé/QR/phiếu nhận đồ sẽ không còn dùng được.</span></span>
            </label>
            @error('confirm_cancellation')<p class="text-sm font-bold text-error">{{ $message }}</p>@enderror
        </div>
        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('admin.showtimes.show', $showtime) }}" class="admin-btn-secondary">Không hủy</a>
            <button type="submit" class="admin-btn-danger"><i class="ph-bold ph-x-circle" aria-hidden="true"></i>Xác nhận hủy suất chiếu</button>
        </div>
    </form>
</div>
@endsection
