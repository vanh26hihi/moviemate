@extends('layouts.admin')

@section('title', 'Hoàn tiền cần xử lý - MovieMate')
@section('page-title', 'Hoàn tiền cần xử lý')

@section('content')
<div class="space-y-6" data-refund-queue>
    <header class="admin-page-header items-start">
        <div><p class="text-xs font-extrabold uppercase tracking-[0.18em] text-brand-start">Đối soát thủ công</p><h1 class="admin-page-title mt-2">Hoàn tiền cần xử lý</h1><p class="admin-page-subtitle max-w-3xl">Đây là danh sách nghĩa vụ do rạp hủy suất. MovieMate không tự chuyển tiền; chỉ ghi nhận sau khi hoàn tiền thực tế bên ngoài hệ thống.</p></div>
    </header>

    @if($errors->any())
        <section id="refund-error-summary" role="alert" tabindex="-1" class="rounded-2xl border border-error/40 bg-error/10 p-5 focus:outline-none focus:ring-2 focus:ring-error"><h2 class="font-extrabold text-error">Chưa thể ghi nhận hoàn tiền</h2><ul class="mt-2 list-disc pl-5 text-sm">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>
        <script>document.addEventListener('DOMContentLoaded',()=>document.getElementById('refund-error-summary')?.focus());</script>
    @endif

    <nav class="flex flex-wrap gap-2" aria-label="Lọc trạng thái hoàn tiền">
        <a href="{{ route('admin.refunds.index', ['status' => 'required']) }}" class="{{ $status === 'required' ? 'admin-btn-primary' : 'admin-btn-secondary' }}">Cần xử lý</a>
        <a href="{{ route('admin.refunds.index', ['status' => 'resolved']) }}" class="{{ $status === 'resolved' ? 'admin-btn-primary' : 'admin-btn-secondary' }}">Đã ghi nhận</a>
        <a href="{{ route('admin.refunds.index', ['status' => 'all']) }}" class="{{ $status === 'all' ? 'admin-btn-primary' : 'admin-btn-secondary' }}">Tất cả</a>
    </nav>

    <section class="space-y-4" aria-label="Danh sách nghĩa vụ hoàn tiền">
        @forelse($refundCases as $case)
            <article class="cinema-card overflow-hidden" data-refund-case="{{ $case->id }}">
                <div class="grid gap-4 p-5 sm:p-6 lg:grid-cols-[1.1fr_1.2fr_1fr_auto]">
                    <div><p class="text-xs font-bold uppercase tracking-wide app-muted">Chi nhánh · đơn</p><p class="mt-1 font-extrabold app-text">{{ $case->cinema->name }}</p><a href="{{ route('admin.bookings.show', $case->booking) }}" class="mt-1 inline-block font-mono text-sm font-bold text-brand-start">{{ $case->booking->booking_code }}</a><p class="mt-1 text-xs app-muted">{{ $case->booking->customer_name ?: $case->booking->customer_email ?: $case->booking->customer_phone ?: 'Khách đặt vé' }}</p></div>
                    <div><p class="text-xs font-bold uppercase tracking-wide app-muted">Suất bị hủy</p><p class="mt-1 font-bold app-text">{{ $case->booking->showtime?->movie?->title }}</p><p class="mt-1 text-sm app-muted">{{ $case->booking->showtime?->show_date?->format('d/m/Y') }} {{ $case->booking->showtime?->show_time ? \Carbon\Carbon::parse($case->booking->showtime->show_time)->format('H:i') : '' }}</p><p class="mt-1 text-xs app-muted">Hủy {{ $case->cancellation->cancelled_at->diffForHumans() }} · {{ $case->cancellation->cancelledBy?->name ?? 'Hệ thống' }}</p></div>
                    <div><p class="text-xs font-bold uppercase tracking-wide app-muted">Payment gốc</p><a href="{{ route('admin.payments.show', $case->payment) }}" class="mt-1 inline-block font-bold text-brand-start">#{{ $case->payment_id }} · {{ \App\Support\PaymentPresentation::providerLabel($case->payment->provider) }}</a><p class="mt-1 text-xl font-extrabold app-text">{{ number_format($case->required_amount, 0, ',', '.') }} {{ $case->currency === 'VND' ? 'VNĐ' : $case->currency }}</p></div>
                    <div class="lg:text-right"><span class="status-badge {{ $case->status === 'required' ? 'bg-error/10 text-error' : 'bg-success/10 text-success' }}">{{ $case->status === 'required' ? 'Cần xử lý hoàn tiền' : 'Đã ghi nhận hoàn tiền' }}</span><p class="mt-2 text-xs app-muted">Tạo {{ $case->created_at->format('d/m/Y H:i') }}</p></div>
                </div>

                @if($case->status === 'required')
                    @can('refunds.resolve')
                    <details class="border-t app-border p-5 sm:p-6" @if($errors->any() && old('refund_case_id') == $case->id) open @endif>
                        <summary class="cursor-pointer font-extrabold text-brand-start">Nhập bằng chứng hoàn tiền đã thực hiện</summary>
                        <form method="POST" action="{{ route('admin.refunds.update', $case) }}" class="mt-5 grid gap-4 md:grid-cols-2" data-submit-once>
                            @csrf @method('PATCH')
                            <input type="hidden" name="refund_case_id" value="{{ $case->id }}">
                            <div><label class="admin-label" for="resolved_amount_{{ $case->id }}">Số tiền đã hoàn</label><input class="admin-input" id="resolved_amount_{{ $case->id }}" name="resolved_amount" type="number" min="0" step="1" value="{{ old('refund_case_id') == $case->id ? old('resolved_amount') : $case->required_amount }}" required><p class="mt-1 text-xs app-muted">Phải khớp chính xác {{ number_format($case->required_amount, 0, ',', '.') }} {{ $case->currency }}.</p></div>
                            <div><label class="admin-label" for="resolution_method_{{ $case->id }}">Phương thức thực tế</label><select class="admin-input" id="resolution_method_{{ $case->id }}" name="resolution_method" required><option value="">Chọn phương thức</option>@foreach(\App\Models\RefundCase::RESOLUTION_METHODS as $code => $label)<option value="{{ $code }}" @selected(old('refund_case_id') == $case->id && old('resolution_method') === $code)>{{ $label }}</option>@endforeach</select></div>
                            <div><label class="admin-label" for="resolution_reference_{{ $case->id }}">Mã tham chiếu / bằng chứng</label><input class="admin-input" id="resolution_reference_{{ $case->id }}" name="resolution_reference" maxlength="200" value="{{ old('refund_case_id') == $case->id ? old('resolution_reference') : '' }}" required></div>
                            <div><label class="admin-label" for="resolution_note_{{ $case->id }}">Ghi chú</label><textarea class="admin-input" id="resolution_note_{{ $case->id }}" name="resolution_note" rows="3" maxlength="500">{{ old('refund_case_id') == $case->id ? old('resolution_note') : '' }}</textarea></div>
                            <label class="flex items-start gap-3 rounded-xl border app-border p-4 md:col-span-2"><input type="checkbox" class="mt-1 h-5 w-5" name="confirm_resolution" value="1" required><span class="text-sm app-text"><strong>Tôi xác nhận tiền đã được hoàn thực tế bên ngoài MovieMate.</strong><span class="mt-1 block app-muted">Thao tác này chỉ ghi nhận bằng chứng, không gửi lệnh chuyển tiền và không sửa Payment gốc.</span></span></label>
                            <div class="md:col-span-2 md:text-right"><button type="submit" class="admin-btn-primary">Ghi nhận hoàn tiền</button></div>
                        </form>
                    </details>
                    @endcan
                @else
                    <div class="border-t app-border p-5 text-sm app-muted sm:p-6"><strong class="app-text">{{ $case->resolvedBy?->name ?? 'Hệ thống' }}</strong> ghi nhận lúc {{ $case->resolved_at?->format('d/m/Y H:i') }} · {{ \App\Models\RefundCase::RESOLUTION_METHODS[$case->resolution_method] ?? $case->resolution_method }} · Tham chiếu: <span class="font-mono app-text">{{ $case->resolution_reference }}</span>@if($case->resolution_note)<p class="mt-2">{{ $case->resolution_note }}</p>@endif</div>
                @endif
            </article>
        @empty
            <div class="cinema-card p-12 text-center"><i class="ph-duotone ph-check-circle text-5xl text-success" aria-hidden="true"></i><h2 class="mt-3 text-xl font-extrabold app-heading">Không có nghĩa vụ phù hợp</h2><p class="mt-2 app-muted">Hàng đợi hiện không có mục ở trạng thái đã chọn.</p></div>
        @endforelse
    </section>

    @if($refundCases->hasPages())<div>{{ $refundCases->links() }}</div>@endif
</div>
@endsection
