@extends('layouts.admin')

@section('title', 'Chi nhánh - MovieMate')
@section('page-title', 'Chi nhánh')

@section('content')
<div class="admin-page-header">
    <div><h1 class="admin-page-title">Hệ thống chi nhánh</h1><p class="admin-page-subtitle">Các chi nhánh thuộc cùng một hệ thống MovieMate.</p></div>
    @can('cinemas.manage')<a href="{{ route('admin.cinemas.create') }}" class="admin-btn-primary"><i class="ph ph-plus"></i> Thêm chi nhánh</a>@endcan
</div>
<div class="grid gap-5 lg:grid-cols-2">
    @forelse($cinemas as $cinema)
        <article class="app-card rounded-2xl border app-border p-6">
            <div class="flex items-start justify-between gap-4">
                <div><p class="text-xs font-bold uppercase tracking-wider text-brand-start">{{ $cinema->code }}</p><h2 class="mt-1 text-xl font-extrabold app-text">{{ $cinema->name }}</h2><p class="mt-2 text-sm app-muted">{{ $cinema->address }}</p></div>
                <span class="rounded-full px-3 py-1 text-xs font-bold {{ $cinema->status === 'active' ? 'bg-success/10 text-success' : 'bg-error/10 text-error' }}">{{ $cinema->status === 'active' ? 'Đang hoạt động' : 'Ngừng hoạt động' }}</span>
            </div>
            <div class="mt-5 grid grid-cols-3 gap-3 text-center"><div><strong class="block app-text">{{ $cinema->rooms_count }}</strong><span class="text-xs app-muted">Phòng</span></div><div><strong class="block app-text">{{ $cinema->active_rooms_count }}</strong><span class="text-xs app-muted">Phòng mở</span></div><div><strong class="block app-text">{{ $cinema->active_assignments_count }}</strong><span class="text-xs app-muted">Nhân sự</span></div></div>
            <a href="{{ route('admin.cinemas.show', $cinema) }}" class="admin-btn-secondary mt-5">Xem chi tiết</a>
        </article>
    @empty
        <x-empty-state title="Chưa có chi nhánh" description="Tạo chi nhánh đầu tiên để bắt đầu cấu hình vận hành." />
    @endforelse
</div>
<div class="mt-6">{{ $cinemas->links() }}</div>
@endsection
