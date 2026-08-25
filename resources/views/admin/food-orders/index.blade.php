@extends('layouts.admin')

@section('title', 'Đơn đồ ăn thành công - MovieMate')
@section('page-title', 'Đơn đồ ăn')

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <span class="status-badge bg-success/10 text-success"><i class="ph-fill ph-check-circle" aria-hidden="true"></i> Chỉ đơn thành công</span>
            <h1 class="mt-3 text-2xl font-extrabold app-heading sm:text-3xl">Đơn đồ ăn nhận tại rạp</h1>
            <p class="mt-2 max-w-3xl app-muted">Tra cứu các đơn đã thanh toán và có bằng chứng xác minh. Đơn chờ thanh toán, hết hạn hoặc đã hủy không xuất hiện tại đây.</p>
        </div>
        @can('tickets.lookup')
            <a href="{{ route('staff.tickets.index') }}" class="btn-secondary shrink-0"><i class="ph ph-qr-code" aria-hidden="true"></i>Tra cứu &amp; in đơn</a>
        @endcan
    </header>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Tổng hợp đơn đồ ăn thành công">
        @foreach([
            ['Đơn thành công', number_format($summary['total']), 'ph-receipt', 'text-success'],
            ['Số phần món', number_format($summary['item_quantity']), 'ph-popcorn', 'text-ai-start'],
            ['Doanh thu đồ ăn', number_format($summary['revenue'], 0, ',', '.').' VNĐ', 'ph-trend-up', 'text-brand-start'],
            ['Phiếu chưa in', number_format($summary['unprinted']), 'ph-printer', 'text-warning'],
        ] as [$label, $value, $icon, $color])
            <article class="cinema-card flex items-center gap-4 p-4 sm:p-5">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-current/10 {{ $color }}"><i class="ph {{ $icon }} text-xl" aria-hidden="true"></i></span>
                <div class="min-w-0"><p class="text-sm app-muted">{{ $label }}</p><p class="mt-1 truncate text-xl font-black app-text">{{ $value }}</p></div>
            </article>
        @endforeach
    </section>

    <section class="cinema-card p-4 sm:p-5" aria-labelledby="food-order-guide-title">
        <h2 id="food-order-guide-title" class="font-extrabold app-text">Cách xử lý tại quầy</h2>
        <ol class="mt-4 grid gap-3 md:grid-cols-3">
            @foreach([
                ['1', 'Tìm đúng đơn', 'Dùng mã đặt vé, mã phiếu, tên hoặc số điện thoại khách.'],
                ['2', 'Đối chiếu thông tin', 'Kiểm tra rạp nhận, danh sách món và bằng chứng thanh toán.'],
                ['3', 'Mở quầy in', 'In phiếu nhận đồ trong “Tra cứu & in đơn”; lý do chỉ bắt buộc khi in lại.'],
            ] as [$step, $title, $description])
                <li class="flex gap-3 rounded-2xl border app-border p-4">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-start font-black text-white">{{ $step }}</span>
                    <div><p class="font-bold app-text">{{ $title }}</p><p class="mt-1 text-sm app-muted">{{ $description }}</p></div>
                </li>
            @endforeach
        </ol>
    </section>

    <form method="GET" action="{{ route('admin.food-orders.index') }}" class="cinema-card p-4 sm:p-5" aria-label="Bộ lọc đơn đồ ăn thành công">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <label class="cinema-label xl:col-span-2">Tìm đơn
                <input class="cinema-input mt-1" name="search" maxlength="120" value="{{ $filters['search'] ?? '' }}" placeholder="Mã đặt vé, mã phiếu, tên hoặc số điện thoại">
            </label>
            <label class="cinema-label">Tình trạng phiếu nhận đồ
                <select class="cinema-input mt-1" name="voucher_status">
                    <option value="">Tất cả phiếu</option>
                    <option value="unprinted" @selected(($filters['voucher_status'] ?? '') === 'unprinted')>Chưa in</option>
                    <option value="printed" @selected(($filters['voucher_status'] ?? '') === 'printed')>Đã in</option>
                    <option value="missing" @selected(($filters['voucher_status'] ?? '') === 'missing')>Thiếu phiếu · cần kiểm tra</option>
                </select>
            </label>
            <div class="flex flex-wrap items-end gap-2">
                <button class="btn-primary" type="submit"><i class="ph ph-magnifying-glass" aria-hidden="true"></i>Tìm đơn</button>
                <a class="btn-secondary" href="{{ route('admin.food-orders.index') }}">Đặt lại</a>
            </div>
        </div>
        <details class="mt-3 rounded-xl border app-border px-4 py-3" @if(isset($filters['date_from']) || isset($filters['date_to']) || isset($filters['sort']) || isset($filters['direction']) || isset($filters['per_page'])) open @endif>
            <summary class="cursor-pointer text-sm font-bold app-text">Ngày thanh toán và cách sắp xếp</summary>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <label class="cinema-label">Từ ngày<input class="cinema-input mt-1" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
                <label class="cinema-label">Đến ngày<input class="cinema-input mt-1" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
                <label class="cinema-label">Sắp xếp<select class="cinema-input mt-1" name="sort"><option value="paid_at" @selected(($filters['sort'] ?? 'paid_at') === 'paid_at')>Thời gian thanh toán</option><option value="total_amount" @selected(($filters['sort'] ?? '') === 'total_amount')>Tiền đồ ăn</option><option value="booking_code" @selected(($filters['sort'] ?? '') === 'booking_code')>Mã đặt vé</option></select></label>
                <label class="cinema-label">Thứ tự<select class="cinema-input mt-1" name="direction"><option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>Mới / cao trước</option><option value="asc" @selected(($filters['direction'] ?? '') === 'asc')>Cũ / thấp trước</option></select></label>
                <label class="cinema-label">Số dòng<select class="cinema-input mt-1" name="per_page">@foreach([15, 25, 50] as $size)<option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 25) === $size)>{{ $size }}</option>@endforeach</select></label>
            </div>
        </details>
        @error('date_to')<p class="mt-3 text-sm font-semibold text-danger">{{ $message }}</p>@enderror
    </form>

    <section class="cinema-card overflow-hidden" aria-labelledby="successful-food-orders-title">
        <div class="border-b app-border px-5 py-4">
            <h2 id="successful-food-orders-title" class="font-extrabold app-text">Danh sách cần phục vụ</h2>
            <p class="mt-1 text-sm app-muted">Mỗi dòng tương ứng một đơn đặt vé đã thanh toán và một điểm nhận đồ.</p>
        </div>
        <div class="space-y-3 p-4 md:hidden" aria-label="Danh sách đơn đồ ăn trên điện thoại">
            @forelse($orders as $order)
                @php
                    $voucher = $order->booking?->foodPickupVoucher;
                    $quantity = $order->items->sum(fn ($item) => (int) $item->quantity);
                    $names = $order->items->map(fn ($item) => $item->snapshot_name ?: $item->food?->name)->filter();
                    $customerName = $order->booking?->user?->name ?: $order->customer_name ?: $order->booking?->customer_name ?: 'Khách đặt vé';
                @endphp
                <article class="rounded-2xl border app-border p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a class="break-all font-extrabold text-brand-start" href="{{ route('admin.food-orders.show', $order) }}">{{ $order->booking?->booking_code ?? '#'.$order->id }}</a>
                            <p class="mt-1 text-xs app-muted">Đơn đồ ăn #{{ $order->id }} · {{ $order->successful_paid_at ? \Carbon\Carbon::parse($order->successful_paid_at)->format('d/m/Y H:i') : '—' }}</p>
                        </div>
                        @if(!$voucher)
                            <span class="status-badge shrink-0 bg-danger/10 text-danger">Thiếu phiếu</span>
                        @elseif($voucher->print_count === 0)
                            <span class="status-badge shrink-0 bg-warning/10 text-warning">Chưa in</span>
                        @else
                            <span class="status-badge shrink-0 bg-success/10 text-success">Đã in</span>
                        @endif
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <div><dt class="app-muted">Khách hàng</dt><dd class="mt-1 font-bold app-text">{{ $customerName }}</dd></div>
                        <div><dt class="app-muted">Rạp nhận</dt><dd class="mt-1 font-bold app-text">{{ $order->pickupCinema?->name ?? 'Không xác định' }}</dd></div>
                        <div><dt class="app-muted">Món đã mua</dt><dd class="mt-1 font-bold app-text">{{ $quantity }} phần</dd><dd class="mt-1 line-clamp-2 text-xs app-muted">{{ $names->join(', ') }}</dd></div>
                        <div><dt class="app-muted">Tiền đồ ăn</dt><dd class="mt-1 font-extrabold app-text">{{ number_format((int) $order->total_amount, 0, ',', '.') }} VNĐ</dd><dd class="mt-1 text-xs text-success">Đã thanh toán</dd></div>
                    </dl>

                    @if($voucher)
                        <p class="mt-3 break-all rounded-xl app-secondary px-3 py-2 text-xs app-muted">Mã phiếu: <strong class="app-text">{{ $voucher->voucher_code }}</strong></p>
                    @endif
                    <a class="btn-secondary mt-4 w-full justify-center" href="{{ route('admin.food-orders.show', $order) }}">Mở đơn và kiểm tra</a>
                </article>
            @empty
                <div class="py-8 text-center"><i class="ph ph-receipt text-3xl app-muted" aria-hidden="true"></i><p class="mt-3 font-bold app-text">Không có đơn thành công phù hợp</p><p class="mt-1 text-sm app-muted">Hãy đổi từ khóa hoặc khoảng ngày. Đơn hủy không được hiển thị tại trang này.</p></div>
            @endforelse
        </div>

        <div class="hidden overflow-x-auto md:block">
            <table class="admin-table min-w-[72rem]">
                <thead><tr><th>Mã đơn</th><th>Khách hàng</th><th>Rạp nhận</th><th>Món đã mua</th><th class="text-right">Tiền đồ ăn</th><th>Phiếu nhận đồ</th><th class="text-right">Thao tác</th></tr></thead>
                <tbody>
                    @forelse($orders as $order)
                        @php
                            $voucher = $order->booking?->foodPickupVoucher;
                            $quantity = $order->items->sum(fn ($item) => (int) $item->quantity);
                            $names = $order->items->map(fn ($item) => $item->snapshot_name ?: $item->food?->name)->filter();
                            $customerName = $order->booking?->user?->name ?: $order->customer_name ?: $order->booking?->customer_name ?: 'Khách đặt vé';
                            $contact = $order->customer_phone ?: $order->booking?->customer_phone;
                        @endphp
                        <tr>
                            <td><a class="font-extrabold text-brand-start" href="{{ route('admin.food-orders.show', $order) }}">{{ $order->booking?->booking_code ?? '#'.$order->id }}</a><span class="mt-1 block text-xs app-muted">Đơn đồ ăn #{{ $order->id }} · {{ $order->successful_paid_at ? \Carbon\Carbon::parse($order->successful_paid_at)->format('d/m/Y H:i') : '—' }}</span></td>
                            <td><span class="font-bold app-text">{{ $customerName }}</span><span class="mt-1 block text-xs app-muted">{{ \App\Support\PrivacyMask::phone($contact) }}</span></td>
                            <td><span class="font-bold app-text">{{ $order->pickupCinema?->name ?? 'Không xác định' }}</span><span class="mt-1 block text-xs app-muted">{{ $order->pickupCinema?->code ?? '—' }}</span></td>
                            <td class="max-w-72">
                                <span class="font-bold app-text">{{ $quantity }} phần</span>
                                <span class="mt-1 block truncate text-xs app-muted">
                                    {{ $names->take(2)->join(', ') }}
                                    @if($names->count() > 2)
                                        +{{ $names->count() - 2 }} món khác
                                    @endif
                                </span>
                            </td>
                            <td class="text-right whitespace-nowrap"><span class="font-extrabold app-text">{{ number_format((int) $order->total_amount, 0, ',', '.') }} VNĐ</span><span class="mt-1 block text-xs text-success">Đã thanh toán</span></td>
                            <td>
                                @if(!$voucher)
                                    <span class="status-badge bg-danger/10 text-danger">Thiếu phiếu · cần kiểm tra</span>
                                @elseif($voucher->print_count === 0)
                                    <span class="status-badge bg-warning/10 text-warning">Chưa in</span><span class="mt-1 block text-xs app-muted">{{ $voucher->voucher_code }}</span>
                                @else
                                    <span class="status-badge bg-success/10 text-success">Đã in {{ $voucher->print_count }} lần</span><span class="mt-1 block text-xs app-muted">Lần cuối {{ $voucher->last_printed_at?->format('d/m/Y H:i') ?? '—' }}</span>
                                @endif
                            </td>
                            <td class="text-right"><a class="btn-secondary !px-3 !py-2 text-xs" href="{{ route('admin.food-orders.show', $order) }}">Mở đơn</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-12 text-center"><i class="ph ph-receipt text-3xl app-muted" aria-hidden="true"></i><p class="mt-3 font-bold app-text">Không có đơn thành công phù hợp</p><p class="mt-1 text-sm app-muted">Hãy đổi từ khóa hoặc khoảng ngày. Đơn hủy không được hiển thị tại trang này.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())<div class="border-t app-border px-5 py-4">{{ $orders->links() }}</div>@endif
    </section>
</div>
@endsection
