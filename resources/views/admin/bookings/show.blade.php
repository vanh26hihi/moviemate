@extends('layouts.admin')

@section('title', 'Đơn '.$booking->booking_code.' - MovieMate')
@section('page-title', 'Chi tiết đơn đặt vé')

@section('content')
@php
    $showtimeStartsAt = $booking->showtime?->show_date && $booking->showtime?->show_time
        ? \Carbon\Carbon::parse($booking->showtime->show_date->format('Y-m-d').' '.$booking->showtime->show_time)
        : null;
    $showtimeEndsAt = $showtimeStartsAt && $booking->showtime?->movie?->duration
        ? $showtimeStartsAt->copy()->addMinutes((int) $booking->showtime->movie->duration)
        : null;
    $hasReconcilablePayment = $payments->contains(fn ($payment) => in_array($payment->status, \App\Models\Payment::RECONCILABLE_STATUSES, true));
@endphp
<div class="space-y-6">
    <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <a href="{{ route('admin.bookings.index') }}" class="mb-3 inline-flex items-center gap-2 text-sm font-bold text-brand-start"><i class="ph ph-arrow-left" aria-hidden="true"></i>Về danh sách đơn</a>
            <h1 class="text-3xl font-extrabold app-heading">{{ $booking->booking_code }}</h1>
            <p class="mt-2 app-muted">Thông tin vận hành lấy trực tiếp từ booking, giao dịch, vé điện tử và dữ liệu suất chiếu.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('tickets.print')
                @if($ticketEligible)<a href="{{ route('staff.tickets.operations', $booking) }}" class="btn-secondary"><i class="ph ph-printer" aria-hidden="true"></i>Vận hành in vé</a>@endif
            @endcan
            @can('payments.reconcile')
                @if($hasReconcilablePayment)
                    <form method="POST" action="{{ route('admin.bookings.payment-query', $booking) }}" onsubmit="return confirm('Truy vấn trạng thái mới nhất trực tiếp từ nhà cung cấp?');">@csrf
                        <button class="btn-secondary" type="submit"><i class="ph ph-arrows-clockwise" aria-hidden="true"></i>Truy vấn thanh toán</button>
                    </form>
                @endif
            @endcan
            @can('bookings.operate')
                @if($cancellable)
                    <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}" onsubmit="return confirm('Hủy đơn chưa thanh toán này và giải phóng ghế?');">@csrf
                        <button class="btn-secondary text-error" type="submit"><i class="ph ph-x-circle" aria-hidden="true"></i>Hủy đơn an toàn</button>
                    </form>
                @endif
            @endcan
        </div>
    </header>

    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5" aria-label="Tóm tắt đơn">
        <div class="cinema-card p-5"><p class="text-sm app-muted">Trạng thái đặt vé</p><p class="mt-2 font-extrabold app-text">{{ \App\Support\StatusLabel::for('booking_admin', $booking->booking_status) }}</p></div>
        <div class="cinema-card p-5"><p class="text-sm app-muted">Trạng thái thanh toán</p><p class="mt-2 font-extrabold app-text">{{ $booking->payment_status_label }}</p></div>
        <div class="cinema-card p-5"><p class="text-sm app-muted">Trạng thái in vé</p><p class="mt-2 font-extrabold app-text">{{ $printState?->status_label ?? 'Chưa có dữ liệu in' }}</p></div>
        <div class="cinema-card p-5"><p class="text-sm app-muted">Trạng thái soát vé</p><p class="mt-2 font-extrabold app-text">{{ $acceptedCheckin ? 'Đã soát vé' : 'Chưa soát vé' }}</p></div>
        <div class="cinema-card p-5"><p class="text-sm app-muted">Tạo lúc</p><p class="mt-2 font-extrabold app-text">{{ $booking->created_at?->format('d/m/Y H:i:s') ?? '—' }}</p></div>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="cinema-card p-6">
            <h2 class="text-xl font-extrabold app-heading">Thông tin bán vé</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-sm app-muted">Kênh bán</dt><dd class="font-bold app-text">{{ $booking->sales_channel === 'counter' ? 'Tại quầy' : 'Online' }}</dd></div>
                <div><dt class="text-sm app-muted">Chi nhánh</dt><dd class="font-bold app-text">{{ $booking->showtime?->cinema?->name ?? '—' }}</dd></div>
                <div><dt class="text-sm app-muted">Người tạo đơn</dt><dd class="font-bold app-text">{{ $booking->createdByStaff?->name ?? '—' }}</dd></div>
                <div><dt class="text-sm app-muted">Thời gian tạo</dt><dd class="font-bold app-text">{{ $booking->created_at?->format('d/m/Y H:i:s') ?? '—' }}</dd></div>
                <div><dt class="text-sm app-muted">Phương thức thanh toán</dt><dd class="font-bold app-text">{{ $authoritativePayment ? \App\Support\PaymentPresentation::providerLabel($authoritativePayment->provider) : '—' }}</dd></div>
                <div><dt class="text-sm app-muted">Người thu tiền</dt><dd class="font-bold app-text">{{ $authoritativePayment?->settledBy?->name ?? '—' }}</dd></div>
                <div><dt class="text-sm app-muted">Thời gian thu tiền</dt><dd class="font-bold app-text">{{ $authoritativePayment?->settled_at?->format('d/m/Y H:i:s') ?? '—' }}</dd></div>
                <div><dt class="text-sm app-muted">Người in vé</dt><dd class="font-bold app-text">{{ $printState?->printedBy?->name ?? '—' }}</dd></div>
                <div><dt class="text-sm app-muted">Thời gian in</dt><dd class="font-bold app-text">{{ $printState?->printed_at?->format('d/m/Y H:i:s') ?? '—' }}</dd></div>
                <div><dt class="text-sm app-muted">Người soát vé</dt><dd class="font-bold app-text">{{ $acceptedCheckin?->actor?->name ?? '—' }}</dd></div>
                <div><dt class="text-sm app-muted">Thời gian soát</dt><dd class="font-bold app-text">{{ $acceptedCheckin?->scanned_at?->format('d/m/Y H:i:s') ?? '—' }}</dd></div>
            </dl>
        </section>

        <section class="cinema-card p-6">
            <h2 class="text-xl font-extrabold app-heading">In vé cứng</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-sm app-muted">Trạng thái</dt><dd class="font-bold app-text">{{ $printState?->status_label ?? 'Chưa có dữ liệu in' }}</dd></div>
                <div><dt class="text-sm app-muted">Số lần in</dt><dd class="font-bold app-text">{{ $printState?->attempts_count ?? 0 }}</dd></div>
                <div><dt class="text-sm app-muted">Số lần in lại</dt><dd class="font-bold app-text">{{ max(0, ($printState?->attempts_count ?? 0) - 1) }}</dd></div>
                <div><dt class="text-sm app-muted">Người in gần nhất</dt><dd class="font-bold app-text">{{ $latestPrintEvent?->actor?->name ?? '—' }}</dd></div>
                <div><dt class="text-sm app-muted">Thời gian in gần nhất</dt><dd class="font-bold app-text">{{ $latestPrintEvent?->created_at?->format('d/m/Y H:i:s') ?? '—' }}</dd></div>
                <div><dt class="text-sm app-muted">Lý do in lại gần nhất</dt><dd class="font-bold app-text">{{ $latestReprintEvent ? (\App\Services\Tickets\TicketPrintService::REPRINT_REASONS[$latestReprintEvent->failure_code] ?? 'Lý do đã được ghi nhận') : '—' }}@if($latestReprintEvent?->safe_note)<span class="mt-1 block max-w-md break-words text-xs app-muted">{{ $latestReprintEvent->safe_note }}</span>@endif</dd></div>
                <div><dt class="text-sm app-muted">Lỗi gần nhất</dt><dd class="font-bold app-text">{{ $printState?->last_failure_code ? (\App\Services\Tickets\TicketPrintService::FAILURE_REASONS[$printState->last_failure_code] ?? 'Lỗi in') : '—' }}</dd></div>
            </dl>
            @can('ticket_prints.view')
                @if($printEvents->isNotEmpty())
                    <div class="mt-5 overflow-x-auto"><table class="admin-table"><thead><tr><th>Thời gian</th><th>Nhân viên</th><th>Hành động</th><th>Lần in</th><th>Lý do</th><th>Kết quả</th></tr></thead><tbody>
                        @foreach($printEvents as $event)<tr><td>{{ $event->created_at?->format('d/m/Y H:i:s') }}</td><td>{{ $event->actor?->name ?? 'Hệ thống' }}</td><td>{{ match($event->event_type) { 'print_started' => $event->attempt_number === 1 ? 'In lần đầu' : 'Bắt đầu in lại', 'reprint_requested' => 'Yêu cầu in lại', 'print_succeeded' => 'Xác nhận kết quả', 'print_failed' => 'Xác nhận kết quả', 'retry_authorized' => 'Dữ liệu duyệt cũ', 'stale_print_released' => 'Lần in hết hạn', default => 'Sự kiện in' } }}</td><td>#{{ $event->attempt_number }}</td><td>{{ $event->event_type === 'reprint_requested' || ($event->event_type === 'print_started' && $event->attempt_number > 1) ? (\App\Services\Tickets\TicketPrintService::REPRINT_REASONS[$event->failure_code] ?? '—') : ($event->event_type === 'print_failed' ? (\App\Services\Tickets\TicketPrintService::FAILURE_REASONS[$event->failure_code] ?? '—') : '—') }}@if($event->safe_note)<span class="mt-1 block max-w-md break-words text-xs app-muted">{{ $event->safe_note }}</span>@endif</td><td>{{ match($event->event_type) { 'print_succeeded' => 'Thành công', 'print_failed' => 'Lỗi', 'reprint_requested' => 'Đã ghi nhận lý do', 'print_started' => 'Đang xử lý', 'stale_print_released' => 'Hết hạn', 'retry_authorized' => 'Lịch sử', default => 'Đã ghi nhận' } }}</td></tr>@endforeach
                    </tbody></table></div>
                @endif
            @endcan
        </section>

        <section class="cinema-card p-6">
            <h2 class="text-xl font-extrabold app-heading">Mốc thời gian</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-sm app-muted">Hết hạn thanh toán</dt><dd class="font-bold app-text">{{ $booking->expires_at?->format('d/m/Y H:i:s') ?? 'Không áp dụng' }}</dd></div>
                <div><dt class="text-sm app-muted">Thanh toán xác minh</dt><dd class="font-bold app-text">{{ $booking->paid_at?->format('d/m/Y H:i:s') ?? 'Chưa thanh toán' }}</dd></div>
                <div><dt class="text-sm app-muted">Soát vé</dt><dd class="font-bold app-text">{{ $booking->used_at?->format('d/m/Y H:i:s') ?? 'Chưa soát vé' }}</dd></div>
                <div><dt class="text-sm app-muted">Cập nhật cuối</dt><dd class="font-bold app-text">{{ $booking->updated_at?->format('d/m/Y H:i:s') ?? '—' }}</dd></div>
            </dl>
        </section>

        <section class="cinema-card p-6">
            <h2 class="text-xl font-extrabold app-heading">Khách hàng</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-sm app-muted">Tên hiển thị</dt><dd class="font-bold app-text">{{ $customer['name'] }}</dd></div>
                <div><dt class="text-sm app-muted">Loại khách</dt><dd class="font-bold app-text">{{ $customer['kind'] }}</dd></div>
                <div><dt class="text-sm app-muted">Email nhận vé</dt><dd class="font-bold app-text">{{ $customer['email'] }}</dd></div>
                <div><dt class="text-sm app-muted">Điện thoại</dt><dd class="font-bold app-text">{{ $customer['phone'] }}</dd></div>
                @if($booking->user_id)<div><dt class="text-sm app-muted">Mã tài khoản nội bộ</dt><dd class="font-bold app-text">#{{ $booking->user_id }}</dd></div>@endif
            </dl>
        </section>
    </div>

    <section class="cinema-card p-6">
        <h2 class="text-xl font-extrabold app-heading">Phim và suất chiếu</h2>
        <dl class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div><dt class="text-sm app-muted">Phim</dt><dd class="font-bold app-text">{{ $booking->showtime?->movie?->title ?? 'Thông tin không còn khả dụng' }}</dd></div>
            <div><dt class="text-sm app-muted">Bắt đầu</dt><dd class="font-bold app-text">{{ $showtimeStartsAt?->format('d/m/Y H:i') ?? '—' }}</dd></div>
            <div><dt class="text-sm app-muted">Kết thúc dự kiến</dt><dd class="font-bold app-text">{{ $showtimeEndsAt?->format('d/m/Y H:i') ?? 'Chưa đủ dữ liệu thời lượng' }}</dd></div>
            <div><dt class="text-sm app-muted">Phòng</dt><dd class="font-bold app-text">{{ $booking->showtime?->room?->code ?? '—' }} · {{ $booking->showtime?->room?->name ?? '—' }}</dd></div>
            <div><dt class="text-sm app-muted">Rạp</dt><dd class="font-bold app-text">{{ $booking->showtime?->cinema?->name ?? $booking->showtime?->room?->cinema?->name ?? '—' }}</dd></div>
            <div><dt class="text-sm app-muted">Phiên bản sơ đồ</dt><dd class="font-bold app-text">{{ $booking->showtime?->roomLayout?->version ? 'Phiên bản '.$booking->showtime->roomLayout->version : 'Không có snapshot' }}</dd></div>
        </dl>
    </section>

    <section class="cinema-card overflow-hidden">
        <div class="border-b app-border p-6"><h2 class="text-xl font-extrabold app-heading">Ghế</h2><p class="mt-1 app-muted">Giá là snapshot trên từng ghế tại thời điểm đặt vé.</p></div>
        <div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Ghế</th><th>Loại</th><th class="text-right">Giá ghi nhận</th></tr></thead><tbody>
            @forelse($seatGroups as $group)<tr><td class="font-extrabold text-brand-start">{{ $group['label'] }}</td><td>{{ \App\Support\StatusLabel::for('seat_type', $group['type']) }}</td><td class="text-right">{{ number_format($group['price'], 0, ',', '.') }} VNĐ</td></tr>
            @empty<tr><td colspan="3" class="py-8 text-center app-muted">Không còn dữ liệu ghế.</td></tr>@endforelse
        </tbody></table></div>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="cinema-card overflow-hidden">
            <div class="border-b app-border p-6"><h2 class="text-xl font-extrabold app-heading">Đồ ăn</h2></div>
            @if($booking->foodOrder)
                <div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Sản phẩm</th><th class="text-right">SL</th><th class="text-right">Đơn giá</th><th class="text-right">Thành tiền</th></tr></thead><tbody>
                    @foreach($booking->foodOrder->items as $item)<tr><td>{{ $item->snapshot_name ?: $item->food?->name ?: 'Sản phẩm không còn khả dụng' }}</td><td class="text-right">{{ $item->quantity }}</td><td class="text-right">{{ number_format((int) $item->unit_price, 0, ',', '.') }} VNĐ</td><td class="text-right">{{ number_format((int) $item->line_total, 0, ',', '.') }} VNĐ</td></tr>@endforeach
                </tbody></table></div>
                <div class="border-t app-border p-5 text-sm"><span class="app-muted">Trạng thái:</span> <strong>{{ $booking->foodOrder->status_label }}</strong><span class="float-right font-extrabold">{{ number_format((int) $booking->food_subtotal, 0, ',', '.') }} VNĐ</span></div>
            @else<p class="p-6 app-muted">Đơn không kèm đồ ăn.</p>@endif
        </section>

        <section class="cinema-card p-6">
            <h2 class="text-xl font-extrabold app-heading">Chi phí xác thực</h2>
            <dl class="mt-4 space-y-3">
                <div class="flex justify-between gap-4"><dt class="app-muted">Tiền ghế</dt><dd class="font-bold app-text">{{ number_format((int) $booking->seat_subtotal, 0, ',', '.') }} VNĐ</dd></div>
                @foreach($booking->bookingSeats->filter(fn($item) => $item->pricing_unit_key)->unique('pricing_unit_key') as $pricedUnit)
                    <div class="rounded-xl border app-border p-3 text-sm"><div class="flex justify-between gap-3"><dt class="font-semibold app-text">{{ $pricedUnit->pricing_unit_label }}</dt><dd class="font-bold app-text">{{ number_format((int)$pricedUnit->final_unit_amount,0,',','.') }} VNĐ</dd></div><div class="mt-1 app-muted">Giá cơ bản {{ number_format((int)$pricedUnit->base_amount,0,',','.') }} VNĐ · Phụ thu {{ number_format((int)$pricedUnit->surcharge_total,0,',','.') }} VNĐ</div></div>
                @endforeach
                <div class="flex justify-between gap-4"><dt class="app-muted">Tiền đồ ăn</dt><dd class="font-bold app-text">{{ number_format((int) $booking->food_subtotal, 0, ',', '.') }} VNĐ</dd></div>
                <div class="flex justify-between gap-4 border-t app-border pt-3"><dt class="font-extrabold app-text">Tổng cuối cùng</dt><dd class="text-xl font-extrabold text-brand-start">{{ number_format((int) $booking->total_amount, 0, ',', '.') }} VNĐ</dd></div>
                <div class="flex justify-between gap-4"><dt class="app-muted">Khoản thanh toán đã xác minh</dt><dd class="font-bold app-text">{{ $authoritativePayment ? number_format((int) $authoritativePayment->amount, 0, ',', '.').' VNĐ' : 'Chưa có' }}</dd></div>
            </dl>
        </section>
    </div>

    <section class="cinema-card overflow-hidden">
        <div class="border-b app-border p-6"><h2 class="text-xl font-extrabold app-heading">Lịch sử giao dịch</h2><p class="mt-1 app-muted">Không hiển thị chữ ký, URL thanh toán hoặc payload nhà cung cấp.</p></div>
        <div class="overflow-x-auto"><table class="admin-table min-w-[78rem]"><thead><tr><th>Nhà cung cấp</th><th>Mã tham chiếu</th><th class="text-right">Số tiền</th><th>Trạng thái</th><th>Phân loại an toàn</th><th>Tạo lúc</th><th>Xác minh lúc</th><th>Nhà cung cấp ghi nhận</th></tr></thead><tbody>
            @forelse($payments as $payment)
                <tr class="{{ $authoritativePayment?->id === $payment->id ? 'bg-success/5' : '' }}">
                    <td class="font-bold app-text">{{ \App\Support\PaymentPresentation::providerLabel($payment->provider) }}@if($authoritativePayment?->id === $payment->id)<span class="ml-2 status-badge bg-success/10 text-success">Giao dịch xác thực</span>@endif</td>
                    <td class="font-mono text-xs">{{ $payment->provider === 'counter_cash' ? ($payment->transaction_code ?? '—') : ($payment->provider === 'zalopay' ? ($payment->app_trans_id ?? '—') : ($payment->order_code ?? '—')) }}</td>
                    <td class="text-right">{{ number_format((int) $payment->amount, 0, ',', '.') }} VNĐ</td>
                    <td>{{ $payment->status_label }}</td><td>{{ $paymentCategories[$payment->id] }}</td>
                    <td>{{ $payment->created_at?->format('d/m/Y H:i:s') ?? '—' }}</td><td>{{ $payment->verified_at?->format('d/m/Y H:i:s') ?? '—' }}</td><td>{{ $payment->provider_paid_at?->format('d/m/Y H:i:s') ?? $payment->paid_at?->format('d/m/Y H:i:s') ?? '—' }}</td>
                </tr>
            @empty<tr><td colspan="8" class="py-8 text-center app-muted">Chưa có lần thanh toán.</td></tr>@endforelse
        </tbody></table></div>
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="cinema-card p-6">
            <h2 class="text-xl font-extrabold app-heading">Vé điện tử</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-sm app-muted">Người nhận</dt><dd class="font-bold app-text">{{ $customer['email'] }}</dd></div>
                <div><dt class="text-sm app-muted">Trạng thái gửi email</dt><dd class="font-bold app-text">{{ match($booking->ticketDelivery?->status) { 'sent' => 'Đã gửi', 'pending' => 'Đang chờ gửi', 'processing' => 'Đang gửi', 'failed' => 'Gửi lỗi', default => $booking->recipient_email ? 'Chưa có yêu cầu gửi' : 'Không có email' } }}</dd></div>
                <div><dt class="text-sm app-muted">Số lần thử</dt><dd class="font-bold app-text">{{ $booking->ticketDelivery?->attempts ?? 0 }}</dd></div>
                <div><dt class="text-sm app-muted">Lần gửi gần nhất</dt><dd class="font-bold app-text">{{ $booking->ticketDelivery?->updated_at?->format('d/m/Y H:i:s') ?? '—' }}</dd></div>
                <div><dt class="text-sm app-muted">Gửi thành công lúc</dt><dd class="font-bold app-text">{{ $booking->ticketDelivery?->sent_at?->format('d/m/Y H:i:s') ?? '—' }}</dd></div>
                @if($booking->ticketDelivery?->last_error_code)<div class="sm:col-span-2"><dt class="text-sm app-muted">Lỗi gần nhất</dt><dd class="font-bold text-error">{{ \App\Support\TicketDeliveryPresentation::error($booking->ticketDelivery->last_error_code) }}</dd></div>@endif
            </dl>
            @can('ticket_deliveries.retry')
                @if($deliveryRetryAllowed)
                    <form method="POST" action="{{ route('admin.bookings.ticket-email.resend', $booking) }}" class="mt-5" onsubmit="return confirm('Gửi lại vé tới email đã lưu của đơn này?');">@csrf
                        <button class="btn-primary" type="submit"><i class="ph ph-envelope-simple" aria-hidden="true"></i>Gửi lại vé</button>
                    </form>
                @endif
            @endcan
        </section>

        <section class="cinema-card p-6">
            <h2 class="text-xl font-extrabold app-heading">Soát vé</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-sm app-muted">Kết quả</dt><dd class="font-bold app-text">{{ $booking->booking_status === 'used' ? 'Đã soát vé' : 'Chưa soát vé' }}</dd></div>
                <div><dt class="text-sm app-muted">Thời gian chấp nhận</dt><dd class="font-bold app-text">{{ $acceptedCheckin?->scanned_at?->format('d/m/Y H:i:s') ?? $booking->used_at?->format('d/m/Y H:i:s') ?? '—' }}</dd></div>
                <div><dt class="text-sm app-muted">Nhân viên chấp nhận</dt><dd class="font-bold app-text">{{ $acceptedCheckin?->actor?->name ?? ($booking->booking_status === 'used' ? 'Không có dữ liệu lịch sử' : '—') }}</dd></div>
                <div><dt class="text-sm app-muted">Lần quét trùng</dt><dd class="font-bold app-text">{{ $duplicateCheckinCount }}</dd></div>
                <div><dt class="text-sm app-muted">Lần bị từ chối</dt><dd class="font-bold app-text">{{ $rejectedCheckinCount }}</dd></div>
            </dl>
            @can('ticket_checkins.view')<a class="mt-4 inline-flex font-bold text-brand-start" href="{{ route('admin.ticket-checkins.index', ['booking_code' => $booking->booking_code]) }}">Xem toàn bộ lịch sử soát vé</a>@endcan
            @if($checkins->isNotEmpty())<div class="mt-5 overflow-x-auto"><table class="admin-table"><thead><tr><th>Thời gian</th><th>Kết quả</th><th>Nhân viên</th></tr></thead><tbody>@foreach($checkins as $checkin)<tr><td>{{ $checkin->scanned_at?->format('d/m/Y H:i:s') }}</td><td>{{ \App\Support\StatusLabel::for('ticket_checkin', $checkin->result) }}</td><td>{{ $checkin->actor?->name ?? 'Tài khoản không còn khả dụng' }}</td></tr>@endforeach</tbody></table></div>@endif
        </section>
    </div>

    @if($includeActivity)
        <section class="cinema-card overflow-hidden">
            <div class="border-b app-border p-6"><h2 class="text-xl font-extrabold app-heading">Lịch sử hoạt động</h2></div>
            <div class="overflow-x-auto"><table class="admin-table"><thead><tr><th>Thời gian</th><th>Người thực hiện</th><th>Hành động</th><th>Mã yêu cầu</th></tr></thead><tbody>
                @forelse($activities as $activity)<tr><td>{{ $activity->created_at?->format('d/m/Y H:i:s') }}</td><td>{{ $activity->actor?->name ?? 'Hệ thống' }}</td><td>{{ match($activity->action) { 'booking.ticket_resend_requested' => 'Yêu cầu gửi lại vé', 'booking.payment_query_requested' => 'Truy vấn nhà cung cấp', 'booking.cancelled' => 'Hủy đơn an toàn', 'ticket.reprint_requested' => 'Ghi nhận lý do in lại', 'ticket.print_started' => 'Bắt đầu in vé cứng', 'ticket.print_succeeded' => 'Xác nhận in thành công', 'ticket.print_failed' => 'Ghi nhận in lỗi', 'ticket.print_retry_authorized' => 'Lịch sử cấp quyền in cũ', 'ticket.print_stale_released' => 'Giải phóng lần in hết hạn', default => 'Hoạt động quản trị' } }}</td><td class="font-mono text-xs">{{ $activity->request_id }}</td></tr>
                @empty<tr><td colspan="4" class="py-8 text-center app-muted">Chưa có hoạt động quản trị liên quan.</td></tr>@endforelse
            </tbody></table></div>
        </section>
    @endif
</div>
@endsection
