@extends('layouts.user')

@section('title', 'Lịch sử đặt vé - MovieMate')
@section('suppress-global-validation-summary', '1')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <aside class="lg:col-span-1">
            <div class="app-card border app-border rounded-3xl p-6 flex flex-col items-center text-center sticky top-24">
                <div class="w-20 h-20 rounded-full overflow-hidden mb-3 border-2 border-brand-start/30">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&background=FF3D57&color=fff&size=128" alt="Avatar" class="w-full h-full object-cover">
                </div>
                <h2 class="font-bold app-text mb-1">{{ Auth::user()->name }}</h2>
                <p class="text-xs text-ai-start font-bold mb-5">Hạng {{ Auth::user()->role?->display_name ?? 'Khách hàng' }}</p>

                <div class="w-full rounded-2xl border border-ai-start/30 bg-ai-start/10 px-4 py-3 mb-4 text-left">
                    <p class="text-xs app-muted">Thành viên {{ Auth::user()->membership_tier }}</p>
                    <p class="text-2xl font-extrabold text-ai-start">{{ number_format(Auth::user()->loyalty_points, 0, ',', '.') }}</p>
                    <p class="text-xs app-muted">điểm khả dụng</p>
                </div>

                <div class="w-full space-y-1 text-left">
                    <a href="{{ route('user.profile') }}" class="flex items-center gap-3 px-4 py-2.5 app-muted hover:app-text hover:bg-brand-start/5 rounded-xl font-medium transition-colors text-sm">
                        <i class="ph ph-user text-lg"></i> Thông tin cá nhân
                    </a>
                    <a href="{{ route('user.bookings.history') }}" class="flex items-center gap-3 px-4 py-2.5 bg-brand-start/10 text-brand-start rounded-xl font-bold border border-brand-start/20 text-sm">
                        <i class="ph-fill ph-ticket text-lg"></i> Lịch sử đặt vé
                    </a>
                    @if(Route::has('user.loyalty.history'))
                        <a href="{{ route('user.loyalty.history') }}" class="flex items-center gap-3 px-4 py-2.5 app-muted hover:app-text hover:bg-brand-start/5 rounded-xl font-medium transition-colors text-sm">
                            <i class="ph ph-coins text-lg"></i> Lịch sử điểm
                        </a>
                    @endif
                    <a href="{{ route('user.reviews.index') }}" class="flex items-center gap-3 px-4 py-2.5 app-muted hover:app-text hover:bg-brand-start/5 rounded-xl font-medium transition-colors text-sm">
                        <i class="ph ph-star text-lg"></i> Đánh giá của tôi
                    </a>
                </div>
            </div>
        </aside>

        <section class="lg:col-span-3">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-3xl font-bold app-text">Lịch sử đặt vé</h1>
                    <p class="app-muted mt-1">Theo dõi vé đã đặt và mã QR của bạn.</p>
                </div>

                <form method="GET" action="{{ route('user.bookings.history') }}" class="flex gap-2">
                    <select name="status" class="app-input border app-border rounded-xl text-sm px-3 py-2">
                        <option value="">Tất cả</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Chờ thanh toán</option>
                        <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Chưa sử dụng</option>
                        <option value="used" {{ request('status') == 'used' ? 'selected' : '' }}>Đã sử dụng</option>
                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Đã hủy</option>
                        <option value="expired" {{ request('status') == 'expired' ? 'selected' : '' }}>Hết hạn</option>
                    </select>
                    <button type="submit" class="px-4 py-2 bg-brand-start text-white text-sm font-bold rounded-xl">Lọc</button>
                </form>
            </div>

            <x-validation-summary class="mb-5" :errors="$errors" />

            <div class="space-y-5">
                @forelse($bookings as $booking)
                    @php
                        $actions = $bookingActions[$booking->id];
                        $poster = $booking->showtime->movie->poster_url;
                        $canUseTicket = in_array($booking->id, $ticketableBookingIds, true);
                    @endphp

                    <article class="app-card border border-brand-start/20 rounded-3xl p-4 sm:p-6 hover:border-brand-start/60 transition-colors relative overflow-hidden">
                        <div class="absolute top-0 right-0 {{ $actions['badge_class'] }} text-xs font-bold px-3 py-1.5 rounded-bl-xl">
                            {{ $actions['badge_label'] }}
                        </div>

                        <div class="flex flex-col sm:flex-row gap-5">
                            <div class="w-28 shrink-0">
                                <div class="poster-frame rounded-2xl">
                                    @if($poster)
                                        <img src="{{ $poster }}" alt="{{ $booking->showtime->movie->title }}" loading="lazy">
                                    @else
                                        <div class="fallback-poster">
                                            <i class="ph-fill ph-film-slate"></i>
                                            <strong class="text-sm">MovieMate</strong>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="flex-grow min-w-0">
                                <h2 class="text-xl font-bold app-text mb-1 pr-20">{{ $booking->showtime->movie->title }}</h2>
                                <p class="app-muted text-xs mb-4">Mã vé: <span class="app-text font-mono font-bold">{{ $booking->booking_code }}</span></p>

                                <div class="grid grid-cols-2 gap-x-4 gap-y-3 text-xs mb-4">
                                    <div><p class="app-muted mb-0.5">Thời gian</p><p class="app-text font-semibold">{{ $booking->showtime?->show_time ? \Carbon\Carbon::parse($booking->showtime->show_time)->format('H:i') : '--:--' }} - {{ $booking->showtime?->show_date ? \Carbon\Carbon::parse($booking->showtime->show_date)->format('d/m/Y') : 'Đang cập nhật' }}</p></div>
                                    <div><p class="app-muted mb-0.5">Ghế</p><p class="text-brand-start font-bold text-sm">{{ $booking->seat_codes }}</p></div>
                                    <div><p class="app-muted mb-0.5">Rạp</p><p class="app-text font-semibold">{{ $booking->showtime->cinema->name }}</p></div>
                                    <div><p class="app-muted mb-0.5">Phòng</p><p class="app-text font-semibold">{{ $booking->showtime->room->name }}</p></div>
                                </div>

                                <div class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t app-border">
                                    <div>
                                        <p class="text-xs app-muted mb-0.5">Tổng tiền</p>
                                        <p class="app-text font-bold text-lg">{{ number_format((int) $booking->total_amount, 0, ',', '.') }} VNĐ</p>
                                        @if($booking->promotion_discount_amount > 0)
                                            <p class="text-xs text-success">Mã {{ $booking->discountCodeRedemptions->pluck('code_snapshot')->join(', ') }}: −{{ number_format((int)$booking->promotion_discount_amount,0,',','.') }} VNĐ</p>
                                        @endif
                                        @if($booking->pointRedemption)
                                            <p class="text-xs text-ai-start">Đã dùng {{ number_format($booking->pointRedemption->points,0,',','.') }} điểm: −{{ number_format((int)$booking->points_discount_amount,0,',','.') }} VNĐ</p>
                                        @endif
                                        @if($booking->loyalty_points_earned > 0)
                                            <p class="text-xs text-ai-start font-semibold">+{{ number_format($booking->loyalty_points_earned,0,',','.') }} điểm</p>
                                        @endif
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        @if($actions['can_resume'])
                                            <form method="POST" action="{{ route('payments.resume', $booking) }}" class="inline" data-submit-once>
                                                @csrf
                                                <button type="submit" data-loading-label="Đang chuyển đến cổng thanh toán…" class="px-4 py-2 bg-gradient-to-r from-brand-start to-brand-end text-white rounded-xl text-xs font-bold hover:shadow-lg transition-all">
                                                    Tiếp tục thanh toán
                                                </button>
                                            </form>
                                        @endif

                                        @if($canUseTicket)
                                            <a href="{{ route('user.bookings.ticket', $booking) }}" class="px-4 py-2 bg-gradient-to-r from-brand-start to-brand-end text-white rounded-xl text-xs font-bold hover:shadow-lg transition-all">
                                                Xem vé QR
                                            </a>
                                            <form method="POST" action="{{ route('user.bookings.ticket-email.resend', $booking) }}" class="inline" data-submit-once>
                                                @csrf
                                                <button type="submit" data-loading-label="Đang gửi…" class="px-3 py-2 border app-border app-muted hover:app-text rounded-xl text-xs font-semibold transition-colors">
                                                    Gửi lại email vé
                                                </button>
                                            </form>
                                        @endif

                                        @if($actions['can_cancel_local'])
                                            <button type="button" class="px-3 py-2 border border-error/40 text-error hover:bg-error/10 rounded-xl text-xs font-semibold transition-colors" data-modal-open="cancel-booking-{{ $booking->id }}" aria-haspopup="dialog" aria-controls="cancel-booking-{{ $booking->id }}">
                                                Hủy đơn
                                            </button>
                                        @elseif($actions['can_cancel_payos'])
                                            <button type="button" class="px-3 py-2 border border-error/40 text-error hover:bg-error/10 rounded-xl text-xs font-semibold transition-colors" data-modal-open="cancel-booking-{{ $booking->id }}" aria-haspopup="dialog" aria-controls="cancel-booking-{{ $booking->id }}">
                                                Hủy đơn
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if($actions['can_cancel_local'] || $actions['can_cancel_payos'])
                            <x-ui.modal id="cancel-booking-{{ $booking->id }}" title="Hủy đơn đặt vé?" description-id="cancel-booking-{{ $booking->id }}-description">
                                    <p id="cancel-booking-{{ $booking->id }}-description" class="app-muted text-sm">Nếu tiếp tục, các ghế đang giữ sẽ được giải phóng và đơn này không thể tiếp tục thanh toán.</p>
                                    <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                        <button type="button" data-modal-close="cancel-booking-{{ $booking->id }}" data-modal-initial-focus class="px-4 py-2 border app-border app-muted hover:app-text rounded-xl text-sm font-semibold transition-colors">Giữ đơn</button>
                                        <form method="POST" action="{{ $actions['can_cancel_payos'] ? route('payments.payos.cancel-attempt', $booking) : route('user.bookings.cancel', $booking) }}" data-submit-once>
                                            @csrf
                                            @unless($actions['can_cancel_payos'])
                                                @method('DELETE')
                                            @endunless
                                            <button type="submit" data-loading-label="Đang xác minh hủy…" class="w-full px-4 py-2 border border-error/40 text-error hover:bg-error/10 rounded-xl text-sm font-bold transition-colors">Hủy đơn</button>
                                        </form>
                                    </div>
                            </x-ui.modal>
                        @endif
                    </article>
                @empty
                    <div class="app-card border app-border rounded-3xl p-10 text-center">
                        <div class="w-16 h-16 rounded-2xl bg-brand-start/10 text-brand-start flex items-center justify-center mx-auto mb-4">
                            <i class="ph ph-ticket text-3xl"></i>
                        </div>
                        <p class="app-muted">Bạn chưa có vé nào.</p>
                    </div>
                @endforelse

                {{ $bookings->withQueryString()->links() }}
            </div>
        </section>
    </div>
</div>
@endsection
