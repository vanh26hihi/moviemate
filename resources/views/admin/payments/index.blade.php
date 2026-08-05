@extends('layouts.admin')

@section('title', 'Thanh toán - MovieMate')
@section('page-title', 'Thanh toán')

@section('content')
<div class="space-y-6">
    <header>
        <h1 class="text-3xl font-extrabold app-heading">Lịch sử giao dịch thanh toán</h1>
        <p class="mt-2 app-muted">Theo dõi các lần thanh toán và trạng thái xác minh từ nhà cung cấp. Mỗi dòng là một lần thử độc lập.</p>
    </header>

    <form method="GET" action="{{ route('admin.payments.index') }}" class="admin-toolbar grid gap-3 md:grid-cols-2 xl:grid-cols-4" aria-label="Bộ lọc thanh toán">
        <label class="cinema-label">Mã đặt vé<input class="cinema-input mt-1" name="booking_code" maxlength="60" value="{{ $filters['booking_code'] ?? '' }}"></label>
        <label class="cinema-label">Provider<select class="cinema-input mt-1" name="provider"><option value="">Tất cả</option>@foreach(\App\Models\Payment::SUPPORTED_PROVIDERS as $provider)<option value="{{ $provider }}" @selected(($filters['provider'] ?? '') === $provider)>{{ strtoupper($provider) }}</option>@endforeach</select></label>
        <label class="cinema-label">Mã tham chiếu<input class="cinema-input mt-1" name="reference" maxlength="100" value="{{ $filters['reference'] ?? '' }}" placeholder="Mã đơn hoặc giao dịch provider"></label>
        <label class="cinema-label">Trạng thái<select class="cinema-input mt-1" name="status"><option value="">Tất cả</option>@foreach(\App\Models\Payment::STATUSES as $value)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ \App\Support\StatusLabel::for('payment', $value) }}</option>@endforeach</select></label>
        <label class="cinema-label">Đã xác minh<select class="cinema-input mt-1" name="verified"><option value="">Tất cả</option><option value="yes" @selected(($filters['verified'] ?? '') === 'yes')>Có</option><option value="no" @selected(($filters['verified'] ?? '') === 'no')>Chưa</option></select></label>
        <label class="cinema-label">Cần review<select class="cinema-input mt-1" name="review"><option value="">Tất cả</option><option value="yes" @selected(($filters['review'] ?? '') === 'yes')>Có</option><option value="no" @selected(($filters['review'] ?? '') === 'no')>Không</option></select></label>
        <label class="cinema-label">Tạo từ ngày<input class="cinema-input mt-1" type="date" name="created_from" value="{{ $filters['created_from'] ?? '' }}"></label>
        <label class="cinema-label">Tạo đến ngày<input class="cinema-input mt-1" type="date" name="created_to" value="{{ $filters['created_to'] ?? '' }}"></label>
        <label class="cinema-label">Thanh toán từ ngày<input class="cinema-input mt-1" type="date" name="paid_from" value="{{ $filters['paid_from'] ?? '' }}"></label>
        <label class="cinema-label">Thanh toán đến ngày<input class="cinema-input mt-1" type="date" name="paid_to" value="{{ $filters['paid_to'] ?? '' }}"></label>
        <label class="cinema-label">Số tiền tối thiểu<input class="cinema-input mt-1" type="number" min="0" name="amount_min" value="{{ $filters['amount_min'] ?? '' }}"></label>
        <label class="cinema-label">Số tiền tối đa<input class="cinema-input mt-1" type="number" min="0" name="amount_max" value="{{ $filters['amount_max'] ?? '' }}"></label>
        <label class="cinema-label">Lệch số tiền<select class="cinema-input mt-1" name="amount_mismatch"><option value="">Tất cả</option><option value="yes" @selected(($filters['amount_mismatch'] ?? '') === 'yes')>Có lệch</option><option value="no" @selected(($filters['amount_mismatch'] ?? '') === 'no')>Khớp</option></select></label>
        <label class="cinema-label">Đã đối soát<select class="cinema-input mt-1" name="reconciled"><option value="">Tất cả</option><option value="yes" @selected(($filters['reconciled'] ?? '') === 'yes')>Đã có bằng chứng/truy vấn</option><option value="no" @selected(($filters['reconciled'] ?? '') === 'no')>Chưa</option></select></label>
        <label class="cinema-label">Sắp xếp<select class="cinema-input mt-1" name="sort"><option value="created_at" @selected(($filters['sort'] ?? 'created_at') === 'created_at')>Ngày tạo</option><option value="amount" @selected(($filters['sort'] ?? '') === 'amount')>Số tiền</option><option value="verified_at" @selected(($filters['sort'] ?? '') === 'verified_at')>Ngày xác minh</option><option value="last_queried_at" @selected(($filters['sort'] ?? '') === 'last_queried_at')>Lần truy vấn</option><option value="status" @selected(($filters['sort'] ?? '') === 'status')>Trạng thái</option></select></label>
        <label class="cinema-label">Thứ tự<select class="cinema-input mt-1" name="direction"><option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>Mới / cao trước</option><option value="asc" @selected(($filters['direction'] ?? '') === 'asc')>Cũ / thấp trước</option></select></label>
        <label class="cinema-label">Số dòng<select class="cinema-input mt-1" name="per_page">@foreach([15,25,50] as $size)<option value="{{ $size }}" @selected((int)($filters['per_page'] ?? 25) === $size)>{{ $size }}</option>@endforeach</select></label>
        <div class="flex flex-wrap items-end gap-2"><button class="btn-primary" type="submit"><i class="ph ph-funnel"></i>Lọc</button><a class="btn-secondary" href="{{ route('admin.payments.index') }}">Xóa bộ lọc</a></div>
    </form>

    <div class="cinema-card overflow-hidden"><div class="overflow-x-auto"><table class="admin-table min-w-[96rem]">
        <thead><tr><th>Giao dịch</th><th>Đặt vé</th><th>Nhà cung cấp</th><th>Mã tham chiếu</th><th class="text-right">Số tiền</th><th>Trạng thái</th><th>Cần xem xét</th><th>Vai trò</th><th>Provider ghi nhận</th><th>Xác minh</th><th>Đối soát</th><th>Ngày tạo</th><th></th></tr></thead>
        <tbody>@forelse($payments as $payment)
            @php($authoritative = $payment->booking?->authoritativePayment?->id === $payment->id)
            <tr>
                <td class="font-extrabold app-text">#{{ $payment->id }}</td>
                <td><a class="font-bold text-brand-start" href="{{ route('admin.bookings.show', $payment->booking_id) }}">{{ $payment->booking?->booking_code ?? '#'.$payment->booking_id }}</a></td>
                <td>{{ strtoupper($payment->provider) }}</td>
                <td><span class="block max-w-64 truncate" title="{{ $payment->app_trans_id ?: $payment->order_code ?: $payment->transaction_id ?: $payment->zp_trans_id }}">{{ $payment->app_trans_id ?: $payment->order_code ?: $payment->transaction_id ?: $payment->zp_trans_id ?: '—' }}</span></td>
                <td class="text-right whitespace-nowrap">{{ number_format($payment->amount, 0, ',', '.') }} {{ $payment->currency }}</td>
                <td><span class="status-badge {{ $payment->status === 'success' ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning' }}">{{ $payment->status_label }}</span></td>
                <td>{{ $payment->status === \App\Models\Payment::STATUS_REVIEW ? 'Có' : 'Không' }}</td>
                <td>@if($authoritative)<span class="status-badge bg-success/10 text-success">Có thẩm quyền</span>@else<span class="app-muted">Lần thử</span>@endif</td>
                <td>{{ $payment->provider_paid_at?->format('d/m/Y H:i') ?? 'Chưa có' }}</td>
                <td>{{ $payment->verified_at?->format('d/m/Y H:i') ?? 'Chưa xác minh' }}</td>
                <td>{{ $payment->last_queried_at?->format('d/m/Y H:i') ?? ($payment->verified_at?->format('d/m/Y H:i') ?? 'Chưa có') }}</td>
                <td class="whitespace-nowrap">{{ $payment->created_at?->format('d/m/Y H:i') }}</td>
                <td><a class="btn-secondary !px-3 !py-2 text-xs" href="{{ route('admin.payments.show', $payment) }}">Chi tiết</a></td>
            </tr>
        @empty<tr><td colspan="13" class="py-12 text-center app-muted">Không có giao dịch phù hợp.</td></tr>@endforelse</tbody>
    </table></div>@if($payments->hasPages())<div class="border-t app-border px-5 py-4">{{ $payments->links() }}</div>@endif</div>
</div>
@endsection
