@extends('layouts.admin')

@section('title', 'Chi tiết nhật ký hoạt động - MovieMate')
@section('page-title', 'Chi tiết nhật ký hoạt động')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div><h1 class="text-2xl font-extrabold app-heading">Sự kiện #{{ $activityLog->id }}</h1><p class="mt-2 app-muted">Bản ghi chỉ đọc, đã lọc dữ liệu nhạy cảm trước khi lưu.</p></div>
        <a href="{{ route('admin.activity-logs.index') }}" class="btn-secondary"><i class="ph ph-arrow-left" aria-hidden="true"></i>Quay lại nhật ký</a>
    </div>

    <dl class="cinema-card grid gap-5 p-5 sm:grid-cols-2 xl:grid-cols-3">
        @foreach([
            'Thời gian' => $activityLog->created_at?->format('d/m/Y H:i:s'),
            'Người thực hiện' => $activityLog->actor_user_id ? 'Người dùng #'.$activityLog->actor_user_id.' ('.($activityLog->actor_role_snapshot ?? 'không xác định').')' : 'Hệ thống',
            'Hành động' => $activityLog->action,
            'Đối tượng' => ($activityLog->subject_label ?? class_basename($activityLog->subject_type)).' #'.($activityLog->subject_id ?? '—'),
            'Route' => trim(($activityLog->method ?? '').' '.($activityLog->route_name ?? '—')),
            'Request ID' => $activityLog->request_id ?? '—',
            'Dấu vết mạng an toàn' => $activityLog->safe_ip_hash ?? '—',
            'Thiết bị' => $activityLog->user_agent_summary ?? '—',
        ] as $label => $value)
            <div class="min-w-0"><dt class="text-xs font-bold uppercase tracking-wider app-muted">{{ $label }}</dt><dd class="mt-1 safe-break font-mono text-sm app-text">{{ $value }}</dd></div>
        @endforeach
    </dl>

    <div class="grid gap-5 xl:grid-cols-3">
        @foreach(['Dữ liệu trước' => $activityLog->before_data, 'Dữ liệu sau' => $activityLog->after_data, 'Ngữ cảnh' => $activityLog->context] as $label => $data)
            <section class="cinema-card min-w-0 p-5"><h2 class="font-bold app-heading">{{ $label }}</h2><pre class="mt-3 max-h-96 overflow-auto whitespace-pre-wrap break-words rounded-xl app-bg-soft p-4 text-xs">{{ $data ? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'Không có dữ liệu.' }}</pre></section>
        @endforeach
    </div>
</div>
@endsection
