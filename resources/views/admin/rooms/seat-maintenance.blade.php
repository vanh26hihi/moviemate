@extends('layouts.admin')

@section('title', 'Bảo trì ghế '.$room->code.' - MovieMate')
@section('page-title', 'Bảo trì ghế')

@section('content')
<div class="space-y-6">
    <header class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div>
            <a href="{{ route('admin.rooms.index') }}" class="mb-3 inline-flex items-center gap-2 text-sm font-bold text-brand-start"><i class="ph ph-arrow-left"></i>Quay lại danh sách phòng</a>
            <h1 class="admin-page-title">Bảo trì ghế</h1>
            <p class="admin-page-subtitle">Cập nhật tình trạng vận hành của từng ghế. Việc thay đổi vị trí, mã ghế, loại ghế và cấu trúc ghế đôi được thực hiện trong phần Thiết kế sơ đồ ghế.</p>
            <p class="mt-2 font-bold app-text">{{ $room->code }} · {{ $room->name }} · Sơ đồ hiện hành v{{ $layout->version }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('rooms.view')<a class="btn-secondary" href="{{ route('admin.rooms.show', $room) }}"><i class="ph ph-eye"></i>Xem phòng</a>@endcan
            @can('seats.manage')<a class="btn-secondary" href="{{ route('admin.rooms.layout.show', $room) }}"><i class="ph ph-grid-four"></i>Thiết kế sơ đồ ghế</a>@endcan
        </div>
    </header>

    <section class="rounded-2xl border border-warning/30 bg-warning/10 px-5 py-4 text-sm app-text">
        <strong>Phạm vi áp dụng toàn cục:</strong> trạng thái ghế ảnh hưởng đến các lượt đặt mới sử dụng ghế hiện tại. Vé đã phát hành, lịch sử đặt vé, suất chiếu cũ và phiên bản sơ đồ cũ không bị xóa hoặc di chuyển.
        @if($historicalOnlyCount > 0)<span class="mt-1 block app-muted">Có {{ $historicalOnlyCount }} ghế chỉ thuộc phiên bản sơ đồ cũ; các ghế này không thể sửa tại đây.</span>@endif
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5" aria-label="Tóm tắt tình trạng ghế">
        @foreach([
            ['Tổng số ghế', $summary['total'], 'ph-armchair'],
            ['Đang sử dụng', $summary['active'], 'ph-check-circle'],
            ['Bảo trì', $summary['maintenance'], 'ph-wrench'],
            ['Ngừng sử dụng', $summary['inactive'], 'ph-prohibit'],
            ['Đang được bảo vệ', $summary['protected'], 'ph-shield-check'],
        ] as [$label, $value, $icon])
            <div class="cinema-card p-5"><i class="ph {{ $icon }} text-xl text-brand-start"></i><p class="mt-3 text-sm app-muted">{{ $label }}</p><p class="mt-1 text-2xl font-extrabold app-text">{{ $value }}</p></div>
        @endforeach
    </section>

    <form method="GET" class="cinema-card grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-4">
        <label class="text-sm font-bold">Mã ghế<input class="cinema-input mt-1" name="seat_code" value="{{ $filters['seat_code'] ?? '' }}"></label>
        <label class="text-sm font-bold">Hàng<input class="cinema-input mt-1" name="row" value="{{ $filters['row'] ?? '' }}"></label>
        <label class="text-sm font-bold">Loại ghế<select class="cinema-input mt-1" name="type"><option value="">Tất cả</option>@foreach(\App\Models\Seat::TYPES as $type)<option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ \App\Support\StatusLabel::for('seat_type', $type) }}</option>@endforeach</select></label>
        <label class="text-sm font-bold">Trạng thái<select class="cinema-input mt-1" name="status"><option value="">Tất cả</option>@foreach(\App\Models\Seat::OPERATIONAL_STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ \App\Support\StatusLabel::for('seat', $status) }}</option>@endforeach</select></label>
        <label class="text-sm font-bold">Ghế đôi<select class="cinema-input mt-1" name="couple"><option value="">Tất cả</option><option value="yes" @selected(($filters['couple'] ?? '') === 'yes')>Chỉ ghế đôi</option><option value="no" @selected(($filters['couple'] ?? '') === 'no')>Không phải ghế đôi</option></select></label>
        <label class="text-sm font-bold">Đang giữ chỗ<select class="cinema-input mt-1" name="active_hold"><option value="">Tất cả</option><option value="yes" @selected(($filters['active_hold'] ?? '') === 'yes')>Có</option><option value="no" @selected(($filters['active_hold'] ?? '') === 'no')>Không</option></select></label>
        <label class="text-sm font-bold">Vé suất tương lai<select class="cinema-input mt-1" name="future_ticket"><option value="">Tất cả</option><option value="yes" @selected(($filters['future_ticket'] ?? '') === 'yes')>Có</option><option value="no" @selected(($filters['future_ticket'] ?? '') === 'no')>Không</option></select></label>
        <label class="text-sm font-bold">Sắp xếp<select class="cinema-input mt-1" name="sort"><option value="seat_code">Mã ghế</option><option value="row" @selected(($filters['sort'] ?? '') === 'row')>Hàng</option><option value="type" @selected(($filters['sort'] ?? '') === 'type')>Loại</option><option value="status" @selected(($filters['sort'] ?? '') === 'status')>Trạng thái</option><option value="updated_at" @selected(($filters['sort'] ?? '') === 'updated_at')>Cập nhật gần nhất</option></select></label>
        <div class="flex items-end gap-2 xl:col-span-4"><button class="btn-primary" type="submit"><i class="ph ph-funnel"></i>Lọc</button><a class="btn-secondary" href="{{ route('admin.rooms.seat-maintenance.index', $room) }}">Xóa lọc</a></div>
    </form>

    @can('seats.maintenance.update')
        <form id="bulk-seat-maintenance" method="POST" action="{{ route('admin.rooms.seat-maintenance.bulk', $room) }}" class="cinema-card flex flex-col gap-3 p-5 sm:flex-row sm:items-end" onsubmit="return confirm('Áp dụng trạng thái đã chọn cho toàn bộ đơn vị ghế?');">
            @csrf
            <label class="text-sm font-bold sm:min-w-56">Thao tác hàng loạt<select class="cinema-input mt-1" name="status" required><option value="maintenance">Chuyển sang bảo trì</option><option value="active">Kích hoạt lại</option><option value="inactive">Ngừng sử dụng</option></select></label>
            <button class="btn-primary" type="submit"><i class="ph ph-check-square"></i>Áp dụng cho ghế đã chọn</button>
            <span class="text-sm app-muted">Tối đa 50 đơn vị; một ghế không an toàn sẽ hủy toàn bộ thao tác.</span>
        </form>
    @endcan

    <section class="cinema-card overflow-hidden">
        <div class="overflow-x-auto"><table class="admin-table min-w-[88rem]"><thead><tr><th class="w-12">Chọn</th><th>Mã ghế</th><th>Hàng</th><th>Loại ghế</th><th>Trạng thái</th><th>Ghế đôi</th><th>Tình trạng bảo vệ</th><th>Phiên bản sơ đồ</th><th>Cập nhật cuối</th><th>Thao tác</th></tr></thead><tbody>
            @forelse($units as $unit)<tr>
                <td>@can('seats.maintenance.update')@if($unit['is_valid'] && $unit['status'] !== 'retired')<input form="bulk-seat-maintenance" type="checkbox" name="seat_ids[]" value="{{ $unit['unit_id'] }}" aria-label="Chọn {{ $unit['label'] }}">@endif @endcan</td>
                <td class="font-extrabold text-brand-start">{{ $unit['label'] }}</td><td>{{ $unit['row'] }}</td>
                <td><span class="status-badge bg-ai-start/10 text-ai-start">{{ \App\Support\StatusLabel::for('seat_type', $unit['type']) }}</span></td>
                <td><span class="status-badge {{ $unit['status'] === 'active' ? 'bg-success/10 text-success' : ($unit['status'] === 'maintenance' ? 'bg-warning/10 text-warning' : 'bg-error/10 text-error') }}">{{ \App\Support\StatusLabel::for('seat', $unit['status']) }}</span></td>
                <td>{{ $unit['is_couple'] ? ($unit['is_valid'] ? 'Một đơn vị gồm hai vị trí' : 'Dữ liệu cặp không hợp lệ') : 'Không' }}</td>
                <td>@if($unit['active_hold'])<span class="status-badge bg-warning/10 text-warning">Đang giữ chỗ</span>@endif @if($unit['future_sold'])<span class="status-badge bg-error/10 text-error">Đã bán cho suất sắp tới</span>@endif @if($unit['issued_ticket'])<span class="status-badge bg-error/10 text-error">Có vé đã phát hành</span>@endif @if(!$unit['protected'])<span class="status-badge bg-success/10 text-success">Không bị ràng buộc</span>@endif</td>
                <td>v{{ $unit['layout_version'] }}</td><td>{{ $unit['updated_at']?->format('d/m/Y H:i:s') ?? '—' }}</td>
                <td>@can('seats.maintenance.update')
                    @if($unit['is_valid'] && $unit['status'] !== 'retired')<form method="POST" action="{{ route('admin.rooms.seat-maintenance.update', ['room' => $room, 'seat' => $unit['unit_id']]) }}" class="flex min-w-64 gap-2" onsubmit="return confirm('Cập nhật trạng thái vận hành của {{ $unit['label'] }}?');">@csrf @method('PATCH')<select class="cinema-input !py-2 text-xs" name="status">@foreach(\App\Models\Seat::OPERATIONAL_STATUSES as $status)<option value="{{ $status }}" @selected($unit['status'] === $status)>{{ \App\Support\StatusLabel::for('seat', $status) }}</option>@endforeach</select><button class="btn-secondary !px-3 !py-2 text-xs" type="submit">Lưu</button></form>
                    @else<span class="text-xs text-error">Chỉ đọc · sửa cấu trúc trong trình thiết kế</span>@endif
                @else<span class="text-xs app-muted">Chỉ xem</span>@endcan</td>
            </tr>@empty<tr><td colspan="10" class="py-12 text-center app-muted">Không có đơn vị ghế phù hợp.</td></tr>@endforelse
        </tbody></table></div>
        @if($units->hasPages())<div class="border-t app-border p-5">{{ $units->links() }}</div>@endif
    </section>
</div>
@endsection
