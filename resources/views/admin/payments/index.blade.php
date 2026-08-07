@extends('layouts.admin')

@section('title', 'Thanh toán - MovieMate')
@section('page-title', 'Thanh toán')

@section('content')
<div class="space-y-6">
    <header>
        <h1 class="text-2xl font-extrabold app-heading sm:text-3xl">Thanh toán</h1>
        <p class="mt-2 app-muted">Theo dõi các giao dịch đã được xác minh thành công.</p>
    </header>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Tổng hợp giao dịch thành công">
        @foreach([
            ['Tổng giao dịch', number_format($summary['total']), 'ph-receipt', 'text-success'],
            ['Tổng doanh thu', number_format($summary['revenue'], 0, ',', '.').' VNĐ', 'ph-trend-up', 'text-brand-start'],
            ['Online', number_format($summary['online']), 'ph-globe', 'text-ai-start'],
            ['Tại quầy', number_format($summary['counter']), 'ph-storefront', 'text-warning'],
        ] as [$label, $value, $icon, $color])
            <article class="cinema-card flex items-center gap-4 p-4 sm:p-5">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-current/10 {{ $color }}"><i class="ph {{ $icon }} text-xl" aria-hidden="true"></i></span>
                <div class="min-w-0"><p class="text-sm app-muted">{{ $label }}</p><p class="mt-1 truncate text-xl font-black app-text">{{ $value }}</p></div>
            </article>
        @endforeach
    </section>

    <form method="GET" action="{{ route('admin.payments.index') }}" class="cinema-card p-4 sm:p-5" aria-label="Bộ lọc thanh toán thành công">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <label class="cinema-label xl:col-span-2">Mã đơn / tham chiếu
                <input class="cinema-input mt-1" name="search" maxlength="120" value="{{ $filters['search'] ?? '' }}" placeholder="Tìm mã đơn hoặc mã tham chiếu">
            </label>
            <label class="cinema-label">Chi nhánh
                <select class="cinema-input mt-1" name="cinema_id"><option value="">Tất cả chi nhánh được phép</option>@foreach($cinemas as $cinema)<option value="{{ $cinema->id }}" @selected((int) ($filters['cinema_id'] ?? 0) === $cinema->id)>{{ $cinema->name }}</option>@endforeach</select>
            </label>
            <label class="cinema-label">Phương thức
                <select class="cinema-input mt-1" name="provider"><option value="">Tất cả phương thức</option>@foreach([...\App\Models\Payment::SUPPORTED_PROVIDERS, \App\Models\Payment::PROVIDER_COUNTER_CASH] as $provider)<option value="{{ $provider }}" @selected(($filters['provider'] ?? '') === $provider)>{{ \App\Support\PaymentPresentation::providerLabel($provider) }}</option>@endforeach</select>
            </label>
            <label class="cinema-label">Kênh bán
                <select class="cinema-input mt-1" name="sales_channel"><option value="">Tất cả kênh</option><option value="online" @selected(($filters['sales_channel'] ?? '') === 'online')>Online</option><option value="counter" @selected(($filters['sales_channel'] ?? '') === 'counter')>Tại quầy</option></select>
            </label>
            <label class="cinema-label">Từ ngày<input class="cinema-input mt-1" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
            <label class="cinema-label">Đến ngày<input class="cinema-input mt-1" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
            <div class="flex flex-wrap items-end gap-2"><button class="btn-primary" type="submit"><i class="ph ph-funnel" aria-hidden="true"></i>Lọc</button><a class="btn-secondary" href="{{ route('admin.payments.index') }}">Đặt lại</a></div>
        </div>
        <details class="mt-3 rounded-xl border app-border px-4 py-3" @if(isset($filters['sort']) || isset($filters['direction']) || isset($filters['per_page'])) open @endif>
            <summary class="cursor-pointer text-sm font-bold app-text">Bộ lọc nâng cao</summary>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <label class="cinema-label">Sắp xếp<select class="cinema-input mt-1" name="sort"><option value="paid_at" @selected(($filters['sort'] ?? 'paid_at') === 'paid_at')>Thời gian xác minh / thu tiền</option><option value="amount" @selected(($filters['sort'] ?? '') === 'amount')>Số tiền</option><option value="booking_code" @selected(($filters['sort'] ?? '') === 'booking_code')>Mã đơn</option></select></label>
                <label class="cinema-label">Thứ tự<select class="cinema-input mt-1" name="direction"><option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>Mới / cao trước</option><option value="asc" @selected(($filters['direction'] ?? '') === 'asc')>Cũ / thấp trước</option></select></label>
                <label class="cinema-label">Số dòng<select class="cinema-input mt-1" name="per_page">@foreach([15,25,50] as $size)<option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 25) === $size)>{{ $size }}</option>@endforeach</select></label>
            </div>
        </details>
    </form>

    <div class="cinema-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="admin-table min-w-[60rem]">
                <thead><tr><th>Mã đơn</th><th>Khách hàng</th><th>Chi nhánh</th><th>Phương thức</th><th class="text-right">Số tiền</th><th>Kênh bán</th><th>Xác minh / Thu tiền lúc</th><th>Người thu tiền</th><th class="text-right">Thao tác</th></tr></thead>
                <tbody>
                    @forelse($payments as $payment)
                        <tr>
                            <td><a class="font-extrabold text-brand-start" href="{{ route('admin.bookings.show', $payment->booking_id) }}">{{ $payment->booking?->booking_code ?? '#'.$payment->booking_id }}</a></td>
                            <td><span class="font-bold app-text">{{ $payment->booking?->user?->name ?? $payment->booking?->customer_name ?? 'Khách đặt vé' }}</span><span class="mt-1 block text-xs app-muted">{{ $payment->booking?->recipient_email ? \App\Support\PrivacyMask::email($payment->booking->recipient_email) : \App\Support\PrivacyMask::phone($payment->booking?->customer_phone) }}</span></td>
                            <td>{{ $payment->booking?->showtime?->cinema?->name ?? '—' }}</td>
                            <td><span class="status-badge bg-success/10 text-success">{{ \App\Support\PaymentPresentation::providerLabel($payment->provider) }}</span></td>
                            <td class="text-right whitespace-nowrap font-extrabold app-text">{{ number_format((int) $payment->amount, 0, ',', '.') }} {{ $payment->currency }}</td>
                            <td><span class="status-badge {{ $payment->booking?->sales_channel === 'counter' ? 'bg-ai-start/10 text-ai-start' : 'bg-success/10 text-success' }}">{{ $payment->booking?->sales_channel === 'counter' ? 'Tại quầy' : 'Online' }}</span></td>
                            <td class="whitespace-nowrap">{{ $payment->successful_paid_at ? \Carbon\Carbon::parse($payment->successful_paid_at)->format('d/m/Y H:i') : '—' }}</td>
                            <td>{{ $payment->provider === \App\Models\Payment::PROVIDER_COUNTER_CASH ? ($payment->settledBy?->name ?? 'Không có dữ liệu') : 'Nhà cung cấp xác minh' }}</td>
                            <td class="text-right"><a class="btn-secondary !px-3 !py-2 text-xs" href="{{ route('admin.payments.show', $payment) }}">Xem chi tiết</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="py-12 text-center app-muted">Chưa có giao dịch thành công phù hợp.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($payments->hasPages())<div class="border-t app-border px-5 py-4">{{ $payments->links() }}</div>@endif
    </div>
</div>
@endsection
