@extends('layouts.admin')
@section('title', 'Chỉnh sửa suất chiếu - Quản trị MovieMate')
@section('page-title', 'Chỉnh sửa suất chiếu')
@section('suppress-global-validation-summary', '1')

@section('content')
<div class="max-w-5xl">
    <div class="cinema-card p-6 sm:p-8">
        <h1 class="text-2xl font-extrabold app-text mb-2">Cập nhật suất chiếu</h1>
        <p class="app-muted mb-6">
            Chi nhánh: {{ $showtime->cinema?->name ?? $cinema?->name ?? '—' }} · Định dạng trình chiếu: {{ $showtime->presentationFormat?->code ?? 'Chưa gán' }} · Sơ đồ hiện tại: phiên bản {{ $showtime->roomLayout->version }}
        </p>

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
