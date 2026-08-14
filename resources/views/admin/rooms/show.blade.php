@extends('layouts.admin')

@section('title', $room->name.' - MovieMate')
@section('page-title', 'Chi tiết phòng chiếu')
@section('suppress-global-validation-summary', '1')

@section('content')
@php
    $published = $room->latestPublishedLayout;
    $physicalSeatCount = $published?->cells->where('cell_type', 'seat')->count() ?? 0;
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
                    <a href="{{ route('admin.rooms.seat-maintenance.index', $room) }}" class="btn-secondary"><i class="ph ph-wrench" aria-hidden="true"></i> Tình trạng ghế & bảo trì</a>
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
        <div class="cinema-card p-5"><p class="text-sm app-muted">{{ __('rooms.fields.physical_seat_count') }}</p><p class="mt-1 text-3xl font-extrabold app-text">{{ $physicalSeatCount }}</p><p class="mt-1 text-xs app-muted">Vị trí ghế trong sơ đồ đã phát hành</p></div>
        <div class="cinema-card p-5"><p class="text-sm app-muted">{{ __('rooms.fields.upcoming_showtimes') }}</p><p class="mt-1 text-3xl font-extrabold app-text">{{ $room->upcoming_showtimes_count }}</p></div>
        <div class="cinema-card p-5"><p class="text-sm app-muted">Tổng lịch sử suất chiếu</p><p class="mt-1 text-3xl font-extrabold app-text">{{ $room->showtimes_count }}</p></div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <section class="cinema-card overflow-hidden xl:col-span-2" aria-labelledby="room-showtimes-title">
            <div class="border-b app-border p-6">
                <p class="text-xs font-black uppercase tracking-[0.18em] text-brand-start">Handoff vận hành</p>
                <h2 id="room-showtimes-title" class="mt-2 text-xl font-extrabold app-text">Lịch chiếu của phòng</h2>
                <p class="mt-1 text-sm app-muted">Tối đa 8 suất đang hoạt động sắp tới; mở từng suất để xem đúng ngữ cảnh vận hành.</p>
            </div>
            @if($upcomingShowtimes->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="admin-table min-w-[42rem]">
                        <thead><tr><th scope="col">Bắt đầu</th><th scope="col">Phim</th><th scope="col">Định dạng</th><th scope="col">Vòng đời</th><th scope="col">Tác vụ</th></tr></thead>
                        <tbody>
                            @foreach($upcomingShowtimes as $showtime)
                                @php($snapshot = $showtime->operational_lifecycle)
                                <tr>
                                    <td class="whitespace-nowrap font-bold app-text">{{ $snapshot['starts_at']->format('d/m/Y H:i') }}</td>
                                    <td class="font-bold app-text">{{ $showtime->movie->title }}</td>
                                    <td>{{ $showtime->presentationFormat?->name ?? '—' }}</td>
                                    <td><span class="status-badge bg-brand-start/10 text-brand-start">{{ $snapshot['label'] }}</span></td>
                                    <td><a class="font-bold text-brand-start" href="{{ route('admin.showtimes.show', $showtime) }}">Xem chi tiết suất chiếu</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="p-6 app-muted">Phòng chưa có suất chiếu đang hoạt động sắp tới.</p>
            @endif
        </section>

        <section class="cinema-card p-6" aria-labelledby="room-seat-operations-title">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-brand-start">Ghế vật lý</p>
            <h2 id="room-seat-operations-title" class="mt-2 text-xl font-extrabold app-text">Tình trạng ghế & bảo trì</h2>
            <p class="mt-1 text-sm app-muted">Bảo trì là trạng thái vật lý; sự cố là lịch sử vận hành có liên kết riêng. Ô BLOCKED không được tính là sự cố.</p>
            @can('seats.maintenance.view')
                @if($room->status === 'active' && $published)
                    <a href="{{ route('admin.rooms.seat-maintenance.index', $room) }}" class="btn-secondary mt-4"><i class="ph ph-wrench" aria-hidden="true"></i> Mở tình trạng ghế & bảo trì</a>
                @endif
                <div class="mt-5 border-t app-border pt-4">
                    <p class="font-extrabold app-text">{{ $openIncidentsCount }} sự cố đang mở</p>
                    @forelse($openIncidents as $incident)
                        <a data-open-seat-incident class="mt-3 block rounded-xl border app-border p-3 hover:border-brand-start" href="{{ route('admin.rooms.seat-incidents.show', [$room, $incident]) }}">
                            <span class="block font-bold text-brand-start">Sự cố #{{ $incident->id }}</span>
                            <span class="mt-1 block text-sm app-muted">{{ match($incident->reason) { 'seat_broken' => 'Ghế hỏng', 'maintenance_required' => 'Cần bảo trì', 'safety_issue' => 'Vấn đề an toàn', default => 'Lý do khác' } }} · {{ $incident->unresolved_impacts_count }} ảnh hưởng chưa xử lý</span>
                        </a>
                    @empty
                        <p class="mt-3 text-sm app-muted">Không có sự cố ghế đang mở.</p>
                    @endforelse
                    @if($openIncidentsCount > $openIncidents->count())
                        <p class="mt-3 text-xs app-muted">Chỉ hiển thị 5 sự cố mới nhất. Mở trang bảo trì để xem đầy đủ lịch sử.</p>
                    @endif
                </div>
            @endcan
        </section>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <section class="cinema-card p-6" aria-labelledby="physical-room-title">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-brand-start">Thông tin vật lý</p>
            <h2 id="physical-room-title" class="mt-2 text-xl font-extrabold app-text">Không gian phòng chiếu</h2>
            <p class="mt-1 text-sm app-muted">Kích thước mặt bằng chữ nhật phục vụ quản lý hành chính, tách biệt với lưới bố trí logic.</p>
            <dl class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div><dt class="text-sm app-muted">{{ __('rooms.fields.code') }}</dt><dd class="font-bold app-text">{{ $room->code }}</dd></div>
                <div><dt class="text-sm app-muted">{{ __('rooms.fields.type') }}</dt><dd class="font-bold app-text">{{ $room->room_type_label }}</dd></div>
                <div><dt class="text-sm app-muted">{{ __('rooms.fields.cinema') }}</dt><dd class="font-bold app-text">{{ $room->cinema->name }}</dd></div>
                <div><dt class="text-sm app-muted">Kích thước phòng</dt><dd class="font-bold app-text">{{ $room->hasCompletePhysicalDimensions() ? $room->formattedWidthMeters().' m × '.$room->formattedLengthMeters().' m' : 'Chưa cấu hình' }}</dd></div>
                <div><dt class="text-sm app-muted">Diện tích mặt bằng</dt><dd class="font-bold app-text">{{ $room->formattedAreaM2() !== null ? $room->formattedAreaM2().' m²' : 'Chưa cấu hình' }}</dd></div>
                <div><dt class="text-sm app-muted">{{ __('rooms.fields.updated_at') }}</dt><dd class="font-bold app-text">{{ $room->updated_at->format('d/m/Y H:i') }}</dd></div>
            </dl>
        </section>

        <section class="cinema-card p-6" aria-labelledby="logical-layout-title">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-brand-start">Sơ đồ bố trí logic</p>
            <h2 id="logical-layout-title" class="mt-2 text-xl font-extrabold app-text">Phiên bản sơ đồ đã phát hành</h2>
            @if($published)
                <p class="mt-1 text-sm app-muted">Cấu trúc đã phát hành là chỉ đọc; suất chiếu tiếp tục giữ đúng phiên bản sơ đồ đã được gán.</p>
                <dl class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <div><dt class="text-sm app-muted">Phiên bản sơ đồ</dt><dd class="text-2xl font-extrabold app-text">{{ $published->version }}</dd></div>
                    <div><dt class="text-sm app-muted">Trạng thái sơ đồ</dt><dd class="mt-1"><span class="status-badge bg-success/10 text-success">{{ $published->status_label }}</span></dd></div>
                    <div><dt class="text-sm app-muted">Lưới logic</dt><dd class="text-2xl font-extrabold app-text">{{ $published->rows }} hàng × {{ $published->columns }} cột</dd></div>
                    <div><dt class="text-sm app-muted">Vị trí màn hình</dt><dd class="text-2xl font-extrabold app-text">{{ $published->screen_position === 'top' ? 'Phía trên' : 'Phía dưới' }}</dd></div>
                    <div><dt class="text-sm app-muted">{{ __('rooms.fields.normal_seats') }}</dt><dd class="text-2xl font-extrabold app-text">{{ $publishedSeats->where('type', 'normal')->count() }}</dd></div>
                    <div><dt class="text-sm app-muted">{{ __('rooms.fields.vip_seats') }}</dt><dd class="text-2xl font-extrabold app-text">{{ $publishedSeats->where('type', 'vip')->count() }}</dd></div>
                    <div><dt class="text-sm app-muted">{{ __('rooms.fields.couple_seats') }}</dt><dd class="text-2xl font-extrabold app-text">{{ $publishedSeats->where('type', 'couple')->count() }}</dd></div>
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
            <p class="mt-1 app-muted">Áp dụng mẫu sẽ tạo một sơ đồ phòng độc lập để kiểm tra trước khi phát hành. Thay đổi mẫu sau này không làm thay đổi sơ đồ đã áp dụng; ghế lịch sử không bị đổi mã hay xóa.</p>
            <form method="POST" action="{{ route('admin.rooms.layout.apply-template', $room) }}" class="mt-4 grid gap-4 md:grid-cols-3">@csrf
                <select name="template_id" class="cinema-input" required><option value="">Chọn mẫu</option>@foreach($templates as $template)<option value="{{ $template->id }}">{{ $template->name }} · lưới {{ $template->rows }} hàng × {{ $template->columns }} cột logic</option>@endforeach</select>
                <input name="layout_name" class="cinema-input" required minlength="5" placeholder="Tên phiên bản có ý nghĩa">
                <input name="change_note" class="cinema-input" placeholder="Mục đích thay đổi">
                <button class="btn-primary md:col-span-3">Tạo bản nháp từ mẫu</button>
            </form>
        </section>
    @endif
</div>
@endsection
