@extends('layouts.admin')
@section('title', 'Chỉnh sửa suất chiếu - Quản trị MovieMate')
@section('page-title', 'Chỉnh sửa suất chiếu')
@section('suppress-global-validation-summary', '1')

@section('content')
<div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">

    <div class="rounded-xl border app-border p-4">
        <div class="flex items-center gap-2">
            <i class="ph ph-film-strip text-brand-start"></i>
            <p class="text-xs font-bold uppercase tracking-wider app-muted">
                Phim đã chọn
            </p>
        </div>

        <p
            id="schedule-movie-name"
            class="mt-2 font-extrabold app-text"
        >
            Chưa chọn phim
        </p>
    </div>

    <div class="rounded-xl border app-border p-4">
        <div class="flex items-center gap-2">
            <i class="ph ph-timer text-brand-start"></i>
            <p class="text-xs font-bold uppercase tracking-wider app-muted">
                Thời lượng
            </p>
        </div>

        <p
            id="schedule-duration"
            class="mt-2 font-extrabold app-text"
        >
            Chưa xác định
        </p>
    </div>

    <div class="rounded-xl border app-border p-4">
        <div class="flex items-center gap-2">
            <i class="ph ph-clock-countdown text-brand-start"></i>
            <p class="text-xs font-bold uppercase tracking-wider app-muted">
                Dự kiến kết thúc
            </p>
        </div>

        <p
            id="schedule-end-time"
            class="mt-2 font-extrabold app-text"
        >
            Chưa xác định
        </p>
    </div>

    <div class="rounded-xl border app-border p-4">
        <div class="flex items-center gap-2">
            <i class="ph ph-broom text-brand-start"></i>
            <p class="text-xs font-bold uppercase tracking-wider app-muted">
                Dọn phòng
            </p>
        </div>

        <p
            id="schedule-room-ready"
            class="mt-2 font-extrabold app-text"
        >
            Chưa xác định
        </p>
    </div>

</div>


<div class="mt-5">
    <p class="mb-3 text-xs font-bold uppercase tracking-wider app-muted">
        Chọn nhanh giờ chiếu
    </p>

    <div class="flex flex-wrap gap-2">

        @foreach([
            '08:00',
            '09:30',
            '11:00',
            '12:30',
            '14:00',
            '15:30',
            '17:00',
            '18:30',
            '20:00',
            '21:30',
            '23:00'
        ] as $quickTime)

            <button
                type="button"
                class="showtime-quick-time rounded-xl border app-border px-3 py-2 text-sm font-bold app-text transition hover:border-brand-start hover:text-brand-start"
                data-time="{{ $quickTime }}"
            >
                <i class="ph ph-clock mr-1"></i>
                {{ $quickTime }}
            </button>

        @endforeach

    </div>
</div>


<div
    id="schedule-warning"
    class="mt-5 hidden rounded-xl border border-warning/20 bg-warning/5 p-4"
>
    <div class="flex items-start gap-3">

        <i class="ph ph-warning-circle mt-0.5 text-xl text-warning"></i>

        <div>
            <p class="font-extrabold text-warning">
                Kiểm tra thời gian suất chiếu
            </p>

            <p
                id="schedule-warning-message"
                class="mt-1 text-sm app-muted"
            ></p>
        </div>

    </div>
</div>


<div
    id="schedule-success"
    class="mt-5 hidden rounded-xl border border-success/20 bg-success/5 p-4"
>
    <div class="flex items-start gap-3">

        <i class="ph ph-check-circle mt-0.5 text-xl text-success"></i>

        <div>
            <p class="font-extrabold text-success">
                Thời gian đã sẵn sàng
            </p>

            <p class="mt-1 text-sm app-muted">
                Ngày và giờ đã được nhập đầy đủ.
                Hệ thống sẽ kiểm tra xung đột lịch một lần nữa khi lưu.
            </p>
        </div>

    </div>
</div>
<div class="max-w-5xl">
    <div class="cinema-card p-6 sm:p-8">
        <h1 class="text-2xl font-extrabold app-text mb-2">Cập nhật suất chiếu</h1>
        <p class="app-muted mb-6">
            Chi nhánh: {{ $showtime->cinema?->name ?? $cinema?->name ?? '—' }} · Định dạng trình chiếu: {{ $showtime->presentationFormat?->code ?? 'Chưa gán' }} · Sơ đồ hiện tại: phiên bản {{ $showtime->roomLayout->version }}
        </p>
        <input
        id="show-date"
        type="date"
        name="show_date"
        value="{{ old(
            'show_date',
            $showtime->show_date?->format('Y-m-d')
        ) }}"
        class="cinema-input"
        required
    >
        @error('showtime')<div class="rounded-2xl border border-error/30 bg-error/10 p-4 text-sm text-error mb-6">{{ $message }}</div>@enderror

        @if($hasBookingHistory)
            <div class="rounded-2xl border border-warning/30 bg-warning/10 p-5">
                <p class="font-bold app-text">Suất chiếu đã phát sinh đơn đặt vé nên không thể thay đổi phim, phòng, ngày hoặc giờ chiếu.</p>
                <p class="mt-2 text-sm app-muted">Dữ liệu lịch chiếu được giữ nguyên để bảo toàn lịch sử đặt vé.</p>
                <a href="{{ route('admin.showtimes.index') }}" class="btn-secondary mt-5">Quay lại danh sách</a>
            </div>
        @else
            <form method="POST" action="{{ route('admin.showtimes.update', $showtime) }}" class="space-y-6"
            value="{{ old(
                'show_time',
                $showtime->show_time
                    ? \Carbon\Carbon::parse($showtime->show_time)->format('H:i')
                    : ''
            ) }}">
                @csrf
                @method('PUT')
                @include('admin.showtimes._form-fields')
                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.showtimes.index') }}" class="btn-secondary">Hủy</a>
                    <button class="btn-primary" data-showtime-save>Cập nhật</button>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
