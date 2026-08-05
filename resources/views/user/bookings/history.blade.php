@extends('layouts.user')

@section('title', 'Lịch sử đặt vé - MovieMate')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <aside class="lg:col-span-1">
            <div class="app-card border app-border rounded-3xl p-6 flex flex-col items-center text-center sticky top-24">
                <div class="w-20 h-20 rounded-full overflow-hidden mb-3 border-2 border-brand-start/30">
                    <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name) }}&background=FF3D57&color=fff&size=128" alt="Avatar" class="w-full h-full object-cover">
                </div>
                <h2 class="font-bold app-text mb-1">{{ Auth::user()->name }}</h2>
                <p class="text-xs text-ai-start font-bold mb-5">Hạng {{ Auth::user()->role->name ?? 'Khách' }}</p>

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
                    <a href="#" class="flex items-center gap-3 px-4 py-2.5 app-muted hover:app-text hover:bg-brand-start/5 rounded-xl font-medium transition-colors text-sm">
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

            @if($errors->any())
                <div class="mb-5 rounded-2xl border border-error/30 bg-error/10 text-error px-4 py-3 text-sm font-semibold">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="space-y-5">
                @forelse($bookings as $booking)
                    @php
                        $statusMap = [
                            'pending' => ['label' => 'Chờ thanh toán', 'class' => 'bg-yellow-100 text-yellow-700'],
                            'paid' => ['label' => 'Chưa sử dụng', 'class' => 'bg-brand-start text-white'],
                            'used' => ['label' => 'Đã sử dụng', 'class' => 'bg-blue-100 text-blue-700'],
                            'cancelled' => ['label' => 'Đã hủy', 'class' => 'bg-red-100 text-red-700'],
                            'expired' => ['label' => 'Hết hạn', 'class' => 'bg-gray-100 text-gray-700'],
                        ];
                        $status = $statusMap[$booking->booking_status] ?? $statusMap['pending'];
                        $poster = $booking->showtime->movie->poster_url;
                    @endphp

                    <article class="app-card border border-brand-start/20 rounded-3xl p-4 sm:p-6 hover:border-brand-start/60 transition-colors relative overflow-hidden">
                        <div class="absolute top-0 right-0 {{ $status['class'] }} text-xs font-bold px-3 py-1.5 rounded-bl-xl">
                            {{ $status['label'] }}
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
                                    <div><p class="app-muted mb-0.5">Ghế</p><p class="text-brand-start font-bold text-sm">{{ $booking->bookingSeats->pluck('seat.seat_code')->join(', ') }}</p></div>
                                    <div><p class="app-muted mb-0.5">Rạp</p><p class="app-text font-semibold">{{ $booking->showtime->cinema->name }}</p></div>
                                    <div><p class="app-muted mb-0.5">Phòng</p><p class="app-text font-semibold">{{ $booking->showtime->room->name }}</p></div>
                                </div>

                                <div class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t app-border">
                                    <div>
                                        <p class="text-xs app-muted mb-0.5">Tổng tiền</p>
                                        <p class="app-text font-bold text-lg">{{ number_format($booking->total_amount,0,',','.') }}đ</p>
                                        @if($booking->loyalty_points_earned > 0)
                                            <p class="text-xs text-ai-start font-semibold">+{{ number_format($booking->loyalty_points_earned,0,',','.') }} điểm</p>
                                        @endif
                                    </div>
                                    <div class="flex gap-2">
                                        @if($booking->booking_status === 'pending' && $booking->payment?->checkout_url)
                                            <a href="{{ $booking->payment->checkout_url }}" class="px-4 py-2 bg-gradient-to-r from-brand-start to-brand-end text-white rounded-xl text-xs font-bold hover:shadow-lg transition-all">
                                                Thanh toán tiếp
                                            </a>
                                        @else
                                            <a href="{{ route('user.bookings.ticket', $booking) }}" class="px-4 py-2 bg-gradient-to-r from-brand-start to-brand-end text-white rounded-xl text-xs font-bold hover:shadow-lg transition-all">
                                                Xem vé QR
                                            </a>
                                        @endif
                                        @if(in_array($booking->booking_status, ['pending', 'paid'], true))
                                            <form method="POST" action="{{ route('user.bookings.cancel', $booking) }}" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="px-3 py-2 border app-border app-muted hover:app-text rounded-xl text-xs font-semibold transition-colors" onclick="return confirm('{{ $booking->booking_status === 'pending' ? 'Bạn chắc chắn muốn hủy thanh toán này?' : 'Bạn chắc chắn muốn hủy vé này?' }}')">
                                                    {{ $booking->booking_status === 'pending' ? 'Hủy thanh toán' : 'Hủy vé' }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
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
