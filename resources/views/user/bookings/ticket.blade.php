@extends('layouts.user')

@section('title', 'Vé '.$booking->booking_code.' - MovieMate')
@section('body_class', 'ticket-document-page')

@php
    $printMode = $printMode ?? false;
    $isPrintable = $isPrintable ?? $isUsable;
    $foodItems = $booking->foodOrder?->items ?? collect();
    $currency = ($booking->currency ?: 'VND') === 'VND' ? 'VNĐ' : $booking->currency;
    $seatTypeLabels = ['normal' => 'Thường', 'vip' => 'VIP', 'couple' => 'Ghế đôi'];
    $paymentProvider = match ($verifiedPayment?->provider) {
        'vnpay' => 'VNPAY',
        'zalopay' => 'ZaloPay',
        default => 'Đã xác minh',
    };
    $issuedAt = $booking->paid_at ?? $verifiedPayment?->verified_at ?? $booking->created_at;
    $backUrl = $backUrl ?? (auth()->check()
        ? route('user.bookings.history')
        : route('user.bookings.success', $booking));
    $backLabel = $backLabel ?? 'Về vé của tôi';
    $ticketRecipient = $ticketRecipient ?? $booking->recipient_email;
@endphp

@section('content')
<div class="ticket-preview-shell" data-ticket-print-page="{{ $printMode ? 'true' : 'false' }}">
    <div class="ticket-toolbar print-hidden" aria-label="Thao tác vé">
        <a href="{{ $backUrl }}" class="ticket-toolbar-link">
            <i class="ph-bold ph-arrow-left" aria-hidden="true"></i>
            {{ $backLabel }}
        </a>

        @if($isPrintable)
            <div class="ticket-toolbar-actions">
                <button type="button" class="btn-secondary" data-print-ticket>
                    <i class="ph-bold ph-printer" aria-hidden="true"></i>
                    In vé
                </button>
                <button type="button" class="btn-primary" data-print-ticket>
                    <i class="ph-bold ph-file-pdf" aria-hidden="true"></i>
                    Lưu PDF / In vé
                </button>
            </div>
        @endif
    </div>

    <article class="cinema-ticket-document" data-ticket-document data-ticket-state="{{ $isUsable ? 'usable' : 'inactive' }}">
        <header class="cinema-ticket-header">
            <div>
                <p class="cinema-ticket-brand"><i class="ph-fill ph-film-strip" aria-hidden="true"></i> MovieMate Cinema</p>
                <h1>VÉ XEM PHIM</h1>
                <p class="cinema-ticket-subtitle">Vé điện tử chính thức · Xuất trình khi check-in</p>
            </div>
            <div class="cinema-ticket-status" aria-label="Trạng thái vé">
                @if($isUsable)
                    <i class="ph-bold ph-seal-check" aria-hidden="true"></i>
                    ĐÃ THANH TOÁN
                @else
                    <i class="ph-bold ph-prohibit" aria-hidden="true"></i>
                    KHÔNG KHẢ DỤNG
                @endif
            </div>
        </header>

        <div class="cinema-ticket-body">
            <section class="cinema-ticket-main" aria-labelledby="ticket-movie-title">
                <div class="cinema-ticket-code-block">
                    <p>Mã đặt vé / soát vé</p>
                    <strong>{{ $booking->booking_code }}</strong>
                </div>

                <h2 id="ticket-movie-title">{{ $booking->showtime?->movie?->title ?? 'Thông tin phim đang cập nhật' }}</h2>

                <dl class="cinema-ticket-highlights">
                    <div>
                        <dt>Ngày chiếu</dt>
                        <dd>{{ $booking->showtime?->show_date?->format('d/m/Y') ?? 'Đang cập nhật' }}</dd>
                    </div>
                    <div>
                        <dt>Giờ chiếu</dt>
                        <dd class="ticket-accent">{{ $booking->showtime?->show_time ? \Carbon\Carbon::parse($booking->showtime->show_time)->format('H:i') : '--:--' }}</dd>
                    </div>
                    <div>
                        <dt>Phòng</dt>
                        <dd>{{ $booking->showtime?->room?->name ?? 'Đang cập nhật' }}</dd>
                    </div>
                    <div>
                        <dt>Ghế</dt>
                        <dd class="ticket-accent">{{ $booking->seat_codes ?: 'Đang cập nhật' }}</dd>
                    </div>
                </dl>

                <dl class="cinema-ticket-details">
                    <div><dt>Rạp</dt><dd>{{ $booking->showtime?->cinema?->name ?? 'Đang cập nhật' }}</dd></div>
                    <div><dt>Địa chỉ</dt><dd>{{ $booking->showtime?->cinema?->address ?? 'Đang cập nhật' }}</dd></div>
                    <div><dt>Khách hàng</dt><dd>{{ $booking->user?->name ?? 'Khách MovieMate' }}</dd></div>
                    <div><dt>Email</dt><dd class="ticket-break">{{ $ticketRecipient }}</dd></div>
                    <div><dt>Thanh toán</dt><dd>{{ $paymentProvider }} · Đã xác minh</dd></div>
                    <div><dt>Xác nhận lúc</dt><dd>{{ ($booking->paid_at ?? $verifiedPayment?->paid_at)?->format('d/m/Y H:i') ?? 'Đang cập nhật' }}</dd></div>
                </dl>

                <section class="cinema-ticket-order" aria-labelledby="ticket-order-title">
                    <h3 id="ticket-order-title">Chi tiết đơn</h3>
                    <div class="cinema-ticket-seats">
                        @foreach($booking->seat_display_groups as $seatGroup)
                            <span>
                                {{ $seatGroup['label'] }}
                                @unless($seatGroup['is_couple'])
                                    · {{ $seatTypeLabels[$seatGroup['type']] ?? \App\Support\StatusLabel::for('seat_type', $seatGroup['type']) }}
                                @endunless
                            </span>
                        @endforeach
                    </div>

                    <div class="cinema-ticket-foods">
                        @forelse($foodItems as $item)
                            <p><span>{{ $item->snapshot_name }} × {{ $item->quantity }}</span><strong>{{ number_format((int) $item->line_total, 0, ',', '.') }} {{ $currency }}</strong></p>
                        @empty
                            <p><span>Đồ ăn</span><strong>Không có</strong></p>
                        @endforelse
                    </div>

                    <dl class="cinema-ticket-totals">
                        <div><dt>Tiền ghế</dt><dd>{{ number_format((int) $booking->seat_subtotal, 0, ',', '.') }} {{ $currency }}</dd></div>
                        <div><dt>Tiền đồ ăn</dt><dd>{{ number_format((int) $booking->food_subtotal, 0, ',', '.') }} {{ $currency }}</dd></div>
                        <div class="cinema-ticket-grand-total"><dt>Tổng cộng</dt><dd>{{ number_format((int) $booking->total_amount, 0, ',', '.') }} {{ $currency }}</dd></div>
                    </dl>
                </section>
            </section>

            <aside class="cinema-ticket-stub" aria-label="Cuống vé check-in">
                <p class="cinema-ticket-stub-label">SOÁT VÉ</p>
                @if($isUsable)
                    <div class="cinema-ticket-qr">
                        <canvas data-qr-value="{{ $checkinCapability }}" data-qr-size="240" width="240" height="240" aria-label="Mã QR soát vé MovieMate"></canvas>
                        <span data-qr-fallback class="hidden">QR chưa tải</span>
                    </div>
                    <p class="cinema-ticket-instruction">Đưa mã QR cho nhân viên soát vé. Vui lòng đến trước giờ chiếu 15 phút.</p>
                @else
                    <div class="cinema-ticket-inactive">
                        <i class="ph-bold ph-qr-code" aria-hidden="true"></i>
                        <p>Đơn đặt vé này không có mã QR sử dụng được.</p>
                    </div>
                @endif

                <dl>
                    <div><dt>PHÒNG</dt><dd>{{ $booking->showtime?->room?->name ?? '—' }}</dd></div>
                    <div><dt>GHẾ</dt><dd>{{ $booking->seat_codes ?: '—' }}</dd></div>
                    <div><dt>GIỜ</dt><dd>{{ $booking->showtime?->show_time ? \Carbon\Carbon::parse($booking->showtime->show_time)->format('H:i') : '—' }}</dd></div>
                </dl>
                <p class="cinema-ticket-stub-code">{{ $booking->booking_code }}</p>
            </aside>
        </div>

        <footer class="cinema-ticket-footer">
            <p>Phát hành lúc {{ $issuedAt?->format('d/m/Y H:i') ?? 'Đang cập nhật' }}</p>
            <p>MovieMate Cinema · Vé chỉ có hiệu lực cho suất chiếu ghi trên vé</p>
        </footer>
    </article>

    @if($isUsable)
        <section class="seat-admission-list" aria-label="Phiếu vào cửa theo ghế">
            @foreach($booking->seat_display_groups as $seatGroup)
                <article class="seat-admission-ticket">
                    <div>
                        <p class="seat-admission-brand">MOVIEMATE · PHIẾU VÀO CỬA</p>
                        <h2>{{ $booking->showtime?->movie?->title }}</h2>
                        <p>{{ $booking->showtime_label }} · {{ $booking->showtime?->room?->name }}</p>
                    </div>
                    <div class="seat-admission-seat">
                        <span>GHẾ</span>
                        <strong>{{ $seatGroup['seat_code'] }}</strong>
                        <small>{{ $seatGroup['is_couple'] ? 'Ghế đôi' : ($seatTypeLabels[$seatGroup['type']] ?? \App\Support\StatusLabel::for('seat_type', $seatGroup['type'])) }}</small>
                    </div>
                </article>
            @endforeach
        </section>
    @endif

    <p class="ticket-print-help print-hidden">
        Chọn “Lưu dưới dạng PDF” trong hộp thoại in của trình duyệt để lưu vé thành PDF.
    </p>
</div>
@endsection
