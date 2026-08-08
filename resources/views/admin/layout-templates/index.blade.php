@extends('layouts.admin')
@section('title', 'Mẫu sơ đồ phòng')
@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between"><div><h1 class="text-2xl font-bold">Mẫu sơ đồ phòng</h1><p class="app-muted">Thư viện mẫu dùng chung; mỗi lần áp dụng sẽ tạo dữ liệu phòng độc lập.</p></div>
        @can('layout_templates.manage')<a href="{{ route('admin.layout-templates.create') }}" class="btn-primary">Tạo mẫu</a>@endcan
    </div>
    <div class="app-card overflow-hidden"><div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="border-b app-border text-left"><th class="p-4">Mẫu</th><th>Loại phòng</th><th>Kích thước</th><th>Ghế / lần dùng</th><th>Trạng thái</th><th></th></tr></thead><tbody>
    @forelse($templates as $template)<tr class="border-b app-border"><td class="p-4"><div class="font-semibold">{{ $template->name }}</div><div class="app-muted">{{ $template->code }}</div></td><td>{{ $template->room_type ? ($roomTypeNames[$template->room_type] ?? $template->room_type) : 'Mọi loại' }}</td><td>{{ $template->rows }} × {{ $template->columns }}</td><td>{{ $template->seat_count }} / {{ $template->room_layouts_count }}</td><td>{{ $template->status_label }}</td><td class="text-right p-4"><a class="text-indigo-400" href="{{ route('admin.layout-templates.show', $template) }}">Xem</a></td></tr>
    @empty<tr><td colspan="6" class="p-8 text-center app-muted">Chưa có mẫu sơ đồ.</td></tr>@endforelse
    </tbody></table></div></div>{{ $templates->links() }}
</div>
@endsection
