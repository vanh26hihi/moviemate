@extends('layouts.staff')

@section('title', 'Kết quả thanh toán tại quầy - MovieMate')
@section('page-title', 'Kết quả thanh toán tại quầy')

@section('content')
@php
    $provider = $authoritative?->provider ?? $payment?->provider;
    $providerLabel = $provider ? \App\Support\PaymentPresentation::providerLabel($provider) : 'nhà cung cấp';
    $presentation = match($state) {
        'paid' => ['title' => 'Thanh toán thành công', 'message' => 'Giao dịch đã có bằng chứng thanh toán hợp lệ.', 'class' => 'border-success/40 bg-success/10 text-success', 'icon' => 'ph-check-circle'],
        'pending' => ['title' => 'Đang chờ khách thanh toán', 'message' => 'Giữ trang này mở; trạng thái sẽ được kiểm tra lại tự động trong thời gian giới hạn.', 'class' => 'border-warning/40 bg-warning/10 text-warning', 'icon' => 'ph-clock'],
        'processing' => ['title' => 'Đang xác minh thanh toán '.$providerLabel, 'message' => 'Provider chưa trả kết quả cuối cùng. Không tạo thêm giao dịch.', 'class' => 'border-warning/40 bg-warning/10 text-warning', 'icon' => 'ph-spinner-gap'],
        'cancelled' => ['title' => 'Thanh toán đã được hủy', 'message' => 'Đơn đã được hủy theo kết quả có thẩm quyền và không thể in vé.', 'class' => 'border-error/40 bg-error/10 text-error', 'icon' => 'ph-x-circle'],
        'review' => ['title' => 'Giao dịch cần được hỗ trợ', 'message' => 'Giao dịch có bất thường thực tế và cần quy trình đối soát.', 'class' => 'border-error/40 bg-error/10 text-error', 'icon' => 'ph-warning'],
        default => ['title' => 'Thanh toán không thành công', 'message' => 'Provider chưa xác nhận thanh toán thành công. Không thể in vé.', 'class' => 'border-error/40 bg-error/10 text-error', 'icon' => 'ph-x-circle'],
    };
@endphp
<div class="mx-auto max-w-4xl space-y-6" data-counter-payment-result data-payment-state="{{ $state }}" @if(in_array($state, ['pending', 'processing'], true)) data-safe-refresh="true" @endif>
    <section class="rounded-2xl border p-6 {{ $presentation['class'] }}" role="status">
        <div class="flex items-start gap-3"><i class="ph {{ $presentation['icon'] }} text-3xl"></i><div><h1 class="text-2xl font-extrabold">{{ $presentation['title'] }}</h1><p class="mt-1">{{ $presentation['message'] }}</p></div></div>
    </section>

    <section class="cinema-card p-6">
        <h2 class="text-xl font-extrabold app-heading">Thông tin đơn đặt vé</h2>
        <dl class="mt-5 grid gap-4 sm:grid-cols-2">
            <div><dt class="text-sm app-muted">Mã đơn</dt><dd class="font-bold">{{ $booking->booking_code }}</dd></div>
            <div><dt class="text-sm app-muted">Phương thức</dt><dd class="font-bold">{{ $provider ? \App\Support\PaymentPresentation::providerLabel($provider) : 'Chưa chọn' }}</dd></div>
            <div><dt class="text-sm app-muted">Phim</dt><dd class="font-bold">{{ $booking->showtime->movie->title }}</dd></div>
            <div><dt class="text-sm app-muted">Suất chiếu</dt><dd class="font-bold">{{ $booking->showtime_label }}</dd></div>
            <div><dt class="text-sm app-muted">Phòng</dt><dd class="font-bold">{{ $booking->showtime->room->name }}</dd></div>
            <div><dt class="text-sm app-muted">Ghế</dt><dd class="font-bold">{{ $booking->seat_codes }}</dd></div>
            <div><dt class="text-sm app-muted">Tổng tiền</dt><dd class="font-bold text-brand-start">{{ number_format((int) $booking->total_amount, 0, ',', '.') }} VNĐ</dd></div>
            <div><dt class="text-sm app-muted">Chi nhánh</dt><dd class="font-bold">{{ $booking->showtime->cinema->name }}</dd></div>
        </dl>
    </section>

    <section class="cinema-card p-6">
        @if($state === 'paid')
            @if($canAutoPrint)
                <h2 class="text-xl font-extrabold app-heading">Đang chuyển sang in toàn bộ</h2>
                <p class="mt-2 app-muted">MovieMate sẽ in đủ một vé cho mỗi ghế và một phiếu nhận đồ ăn nếu đơn có đồ ăn. Mỗi tài liệu chỉ được ghi nhận một lần in đầu.</p>
                <form method="POST" action="{{ route('staff.tickets.print-all', $booking) }}" class="mt-5" data-auto-print-all data-auto-print-key="{{ $booking->id }}:{{ $authoritative?->id }}" data-submit-once>
                    @csrf
                    <button type="submit" class="btn-primary"><i class="ph ph-printer"></i>In toàn bộ ngay</button>
                </form>
            @elseif($printState?->status === \App\Models\BookingTicketPrint::STATUS_PRINTING)
                <h2 class="text-xl font-extrabold app-heading">Phiên in đang chờ xác nhận</h2>
                <p class="mt-2 app-muted">Mở phiên in hiện tại để in hoặc xác nhận kết quả thực tế.</p>
                <a class="btn-primary mt-5" href="{{ route('staff.tickets.print.show', $booking) }}"><i class="ph ph-printer"></i>Mở phiên in</a>
            @elseif($printState?->status === \App\Models\BookingTicketPrint::STATUS_PRINTED)
                <h2 class="text-xl font-extrabold text-success">Vé đã được ghi nhận in thành công</h2>
                <p class="mt-2 app-muted">Người in: {{ $printState->printedBy?->name ?? '—' }} · {{ $printState->printed_at?->format('d/m/Y H:i:s') }}</p>
            @else
                <h2 class="text-xl font-extrabold app-heading">Vé cần xử lý theo chính sách in lại</h2>
                <p class="mt-2 app-muted">Không tự động tạo lượt in mới khi lần trước đã lỗi; nhân viên phải ghi lý do trước khi in lại.</p>
                <a class="btn-primary mt-5" href="{{ route('staff.tickets.operations', $booking) }}">Mở vận hành vé</a>
            @endif
        @else
            <h2 class="text-xl font-extrabold app-heading">Xử lý đơn an toàn</h2>
            @if($canResume)
                <p class="mt-2 app-muted">Nút Quay lại của trình duyệt không có nghĩa là nhà cung cấp đã hủy. Hãy mở lại đúng giao dịch hiện tại hoặc kiểm tra trạng thái trước khi đổi phương thức.</p>
                <form method="POST" action="{{ route('staff.counter.payment.resume', $booking) }}" class="mt-5" data-submit-once>
                    @csrf
                    <button type="submit" class="btn-primary">Tiếp tục thanh toán {{ $payment?->provider === 'vnpay' ? 'VNPAY' : 'payOS' }}</button>
                </form>
            @endif

            @if($canReconcile)
                <form method="POST" action="{{ route('staff.counter.payment.reconcile', $booking) }}" class="mt-3" data-submit-once>
                    @csrf
                    <button type="submit" class="btn-secondary"><i class="ph ph-arrows-clockwise"></i>Kiểm tra trạng thái với nhà cung cấp</button>
                </form>
            @endif

            @if($canCancelPayOsAttempt)
                <form method="POST" action="{{ route('staff.counter.payment.cancel-payos-attempt', $booking) }}" class="mt-3" data-submit-once>
                    @csrf
                    <button type="submit" class="btn-secondary">Hủy giao dịch payOS và chọn phương thức khác</button>
                    <p class="mt-2 text-sm app-muted">Chỉ cho đổi phương thức sau khi payOS xác nhận liên kết thanh toán đã hủy.</p>
                </form>
            @endif

            @if($canChooseAnotherMethod)
                <a class="btn-primary mt-5" href="{{ route('staff.counter.review', $booking) }}">Chọn phương thức thanh toán khác</a>
            @endif

            @if($canCancelOrder)
                @can('counter_sales.cancel')
                    <form method="POST" action="{{ route('staff.counter.cancel', $booking) }}" class="mt-3" data-submit-once>
                        @csrf
                        <button type="submit" class="btn-secondary">Hủy đơn và giải phóng ghế</button>
                    </form>
                @endcan
            @endif

            @unless($canResume || $canReconcile || $canCancelPayOsAttempt || $canChooseAnotherMethod || $canCancelOrder)
                <a class="btn-secondary mt-5" href="{{ route('staff.counter.index') }}">Quay lại quầy bán vé</a>
            @endunless
        @endif
    </section>
</div>
<script>
    (() => {
        const form = document.querySelector('[data-auto-print-all]');
        if (!(form instanceof HTMLFormElement)) return;
        const key = `moviemate:counter-auto-print-all:${form.dataset.autoPrintKey}`;
        try {
            if (sessionStorage.getItem(key) === 'started') return;
            sessionStorage.setItem(key, 'started');
        } catch {
            return;
        }
        window.setTimeout(() => form.requestSubmit(), 250);
    })();
</script>
@endsection
