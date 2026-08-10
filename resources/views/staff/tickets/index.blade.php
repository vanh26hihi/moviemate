@extends('layouts.staff')

@section('title', 'Danh sách vé - MovieMate Staff')
@section('page-title', 'Danh sách vé')

@section('content')
@php
    $statusMap = [
        'paid' => ['label' => 'Chưa sử dụng', 'class' => 'bg-success/10 text-success border-success/20'],
        'used' => ['label' => 'Đã sử dụng', 'class' => 'bg-warning/10 text-warning border-warning/20'],
        'cancelled' => ['label' => 'Đã hủy', 'class' => 'bg-error/10 text-error border-error/20'],
        'expired' => ['label' => 'Hết hạn', 'class' => 'bg-gray-500/10 text-gray-400 border-gray-500/20'],
    ];
@endphp

<div class="app-card border app-border rounded-2xl overflow-hidden shadow-lg">
    <div class="p-6 border-b app-border">
        <form method="GET" action="{{ route('staff.tickets.index') }}" class="flex flex-col md:flex-row gap-4 items-center justify-between">
            <div class="relative w-full md:w-96">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i class="ph ph-magnifying-glass app-muted text-lg"></i>
                </div>
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    class="w-full pl-11 pr-4 py-2.5 app-input border app-border rounded-xl app-text focus:outline-none focus:border-ai-start transition-colors text-sm"
                    placeholder="Tìm mã vé, khách hàng, phim..."
                >
            </div>

            <div class="flex flex-wrap gap-2 w-full md:w-auto">
                <a href="{{ route('staff.tickets.index') }}" class="px-4 py-2 {{ request('status') ? 'app-secondary border app-border app-muted' : 'bg-ai-start text-white' }} text-sm font-semibold rounded-lg whitespace-nowrap">
                    Tất cả ({{ $counts['all'] }})
                </a>
                <a href="{{ route('staff.tickets.index', ['status' => 'paid']) }}" class="px-4 py-2 {{ request('status') === 'paid' ? 'bg-ai-start text-white' : 'app-secondary border app-border app-muted' }} text-sm font-semibold rounded-lg whitespace-nowrap">
                    Chưa sử dụng ({{ $counts['paid'] }})
                </a>
                <a href="{{ route('staff.tickets.index', ['status' => 'used']) }}" class="px-4 py-2 {{ request('status') === 'used' ? 'bg-ai-start text-white' : 'app-secondary border app-border app-muted' }} text-sm font-semibold rounded-lg whitespace-nowrap">
                    Đã sử dụng ({{ $counts['used'] }})
                </a>
                <a href="{{ route('staff.tickets.index', ['status' => 'cancelled']) }}" class="px-4 py-2 {{ request('status') === 'cancelled' ? 'bg-ai-start text-white' : 'app-secondary border app-border app-muted' }} text-sm font-semibold rounded-lg whitespace-nowrap">
                    Đã hủy ({{ $counts['cancelled'] }})
                </a>
                <button type="submit" class="px-4 py-2 bg-brand-start text-white text-sm font-bold rounded-lg whitespace-nowrap">
                    Lọc
                </button>
            </div>
        </form>
    </div>

    <div class="overflow-x-auto hide-scrollbar">
        <table class="w-full text-left border-collapse whitespace-nowrap">
            <thead>
                <tr class="app-secondary text-xs uppercase tracking-wider app-muted border-b app-border">
                    <th class="p-4 font-medium">Mã vé</th>
                    <th class="p-4 font-medium">Khách hàng</th>
                    <th class="p-4 font-medium">Phim</th>
                    <th class="p-4 font-medium">Suất chiếu</th>
                    <th class="p-4 font-medium">Ghế</th>
                    <th class="p-4 font-medium text-right">Tổng tiền</th>
                    <th class="p-4 font-medium text-center">Trạng thái</th>
                    <th class="p-4 font-medium text-center">Hành động</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--border-color)] text-sm">
                @forelse($bookings as $booking)
                    @php
                        $status = $statusMap[$booking->booking_status] ?? ['label' => $booking->booking_status, 'class' => 'bg-gray-500/10 text-gray-400 border-gray-500/20'];
                        $showDate = $booking->showtime?->show_date ? \Carbon\Carbon::parse($booking->showtime->show_date)->format('d/m/Y') : '--';
                        $showTime = $booking->showtime?->show_time ? \Carbon\Carbon::parse($booking->showtime->show_time)->format('H:i') : '--:--';
                        $seatCodes = $booking->bookingSeats->pluck('seat.seat_code')->filter()->join(', ') ?: '-';
                    @endphp
                    <tr class="hover:bg-white/5 transition-colors">
                        <td class="p-4">
                            <span class="font-mono app-text font-bold">{{ $booking->booking_code }}</span>
                        </td>
                        <td class="p-4">
                            <p class="app-text font-semibold">{{ $booking->user->name ?? 'Khách' }}</p>
                            <p class="text-xs app-muted">{{ $booking->user->phone ?? $booking->user->email ?? '-' }}</p>
                        </td>
                        <td class="p-4">
                            <p class="app-text font-semibold truncate max-w-[180px]">{{ $booking->showtime?->movie?->title ?? 'Không rõ phim' }}</p>
                            <p class="text-xs app-muted">{{ $booking->showtime?->room?->name ?? 'Không rõ phòng' }}</p>
                        </td>
                        <td class="p-4 app-text">
                            {{ $showTime }}<br><span class="text-xs app-muted">{{ $showDate }}</span>
                        </td>
                        <td class="p-4 text-brand-start font-bold">{{ $seatCodes }}</td>
                        <td class="p-4 text-right app-text font-semibold">{{ number_format($booking->total_amount, 0, ',', '.') }}đ</td>
                        <td class="p-4 text-center">
                            <span class="inline-flex px-2 py-1 border rounded text-[10px] font-bold uppercase tracking-wider {{ $status['class'] }}">
                                {{ $status['label'] }}
                            </span>
                        </td>
                        <td class="p-4">
                            <div class="flex items-center justify-center gap-2">
                                @if($booking->booking_status === 'paid')
                                    <a href="{{ route('staff.tickets.valid', $booking) }}" class="w-8 h-8 rounded-lg bg-ai-start/10 text-ai-start hover:bg-ai-start hover:text-white flex items-center justify-center transition-colors" title="Kiểm tra vé">
                                        <i class="ph-bold ph-check"></i>
                                    </a>
                                @elseif($booking->booking_status === 'used')
                                    <a href="{{ route('staff.tickets.used', $booking) }}" class="w-8 h-8 rounded-lg bg-warning/10 text-warning hover:bg-warning hover:text-white flex items-center justify-center transition-colors" title="Xem vé đã dùng">
                                        <i class="ph-bold ph-warning"></i>
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="p-8 text-center app-muted">Không có vé nào phù hợp.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="p-4 border-t app-border app-secondary">
        {{ $bookings->links() }}
    </div>
</div>
@endsection
