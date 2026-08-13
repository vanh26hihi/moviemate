@extends('layouts.admin')
@section('title', 'Mẫu sơ đồ phòng')
@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div class="min-w-0"><h1 class="break-words text-2xl font-bold">Mẫu lưới bố trí logic</h1><p class="app-muted">Thư viện mẫu sơ đồ dùng chung; mỗi lần áp dụng sẽ tạo một RoomLayout độc lập.</p></div>
        @can('layout_templates.manage')<a href="{{ route('admin.layout-templates.create') }}" class="btn-primary">Tạo mẫu</a>@endcan
    </div>
    <div class="app-card overflow-hidden"><div class="overflow-x-auto"><table class="min-w-[760px] w-full text-sm"><thead><tr class="border-b app-border text-left"><th class="p-4">Mẫu</th><th>Loại phòng</th><th>Lưới logic</th><th>Vị trí SEAT / lần áp dụng</th><th>Trạng thái</th><th></th></tr></thead><tbody>
    @forelse($templates as $template)<tr class="border-b app-border"><td class="p-4"><div class="break-words font-semibold">{{ $template->name }}</div><div class="app-muted">{{ $template->code }}</div></td><td>{{ $template->room_type ? ($roomTypeNames[$template->room_type] ?? $template->room_type) : 'Mọi loại' }}</td><td>{{ $template->rows }} hàng × {{ $template->columns }} cột</td><td>{{ $template->seat_count }} / {{ $template->room_layouts_count }}</td><td>{{ $template->status_label }}</td><td class="text-right p-4"><a class="text-indigo-400" href="{{ route('admin.layout-templates.show', $template) }}">Xem</a></td></tr>
    @empty<tr><td colspan="6" class="p-8 text-center app-muted">Chưa có mẫu sơ đồ.</td></tr>@endforelse
    </tbody></table></div></div>{{ $templates->links() }}
</div>
@endsection
