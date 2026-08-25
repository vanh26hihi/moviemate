@extends('layouts.admin')
@section('title', 'Xem trước lịch giá')
@section('page-title', 'Xem trước lịch giá')
@section('content')
<div class="admin-page-header items-start">
    <div>
        <a class="text-sm font-bold text-brand-start" href="{{ route('admin.price-books.versions.show', $version) }}">← Quay lại bảng giá v{{ $version->version_number }}</a>
        <h1 class="admin-page-title mt-2">Kiểm tra lịch giá mới</h1>
        <p class="admin-page-subtitle">Chưa có thay đổi nào được lưu. Hãy kiểm tra từng khoảng trước khi xác nhận.</p>
    </div>
    <span class="status-badge border border-success/40 text-success">Không chồng lấn · Không ngày trống</span>
</div>

<section class="cinema-card overflow-hidden" aria-labelledby="replacement-schedule-title">
    <div class="border-b app-border p-5 sm:p-6">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-start">Lịch sau thay đổi</p>
        <h2 id="replacement-schedule-title" class="mt-1 text-2xl font-extrabold app-heading">
            {{ $plan['kind'] === 'single_day' ? 'Giá đặc biệt ngày '.$plan['change_date']->format('d/m/Y') : 'Đổi giá từ ngày '.$plan['change_date']->format('d/m/Y') }}
        </h2>
        <p class="mt-1 text-sm app-muted">Hệ thống sẽ ngừng phiên bản v{{ $version->version_number }} và phát hành các khoảng bên dưới trong cùng một giao dịch. Nếu có lỗi, toàn bộ thao tác được hủy.</p>
    </div>

    <div class="grid gap-4 p-5 sm:p-6 xl:grid-cols-3">
        @foreach($plan['segments'] as $index => $segment)
            <article class="rounded-2xl border {{ $segment['purpose'] === 'changed' ? 'border-brand-start bg-brand-start/5' : 'app-border' }} p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.14em] {{ $segment['purpose'] === 'changed' ? 'text-brand-start' : 'app-muted' }}">Khoảng {{ $index + 1 }}</p>
                        <h3 class="mt-1 font-extrabold app-heading">{{ $segment['label'] }}</h3>
                    </div>
                    @if($segment['purpose'] === 'changed')<span class="status-badge border border-brand-start/40 text-brand-start">Giá mới</span>@endif
                </div>

                <p class="mt-4 text-sm font-bold app-heading">
                    {{ $segment['effective_from']->format('d/m/Y') }} →
                    {{ $segment['effective_until'] ? $segment['effective_until']->copy()->subDay()->format('d/m/Y') : 'Không giới hạn' }}
                </p>

                <dl class="mt-4 space-y-2 border-t app-border pt-4 text-sm">
                    @foreach($plan['seat_types'] as $seatType)
                        <div class="flex items-center justify-between gap-3">
                            <dt class="app-muted">{{ \App\Support\StatusLabel::for('seat_type', $seatType->code) }}</dt>
                            <dd class="font-extrabold tabular-nums app-heading">{{ number_format($segment['ticket_prices'][(int) $seatType->id], 0, ',', '.') }} ₫</dd>
                        </div>
                    @endforeach
                </dl>

                <p class="mt-4 rounded-xl bg-black/10 p-3 text-xs app-muted">
                    Giữ {{ $segment['contextual_rule_count'] }} quy tắc theo giờ, cuối tuần, ngày lễ, loại phòng, phòng hoặc chi nhánh.
                </p>
            </article>
        @endforeach
    </div>
</section>

<section class="cinema-card mt-6 p-5 sm:p-6" aria-labelledby="confirm-schedule-title">
    <h2 id="confirm-schedule-title" class="text-xl font-extrabold app-heading">Xác nhận áp dụng</h2>
    <p class="mt-1 max-w-3xl text-sm app-muted">Phiên bản cũ chỉ chuyển sang lịch sử; định nghĩa tài chính và giá đã snapshot của các suất chiếu hiện có vẫn nguyên vẹn.</p>
    <form method="POST" action="{{ route('admin.price-books.versions.schedule-change.apply', $version) }}" class="mt-5">
        @csrf
        <input type="hidden" name="change_kind" value="{{ $plan['kind'] }}">
        <input type="hidden" name="change_date" value="{{ $plan['change_date']->format('Y-m-d') }}">
        @foreach($plan['ticket_prices'] as $seatTypeId => $price)
            <input type="hidden" name="ticket_prices[{{ $seatTypeId }}]" value="{{ $price }}">
        @endforeach
        <button class="admin-btn-primary" onclick="return confirm('Áp dụng lịch giá mới? Thao tác sẽ ngừng phiên bản hiện tại và phát hành các khoảng thay thế liền kề.')">Áp dụng lịch giá mới</button>
        <a class="admin-btn-secondary ml-2" href="{{ route('admin.price-books.versions.show', $version) }}">Chỉnh lại</a>
    </form>
</section>
@endsection
