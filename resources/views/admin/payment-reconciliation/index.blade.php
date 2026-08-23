@extends('layouts.admin')

@section('title', 'Đối soát giao dịch - MovieMate')
@section('page-title', 'Đối soát giao dịch')

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div><h1 class="text-3xl font-extrabold app-heading">Đối soát giao dịch</h1><p class="mt-2 app-muted">Kiểm tra các giao dịch cần xác minh hoặc có sai lệch với dữ liệu nhà cung cấp. Không tự suy đoán kết quả provider.</p></div>
        <a class="btn-secondary" href="{{ route('admin.payment-reconciliation.export') }}"><i class="ph ph-download-simple"></i>Xuất hàng đợi</a>
        <form method="GET"><label class="cinema-label">Số dòng<select class="cinema-input mt-1" name="per_page" onchange="this.form.submit()">@foreach([15,25,50] as $size)<option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>@endforeach</select></label></form>
    </header>
    <div class="cinema-card overflow-hidden"><div class="overflow-x-auto"><table class="admin-table min-w-[86rem]">
        <thead><tr><th>Ưu tiên</th><th>Lý do</th><th>Giao dịch</th><th>Đặt vé</th><th>Provider</th><th class="text-right">Tiền booking</th><th class="text-right">Tiền giao dịch</th><th>Trạng thái local</th><th>Kết quả provider</th><th>Lần kiểm tra</th><th>Thao tác an toàn</th></tr></thead>
        <tbody>@forelse($payments as $payment)<tr>
            <td><span class="status-badge {{ $payment->reconciliation_priority === 'Khẩn cấp' ? 'bg-error/10 text-error' : ($payment->reconciliation_priority === 'Cao' ? 'bg-warning/10 text-warning' : 'bg-ai-start/10 text-ai-start') }}">{{ $payment->reconciliation_priority }}</span></td>
            <td class="max-w-80">{{ $payment->reconciliation_reason }}</td>
            <td><a class="font-extrabold text-brand-start" href="{{ route('admin.payments.show', $payment) }}">#{{ $payment->id }}</a></td>
            <td><a class="font-bold text-brand-start" href="{{ route('admin.bookings.show', $payment->booking_id) }}">{{ $payment->booking?->booking_code ?? '#'.$payment->booking_id }}</a></td>
            <td>{{ \App\Support\PaymentPresentation::providerLabel($payment->provider) }}</td>
            <td class="text-right whitespace-nowrap">{{ number_format((int) $payment->booking?->total_amount, 0, ',', '.') }} VNĐ</td>
            <td class="text-right whitespace-nowrap">{{ number_format($payment->amount, 0, ',', '.') }} {{ $payment->currency }}</td>
            <td>{{ $payment->status_label }}</td><td>{{ \App\Support\PaymentPresentation::providerCategory($payment) }}</td><td>{{ $payment->last_queried_at?->format('d/m/Y H:i') ?? 'Chưa truy vấn' }}</td>
            <td><div class="flex flex-wrap gap-2">
                @if(in_array($payment->status, \App\Models\Payment::RECONCILABLE_STATUSES, true))<form method="POST" action="{{ route('admin.payments.query-provider', $payment) }}" onsubmit="return confirm('Truy vấn trực tiếp trạng thái hiện tại từ provider?');">@csrf<button class="btn-secondary !px-3 !py-2 text-xs" type="submit">Truy vấn provider</button></form>@endif
                @if(in_array($payment->status, \App\Models\Payment::UNSAFE_RETRY_STATUSES, true) && ($payment->status !== \App\Models\Payment::STATUS_REVIEW || in_array($payment->provider, \App\Models\Payment::SUPPORTED_PROVIDERS, true)))<form method="POST" action="{{ route('admin.payments.reconcile', $payment) }}" onsubmit="return confirm('Đối soát giao dịch hiện có bằng provider, không ghi đè thủ công?');">@csrf<button class="btn-primary !px-3 !py-2 text-xs" type="submit">Đối soát</button></form>@endif
            </div></td>
        </tr>@empty<tr><td colspan="11" class="py-12 text-center app-muted">Không có giao dịch nào cần chú ý theo các điều kiện hiện có.</td></tr>@endforelse</tbody>
    </table></div>@if($payments->hasPages())<div class="border-t app-border px-5 py-4">{{ $payments->links() }}</div>@endif</div>
    <section class="cinema-card p-5"><h2 class="font-extrabold app-heading">Giới hạn dữ liệu</h2><p class="mt-2 text-sm app-muted">Hệ thống không thể kết luận bất đồng Return/IPN nếu provider chưa gửi hoặc schema chưa lưu đủ hai bằng chứng đó. Các trường hợp như vậy chỉ được đưa vào hàng đợi khi có trạng thái unresolved/review hoặc dấu hiệu sai lệch khác đã lưu.</p></section>
</div>
@endsection
