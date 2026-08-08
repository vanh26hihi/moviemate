@extends('layouts.admin')

@section('title', $room->name.' - MovieMate')
@section('page-title', 'Chi tiết phòng chiếu')
@section('suppress-global-validation-summary', '1')

@section('content')
@php
    $published = $room->latestPublishedLayout;
    $publishedSeats = $published?->cells->where('cell_type', 'seat')->pluck('seat')->filter() ?? collect();
@endphp
<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <a href="{{ route('admin.rooms.index') }}" class="mb-3 inline-flex items-center gap-2 text-sm font-bold text-brand-start"><i class="ph ph-arrow-left" aria-hidden="true"></i> {{ __('rooms.actions.back') }}</a>
            <h1 class="text-3xl font-extrabold app-text">{{ $room->name }}</h1>
            <p class="mt-1 app-muted">{{ $room->code }} · {{ $room->cinema->name }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('rooms.update')
                <a href="{{ route('admin.rooms.edit', $room) }}" class="btn-secondary"><i class="ph ph-pencil-simple" aria-hidden="true"></i> {{ __('rooms.actions.edit') }}</a>
            @endcan
            @can('seats.manage')
                @if($room->status === 'active')
                    <a href="{{ route('admin.rooms.layout.show', $room) }}" class="btn-primary"><i class="ph ph-grid-four" aria-hidden="true"></i> {{ __('rooms.actions.layout') }}</a>
                @elseif($published)
                    <a href="{{ route('admin.rooms.layout.preview', ['room' => $room, 'version' => $published->version]) }}" class="btn-secondary"><i class="ph ph-eye" aria-hidden="true"></i> {{ __('rooms.actions.preview') }}</a>
                @endif
            @endcan
            @can('seats.maintenance.view')
                @if($room->status === 'active' && $published)
                    <a href="{{ route('admin.rooms.seat-maintenance.index', $room) }}" class="btn-secondary"><i class="ph ph-wrench" aria-hidden="true"></i> Bảo trì ghế</a>
                @endif
            @endcan
        </div>
    </div>

    <x-validation-summary :errors="$errors" :except="['status']" />

    @error('status')
        <div class="rounded-2xl border border-error/30 bg-error/10 px-4 py-3 text-sm font-bold text-error" role="alert">{{ $message }}</div>
    @enderror

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="cinema-card p-5"><p class="text-sm app-muted">{{ __('rooms.fields.status') }}</p><p class="mt-2"><span class="status-badge {{ $room->status === 'active' ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning' }}">{{ $room->status_label }}</span></p></div>
        <div class="cinema-card p-5"><p class="text-sm app-muted">{{ __('rooms.fields.total_seats') }}</p><p class="mt-1 text-3xl font-extrabold app-text">{{ $publishedSeats->count() }}</p></div>
        <div class="cinema-card p-5"><p class="text-sm app-muted">{{ __('rooms.fields.upcoming_showtimes') }}</p><p class="mt-1 text-3xl font-extrabold app-text">{{ $room->upcoming_showtimes_count }}</p></div>
        <div class="cinema-card p-5"><p class="text-sm app-muted">Tổng lịch sử suất chiếu</p><p class="mt-1 text-3xl font-extrabold app-text">{{ $room->showtimes_count }}</p></div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <section class="cinema-card p-6">
            <h2 class="text-xl font-extrabold app-text">Thông tin phòng</h2>
            <dl class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div><dt class="text-sm app-muted">{{ __('rooms.fields.code') }}</dt><dd class="font-bold app-text">{{ $room->code }}</dd></div>
                <div><dt class="text-sm app-muted">{{ __('rooms.fields.type') }}</dt><dd class="font-bold app-text">{{ $room->room_type }}</dd></div>
                <div><dt class="text-sm app-muted">{{ __('rooms.fields.cinema') }}</dt><dd class="font-bold app-text">{{ $room->cinema->name }}</dd></div>
                <div><dt class="text-sm app-muted">{{ __('rooms.fields.updated_at') }}</dt><dd class="font-bold app-text">{{ $room->updated_at->format('d/m/Y H:i') }}</dd></div>
            </dl>
        </section>

        <section class="cinema-card p-6">
            <h2 class="text-xl font-extrabold app-text">Thống kê sơ đồ ghế</h2>
            @if($published)
                <p class="mt-1 text-sm app-muted">{{ $published->display_name }} · {{ $published->status_label }}</p>
                <dl class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div><dt class="text-sm app-muted">{{ __('rooms.fields.normal_seats') }}</dt><dd class="text-2xl font-extrabold app-text">{{ $publishedSeats->where('type', 'normal')->count() }}</dd></div>
                    <div><dt class="text-sm app-muted">{{ __('rooms.fields.vip_seats') }}</dt><dd class="text-2xl font-extrabold app-text">{{ $publishedSeats->where('type', 'vip')->count() }}</dd></div>
                    <div><dt class="text-sm app-muted">{{ __('rooms.fields.couple_seats') }}</dt><dd class="text-2xl font-extrabold app-text">{{ $publishedSeats->where('type', 'couple')->count() }}</dd></div>
                    <div><dt class="text-sm app-muted">Kích thước</dt><dd class="text-2xl font-extrabold app-text">{{ $published->rows }} × {{ $published->columns }}</dd></div>
                </dl>
            @else
                <p class="mt-5 app-muted">{{ __('rooms.no_layout') }}.</p>
            @endif
            @if($room->draftLayout)
                <p class="mt-4 rounded-xl bg-warning/10 px-3 py-2 text-sm font-bold text-warning">{{ __('rooms.has_draft', ['version' => $room->draftLayout->version]) }}</p>
            @endif
        </section>
    </div>

    @can('rooms.update')
        <section class="cinema-card p-6">
            <h2 class="text-xl font-extrabold app-text">Trạng thái hoạt động</h2>
            <p class="mt-1 app-muted">Ngừng hoạt động không xóa sơ đồ ghế, suất chiếu cũ hoặc lịch sử đặt vé.</p>
            <form action="{{ route('admin.rooms.status.update', $room) }}" method="POST" class="mt-4" onsubmit="return confirm(@js($room->status === 'active' ? __('rooms.confirm.deactivate') : __('rooms.confirm.activate')));">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $room->status === 'active' ? 'inactive' : 'active' }}">
                <button type="submit" class="btn-secondary">{{ $room->status === 'active' ? __('rooms.actions.deactivate') : __('rooms.actions.activate') }}</button>
            </form>
        </section>
    @endcan

    @if(isset($templates) && $templates->isNotEmpty() && auth()->user()->hasPermission('room_layouts.apply_template') && ! $room->draftLayout)
        <section class="cinema-card p-6">
            <h2 class="text-xl font-extrabold app-text">Tạo phiên bản từ mẫu</h2>
            <p class="mt-1 app-muted">Tạo một bản nháp độc lập để kiểm tra trước khi phát hành. Ghế lịch sử không bị đổi mã hay xóa.</p>
            <form method="POST" action="{{ route('admin.rooms.layout.apply-template', $room) }}" class="mt-4 grid gap-4 md:grid-cols-3">@csrf
                <select name="template_id" class="cinema-input" required><option value="">Chọn mẫu</option>@foreach($templates as $template)<option value="{{ $template->id }}">{{ $template->name }} · {{ $template->rows }}×{{ $template->columns }}</option>@endforeach</select>
                <input name="layout_name" class="cinema-input" required minlength="5" placeholder="Tên phiên bản có ý nghĩa">
                <input name="change_note" class="cinema-input" placeholder="Mục đích thay đổi">
                <button class="btn-primary md:col-span-3">Tạo bản nháp từ mẫu</button>
            </form>
        </section>
    @endif
</div>
@endsection
