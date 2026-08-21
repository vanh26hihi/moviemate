@extends('layouts.admin')

@section('title', 'Hồ sơ '.$managedUser->name.' - MovieMate')
@section('page-title', 'Hồ sơ người dùng')

@section('content')
@php
    $money = fn ($value) => number_format((int) $value, 0, ',', '.').' VNĐ';
    $statusTone = fn (string $status) => match ($status) {
        'paid' => 'bg-success/10 text-success',
        'pending_payment' => 'bg-warning/10 text-warning',
        'cancelled', 'expired' => 'bg-error/10 text-error',
        default => 'app-secondary app-muted',
    };
@endphp

<div class="admin-page-header">
    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="admin-page-title truncate">{{ $managedUser->name }}</h1>
            <span class="rounded-full px-3 py-1 text-xs font-extrabold {{ $managedUser->status === 'active' ? 'bg-success/10 text-success' : 'bg-error/10 text-error' }}">{{ $managedUser->status_label }}</span>
        </div>
        <p class="admin-page-subtitle">Hồ sơ tài khoản, hoạt động đặt vé và dấu vết quản trị trong phạm vi được phép.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.users.index') }}" class="admin-btn-secondary"><i class="ph ph-arrow-left"></i> Danh sách</a>
        <a href="{{ route('admin.users.edit', $managedUser) }}" class="admin-btn-primary"><i class="ph ph-pencil-simple"></i> Quản lý</a>
    </div>
</div>

<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Tổng quan người dùng">
    @foreach([
        ['label' => 'Tổng đơn', 'value' => number_format($summary['booking_count']), 'note' => $summary['paid_count'].' đơn đã thanh toán', 'icon' => 'ph-ticket', 'tone' => 'text-brand-start'],
        ['label' => 'Giá trị đã đặt', 'value' => $money($summary['paid_value']), 'note' => 'Theo trạng thái đơn đã thanh toán', 'icon' => 'ph-wallet', 'tone' => 'text-success'],
        ['label' => 'Đánh giá', 'value' => number_format($summary['review_count']), 'note' => $summary['review_count'] ? $summary['average_rating'].' / 5 điểm trung bình' : 'Chưa gửi đánh giá', 'icon' => 'ph-star', 'tone' => 'text-warning'],
        ['label' => 'Không thành công', 'value' => number_format($summary['unsuccessful_count']), 'note' => 'Đơn đã hủy hoặc hết hạn', 'icon' => 'ph-x-circle', 'tone' => 'text-error'],
    ] as $card)
        <article class="app-card rounded-2xl border app-border p-5">
            <div class="flex items-center justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-wide app-muted">{{ $card['label'] }}</p><p class="mt-2 text-2xl font-black app-heading">{{ $card['value'] }}</p></div><span class="grid size-11 place-items-center rounded-xl app-secondary {{ $card['tone'] }}"><i class="ph {{ $card['icon'] }} text-2xl"></i></span></div>
            <p class="mt-3 text-xs app-muted">{{ $card['note'] }}</p>
        </article>
    @endforeach
</section>

<div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(300px,1fr)]">
    <section class="app-card rounded-2xl border app-border" aria-labelledby="booking-history-title">
        <div class="border-b app-border p-5 sm:p-6">
            <div class="flex flex-wrap items-end justify-between gap-3"><div><h2 id="booking-history-title" class="text-xl font-black app-heading">Lịch sử đặt vé</h2><p class="mt-1 text-sm app-muted">{{ $bookings->total() }} đơn thuộc phạm vi chi nhánh hiện tại.</p></div><div class="flex items-center gap-3">@if(array_filter($filters))<a href="{{ route('admin.users.show', $managedUser) }}" class="text-sm font-bold text-brand-start hover:underline">Xóa bộ lọc</a>@endif<a href="{{ route('admin.users.bookings.export', [$managedUser] + $filters) }}" class="admin-btn-secondary"><i class="ph ph-download-simple"></i> Xuất CSV</a></div></div>
            <form method="GET" class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <input name="booking_search" value="{{ $filters['booking_search'] ?? '' }}" maxlength="100" class="app-input rounded-xl border app-border px-4 py-2.5 xl:col-span-2" placeholder="Mã đơn hoặc tên phim">
                <select name="booking_status" class="app-input rounded-xl border app-border px-3 py-2.5"><option value="">Mọi trạng thái</option>@foreach($bookingStatuses as $status)<option value="{{ $status }}" @selected(($filters['booking_status'] ?? '') === $status)>{{ \App\Support\StatusLabel::for('booking_admin', $status) }}</option>@endforeach</select>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="app-input rounded-xl border app-border px-3 py-2.5" aria-label="Từ ngày">
                <div class="flex gap-2"><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="app-input min-w-0 flex-1 rounded-xl border app-border px-3 py-2.5" aria-label="Đến ngày"><button class="admin-btn-primary" aria-label="Lọc"><i class="ph ph-funnel"></i></button></div>
            </form>
        </div>
        <div class="overflow-x-auto"><table class="admin-table min-w-[850px]">
            <thead><tr><th>Đơn đặt vé</th><th>Phim & suất chiếu</th><th>Chi nhánh</th><th>Trạng thái</th><th class="text-right">Giá trị</th><th></th></tr></thead>
            <tbody>@forelse($bookings as $booking)<tr>
                <td><p class="font-extrabold app-text">{{ $booking->booking_code }}</p><p class="mt-1 text-xs app-muted">{{ $booking->created_at?->format('d/m/Y H:i') }}</p></td>
                <td><p class="max-w-52 truncate font-bold app-text">{{ $booking->showtime?->movie?->title ?? 'Phim đang cập nhật' }}</p><p class="mt-1 text-xs app-muted">{{ $booking->showtime_label }} · {{ $booking->showtime?->room?->name ?? 'Phòng đang cập nhật' }}</p></td>
                <td class="font-semibold app-text">{{ $booking->cinema?->name ?? 'Đang cập nhật' }}</td>
                <td><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-extrabold {{ $statusTone($booking->booking_status) }}">{{ \App\Support\StatusLabel::for('booking_admin', $booking->booking_status) }}</span></td>
                <td class="text-right font-extrabold app-text">{{ $money($booking->total_amount) }}</td>
                <td class="text-right"><a href="{{ route('admin.bookings.show', $booking) }}" class="admin-btn-secondary">Xem</a></td>
            </tr>@empty<tr><td colspan="6" class="py-12 text-center"><i class="ph ph-ticket text-4xl app-muted"></i><p class="mt-3 font-bold app-text">Không có đơn phù hợp</p><p class="mt-1 text-sm app-muted">Thử thay đổi bộ lọc.</p></td></tr>@endforelse</tbody>
        </table></div>
        @if($bookings->hasPages())<div class="border-t app-border p-5">{{ $bookings->links() }}</div>@endif
    </section>

    <aside class="space-y-6">
        <section class="app-card rounded-2xl border app-border p-5"><h2 class="text-lg font-black app-heading">Thông tin tài khoản</h2><dl class="mt-5 space-y-4 text-sm">
            <div><dt class="text-xs font-bold uppercase app-muted">Email</dt><dd class="mt-1 break-all font-semibold app-text">{{ $managedUser->email }}</dd></div>
            <div><dt class="text-xs font-bold uppercase app-muted">Vai trò</dt><dd class="mt-1 font-semibold app-text">{{ $managedUser->role?->display_name ?? 'Chưa gán vai trò' }}</dd></div>
            <div><dt class="text-xs font-bold uppercase app-muted">Xác minh email</dt><dd class="mt-1 font-semibold {{ $managedUser->email_verified_at ? 'text-success' : 'text-warning' }}">{{ $managedUser->email_verified_at?->format('d/m/Y H:i') ?? 'Chưa xác minh' }}</dd></div>
            <div><dt class="text-xs font-bold uppercase app-muted">Ngày tham gia</dt><dd class="mt-1 font-semibold app-text">{{ $managedUser->created_at?->format('d/m/Y H:i') }}</dd></div>
            <div><dt class="text-xs font-bold uppercase app-muted">Đặt vé gần nhất</dt><dd class="mt-1 font-semibold app-text">{{ $summary['latest_booking_at'] ? \Illuminate\Support\Carbon::parse($summary['latest_booking_at'])->format('d/m/Y H:i') : 'Chưa có' }}</dd></div>
        </dl></section>
        <section class="app-card rounded-2xl border app-border p-5"><div class="flex justify-between gap-3"><h2 class="text-lg font-black app-heading">Phân công chi nhánh</h2><span class="rounded-full app-secondary px-2.5 py-1 text-xs font-bold app-muted">{{ $managedUser->cinemaAssignments->where('status', 'active')->count() }}</span></div><div class="mt-4 space-y-3">
            @forelse($managedUser->cinemaAssignments->take(6) as $assignment)<article class="rounded-xl app-secondary p-3"><div class="flex justify-between gap-3"><strong class="text-sm app-text">{{ $assignment->cinema?->name ?? 'Chi nhánh đã xóa' }}</strong><span class="text-xs font-bold {{ $assignment->status === 'active' ? 'text-success' : 'app-muted' }}">{{ $assignment->status === 'active' ? 'Hoạt động' : 'Đã thu hồi' }}</span></div><p class="mt-2 text-xs app-muted">Từ {{ $assignment->assigned_at?->format('d/m/Y H:i') ?? 'không rõ' }}{{ $assignment->assignedBy ? ' · bởi '.$assignment->assignedBy->name : '' }}</p></article>@empty<p class="rounded-xl border border-dashed app-border p-4 text-center text-sm app-muted">Không có lịch sử phân công.</p>@endforelse
        </div></section>
        <section class="app-card rounded-2xl border app-border p-5"><h2 class="text-lg font-black app-heading">Hoạt động gần đây</h2><div class="mt-4 space-y-4">
            @forelse($activity as $log)<article class="relative border-l-2 app-border pl-4"><span class="absolute -left-[5px] top-1 size-2 rounded-full bg-brand-start"></span><p class="text-sm font-bold app-text">{{ str_replace(['.', '_'], ' ', $log->action) }}</p><p class="mt-1 text-xs app-muted">{{ $log->created_at?->format('d/m/Y H:i') }} · {{ $log->actor?->name ?? ($log->actor_user_id ? 'Tài khoản đã xóa' : 'Hệ thống') }}</p></article>@empty<p class="rounded-xl border border-dashed app-border p-4 text-center text-sm app-muted">Chưa có hoạt động liên quan.</p>@endforelse
        </div></section>
    </aside>
</div>
@endsection
