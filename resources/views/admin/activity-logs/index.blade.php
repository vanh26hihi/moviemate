@extends('layouts.admin')

@section('title', 'Nhật ký hoạt động - MovieMate')
@section('page-title', 'Nhật ký hoạt động')

@section('content')
<div class="space-y-6">
    <header>
        <h1 class="text-2xl font-extrabold app-heading">Nhật ký hoạt động</h1>
        <p class="mt-2 app-muted">Lịch sử chỉ đọc của các thao tác quản trị nhạy cảm. Nhật ký không thể sửa hoặc xóa.</p>
    </header>

    <form method="GET" action="{{ route('admin.activity-logs.index') }}" class="admin-toolbar grid gap-3 md:grid-cols-2 xl:grid-cols-4" aria-label="Bộ lọc nhật ký hoạt động">
        <label class="cinema-label">Mã người thực hiện
            <input class="cinema-input mt-1" type="number" min="1" name="actor" value="{{ $filters['actor'] ?? '' }}" placeholder="Ví dụ: 12">
        </label>
        <label class="cinema-label">Hành động
            <select class="cinema-input mt-1" name="action">
                <option value="">Tất cả hành động</option>
                @foreach($actions as $action)<option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>@endforeach
            </select>
        </label>
        <label class="cinema-label">Loại đối tượng
            <select class="cinema-input mt-1" name="subject_type">
                <option value="">Tất cả đối tượng</option>
                @foreach($subjectTypes as $type)<option value="{{ $type }}" @selected(($filters['subject_type'] ?? '') === $type)>{{ class_basename($type) }}</option>@endforeach
            </select>
        </label>
        <label class="cinema-label">Tên route
            <select class="cinema-input mt-1" name="route">
                <option value="">Tất cả route</option>
                @foreach($routeNames as $routeName)<option value="{{ $routeName }}" @selected(($filters['route'] ?? '') === $routeName)>{{ $routeName }}</option>@endforeach
            </select>
        </label>
        <label class="cinema-label">Từ ngày<input class="cinema-input mt-1" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
        <label class="cinema-label">Đến ngày<input class="cinema-input mt-1" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
        <label class="cinema-label md:col-span-2">Mã yêu cầu
            <input class="cinema-input mt-1 font-mono text-xs" name="request_id" maxlength="100" value="{{ $filters['request_id'] ?? '' }}" placeholder="Request ID chính xác">
        </label>
        <div class="flex flex-wrap items-end gap-2 md:col-span-2 xl:col-span-4">
            <button type="submit" class="btn-primary"><i class="ph ph-funnel" aria-hidden="true"></i>Lọc</button>
            <a href="{{ route('admin.activity-logs.index') }}" class="btn-secondary">Xóa bộ lọc</a>
        </div>
    </form>

    <div class="cinema-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[64rem] text-left text-sm">
                <thead class="app-table-head text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3">Thời gian</th><th class="px-4 py-3">Người thực hiện</th><th class="px-4 py-3">Hành động</th>
                        <th class="px-4 py-3">Đối tượng</th><th class="px-4 py-3">Route</th><th class="px-4 py-3">Request ID</th><th class="px-4 py-3 text-right">Chi tiết</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border-color)]">
                    @forelse($logs as $log)
                        <tr class="app-table-row">
                            <td class="px-4 py-3 whitespace-nowrap">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                            <td class="px-4 py-3">{{ $log->actor_user_id ? 'Người dùng #'.$log->actor_user_id : 'Hệ thống' }}<span class="block text-xs app-muted">{{ $log->actor_role_snapshot ?? 'không xác định' }}</span></td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $log->action }}</td>
                            <td class="px-4 py-3">{{ $log->subject_label ?? class_basename($log->subject_type) }}<span class="block text-xs app-muted">{{ class_basename($log->subject_type) }} #{{ $log->subject_id ?? '—' }}</span></td>
                            <td class="px-4 py-3"><span class="font-mono text-xs">{{ $log->method }} {{ $log->route_name ?? '—' }}</span></td>
                            <td class="max-w-52 truncate px-4 py-3 font-mono text-xs" title="{{ $log->request_id }}">{{ $log->request_id ?? '—' }}</td>
                            <td class="px-4 py-3 text-right"><a class="btn-secondary !px-3 !py-2 text-xs" href="{{ route('admin.activity-logs.show', $log) }}">Xem</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-10 text-center app-muted">Chưa có hoạt động phù hợp với bộ lọc.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())<div class="border-t app-border px-5 py-4">{{ $logs->links() }}</div>@endif
    </div>
</div>
@endsection
