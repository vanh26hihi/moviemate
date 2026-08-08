@extends('layouts.admin')

@section('title', 'Giao dịch #'.$payment->id.' - MovieMate')
@section('page-title', 'Chi tiết thanh toán')

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <a href="{{ route('admin.payments.index') }}" class="mb-3 inline-flex items-center gap-2 text-sm font-bold text-brand-start"><i class="ph ph-arrow-left"></i>Về danh sách thanh toán</a>
            <h1 class="text-3xl font-extrabold app-heading">Giao dịch #{{ $payment->id }}</h1>
            <p class="mt-2 app-muted">{{ \App\Support\PaymentPresentation::providerLabel($payment->provider) }} · {{ $payment->status_label }} · {{ $payment->status === \App\Models\Payment::STATUS_REVIEW ? 'Cần xem xét' : 'Không ở trạng thái review' }} @if($isAuthoritative)· Giao dịch có thẩm quyền @endif</p>
        </div>
        @can('payments.reconcile')<div class="flex flex-wrap gap-2">
            @if(in_array($payment->status, \App\Models\Payment::RECONCILABLE_STATUSES, true))<form method="POST" action="{{ route('admin.payments.query-provider', $payment) }}" onsubmit="return confirm('Truy vấn trực tiếp giao dịch này từ provider?');">@csrf<button class="btn-secondary" type="submit"><i class="ph ph-magnifying-glass"></i>Truy vấn provider</button></form>@endif
            @if(in_array($payment->status, \App\Models\Payment::UNSAFE_RETRY_STATUSES, true) && ($payment->status !== \App\Models\Payment::STATUS_REVIEW || in_array($payment->provider, \App\Models\Payment::SUPPORTED_PROVIDERS, true)))<form method="POST" action="{{ route('admin.payments.reconcile', $payment) }}" onsubmit="return confirm('Đối soát bằng dữ liệu provider hiện có? Hệ thống không cho phép ép thành công.');">@csrf<button class="btn-primary" type="submit"><i class="ph ph-arrows-clockwise"></i>Đối soát</button></form>@endif
        </div>@endcan
    </header>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="cinema-card p-5"><p class="text-sm app-muted">Trạng thái local</p><p class="mt-2 font-extrabold app-text">{{ $payment->status_label }}</p></div>
        <div class="cinema-card p-5"><p class="text-sm app-muted">Số tiền</p><p class="mt-2 text-xl font-extrabold text-brand-start">{{ number_format($payment->amount, 0, ',', '.') }} {{ $payment->currency }}</p></div>
        <div class="cinema-card p-5"><p class="text-sm app-muted">Xác minh</p><p class="mt-2 font-extrabold app-text">{{ $payment->verified_at?->format('d/m/Y H:i:s') ?? 'Chưa xác minh' }}</p></div>
        <div class="cinema-card p-5"><p class="text-sm app-muted">Vai trò</p><p class="mt-2 font-extrabold {{ $isAuthoritative ? 'text-success' : 'app-text' }}">{{ $isAuthoritative ? 'Giao dịch có thẩm quyền' : 'Lần thử thanh toán' }}</p></div>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="cinema-card p-6"><h2 class="text-xl font-extrabold app-heading">Thông tin giao dịch</h2><dl class="mt-4 grid gap-4 sm:grid-cols-2">
            <div><dt class="text-sm app-muted">Provider</dt><dd class="font-bold app-text">{{ \App\Support\PaymentPresentation::providerLabel($payment->provider) }}</dd></div>
            <div><dt class="text-sm app-muted">Mã đơn provider</dt><dd class="break-all font-bold app-text">{{ $payment->app_trans_id ?: $payment->order_code ?: '—' }}</dd></div>
            <div><dt class="text-sm app-muted">Mã giao dịch provider</dt><dd class="break-all font-bold app-text">{{ $payment->transaction_id ?: $payment->zp_trans_id ?: '—' }}</dd></div>
            <div><dt class="text-sm app-muted">Mã phản hồi</dt><dd class="font-bold app-text">{{ $payment->response_code ?? $payment->provider_return_code ?? '—' }}</dd></div>
            <div><dt class="text-sm app-muted">Trạng thái provider</dt><dd class="font-bold app-text">{{ $payment->transaction_status ?? 'Chưa có' }}</dd></div>
            <div><dt class="text-sm app-muted">Provider ghi nhận thanh toán</dt><dd class="font-bold app-text">{{ $payment->provider_paid_at?->format('d/m/Y H:i:s') ?? 'Chưa có' }}</dd></div>
            <div><dt class="text-sm app-muted">Hệ thống ghi nhận thanh toán</dt><dd class="font-bold app-text">{{ $payment->paid_at?->format('d/m/Y H:i:s') ?? 'Chưa có' }}</dd></div>
            <div><dt class="text-sm app-muted">Người thu tiền mặt</dt><dd class="font-bold app-text">{{ $payment->settledBy?->name ?? '—' }}</dd></div>
            <div><dt class="text-sm app-muted">Thu tiền mặt lúc</dt><dd class="font-bold app-text">{{ $payment->settled_at?->format('d/m/Y H:i:s') ?? '—' }}</dd></div>
            <div><dt class="text-sm app-muted">Callback nhận lúc</dt><dd class="font-bold app-text">{{ $payment->callback_received_at?->format('d/m/Y H:i:s') ?? 'Chưa có' }}</dd></div>
            <div><dt class="text-sm app-muted">Đối soát gần nhất</dt><dd class="font-bold app-text">{{ $payment->last_queried_at?->format('d/m/Y H:i:s') ?? 'Chưa truy vấn' }}</dd></div>
            <div><dt class="text-sm app-muted">Tạo lúc</dt><dd class="font-bold app-text">{{ $payment->created_at?->format('d/m/Y H:i:s') ?? '—' }}</dd></div>
            <div><dt class="text-sm app-muted">Cập nhật lúc</dt><dd class="font-bold app-text">{{ $payment->updated_at?->format('d/m/Y H:i:s') ?? '—' }}</dd></div>
            <div><dt class="text-sm app-muted">Hạn đối soát</dt><dd class="font-bold app-text">{{ $payment->reconcile_until?->format('d/m/Y H:i:s') ?? 'Không có' }}</dd></div>
            <div><dt class="text-sm app-muted">Kết quả provider</dt><dd class="font-bold app-text">{{ \App\Support\PaymentPresentation::providerCategory($payment) }}</dd></div>
            <div><dt class="text-sm app-muted">Lý do cần xem xét</dt><dd class="font-bold app-text">{{ \App\Support\PaymentPresentation::reason($payment->failure_reason) }}</dd></div>
        </dl></section>

        <section class="cinema-card p-6"><h2 class="text-xl font-extrabold app-heading">Đơn đặt vé</h2><dl class="mt-4 grid gap-4 sm:grid-cols-2">
            <div><dt class="text-sm app-muted">Mã đặt vé</dt><dd><a class="font-extrabold text-brand-start" href="{{ route('admin.bookings.show', $payment->booking_id) }}">{{ $payment->booking?->booking_code ?? '#'.$payment->booking_id }}</a></dd></div>
            <div><dt class="text-sm app-muted">Tổng đơn</dt><dd class="font-bold app-text">{{ number_format((int) $payment->booking?->total_amount, 0, ',', '.') }} VNĐ</dd></div>
            <div><dt class="text-sm app-muted">Trạng thái đặt vé</dt><dd class="font-bold app-text">{{ \App\Support\StatusLabel::for('booking_admin', $payment->booking?->booking_status) }}</dd></div>
            <div><dt class="text-sm app-muted">Trạng thái thanh toán đơn</dt><dd class="font-bold app-text">{{ \App\Support\StatusLabel::for('booking_payment', $payment->booking?->payment_status) }}</dd></div>
            <div><dt class="text-sm app-muted">Khách hàng</dt><dd class="font-bold app-text">{{ $customerNameMasked }} · {{ $recipientEmailMasked }}</dd></div>
            <div><dt class="text-sm app-muted">Phim / phòng</dt><dd class="font-bold app-text">{{ $payment->booking?->showtime?->movie?->title ?? '—' }} · {{ $payment->booking?->showtime?->room?->code ?? '—' }}</dd></div>
            <div><dt class="text-sm app-muted">Suất chiếu</dt><dd class="font-bold app-text">{{ $payment->booking?->showtime_label ?? '—' }}</dd></div>
        </dl></section>
    </div>

    <section class="cinema-card p-6"><h2 class="text-xl font-extrabold app-heading">Bằng chứng xác minh</h2><p class="mt-1 text-sm app-muted">“Chưa xác định” không được hiểu là thất bại hoặc thành công.</p><div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        @foreach(['provider'=>'Xác thực chữ ký/provider','amount'=>'Khớp số tiền','booking'=>'Khớp đơn','transaction'=>'Mã giao dịch duy nhất','result'=>'Kết quả provider được chấp nhận','finalization'=>'Hoàn tất nhất quán'] as $key => $label)<div class="rounded-xl border app-border p-4"><p class="text-sm app-muted">{{ $label }}</p><p class="mt-2 font-bold {{ $evidence[$key]['state'] === 'yes' ? 'text-success' : ($evidence[$key]['state'] === 'no' ? 'text-error' : 'text-warning') }}">{{ $evidence[$key]['label'] }}</p></div>@endforeach
    </div></section>

    <section class="cinema-card overflow-hidden"><div class="border-b app-border p-6"><h2 class="text-xl font-extrabold app-heading">Các lần thử liên quan</h2>@if($attemptsTruncated)<p class="mt-1 text-sm app-muted">Chỉ hiển thị 100 lần thử mới nhất.</p>@endif</div><div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>ID</th><th>Provider</th><th class="text-right">Số tiền</th><th>Trạng thái</th><th>Vai trò</th><th>Xác minh</th><th>Ngày tạo</th></tr></thead><tbody>@foreach($attempts as $attempt)<tr class="{{ $attempt->id === $payment->id ? 'bg-brand-start/5' : '' }}"><td><a class="font-bold text-brand-start" href="{{ route('admin.payments.show', $attempt) }}">#{{ $attempt->id }}</a></td><td>{{ \App\Support\PaymentPresentation::providerLabel($attempt->provider) }}</td><td class="text-right">{{ number_format($attempt->amount, 0, ',', '.') }} {{ $attempt->currency }}</td><td>{{ $attempt->status_label }}</td><td>@if($attempt->id === $payment->id)<span class="font-bold text-brand-start">Đang xem</span>@endif @if($payment->booking?->authoritativePayment?->id === $attempt->id)<span class="status-badge bg-success/10 text-success">Có thẩm quyền</span>@elseif($attempt->id !== $payment->id)<span class="app-muted">Lần thử</span>@endif</td><td>{{ $attempt->verified_at?->format('d/m/Y H:i') ?? 'Chưa' }}</td><td>{{ $attempt->created_at?->format('d/m/Y H:i') }}</td></tr>@endforeach</tbody></table></div></section>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="cinema-card overflow-hidden"><div class="border-b app-border p-6"><h2 class="text-xl font-extrabold app-heading">Lịch sử đối soát review</h2></div><div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Thời gian</th><th>Người thực hiện</th><th>Kết quả</th><th>Trạng thái</th></tr></thead><tbody>@forelse($reviewEvents as $event)<tr><td>{{ $event->created_at?->format('d/m/Y H:i:s') }}</td><td>{{ $event->actor?->name ?? '#'.$event->actor_user_id }}</td><td>{{ \App\Support\PaymentPresentation::reviewCategory($event->provider_result_category) }}</td><td>{{ \App\Support\StatusLabel::for('payment', $event->previous_status) }} → {{ \App\Support\StatusLabel::for('payment', $event->resulting_status) }}</td></tr>@empty<tr><td colspan="4" class="py-8 text-center app-muted">Chưa có sự kiện đối soát review.</td></tr>@endforelse</tbody></table></div></section>
        @can('activity_logs.view')<section class="cinema-card overflow-hidden"><div class="border-b app-border p-6"><h2 class="text-xl font-extrabold app-heading">Nhật ký thao tác</h2></div><div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Thời gian</th><th>Người thực hiện</th><th>Hành động</th></tr></thead><tbody>@forelse($activityLogs as $log)<tr><td>{{ $log->created_at?->format('d/m/Y H:i:s') }}</td><td>{{ $log->actor?->name ?? 'Hệ thống' }}</td><td><a class="font-bold text-brand-start" href="{{ route('admin.activity-logs.show', $log) }}">{{ $log->action }}</a></td></tr>@empty<tr><td colspan="3" class="py-8 text-center app-muted">Chưa có nhật ký thao tác.</td></tr>@endforelse</tbody></table></div></section>@endcan
    </div>
</div>
@endsection
