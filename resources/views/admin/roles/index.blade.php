@extends('layouts.admin')

@section('title', 'Vai trò và quyền - MovieMate')
@section('page-title', 'Vai trò và quyền')

@section('content')
<div class="admin-page-header"><div><h1 class="admin-page-title">Vai trò hệ thống</h1><p class="admin-page-subtitle">Quản trị viên và Khách hàng được khóa; chỉ Quản lý và Nhân viên có thể chỉnh quyền.</p></div></div>
<div class="grid gap-5 md:grid-cols-2">
    @foreach($roles as $role)
    <section class="app-card border app-border rounded-2xl p-6">
        <div class="flex justify-between gap-4"><div><h2 class="text-lg font-bold">{{ $role->display_name }}</h2><p class="text-sm app-muted">{{ $role->description }}</p></div><span class="text-sm app-muted">{{ $role->users_count }} tài khoản</span></div>
        <div class="flex flex-wrap gap-2 my-5">
            @forelse($role->permissions as $permission)<span class="px-2 py-1 rounded-lg app-card-soft border app-border text-xs">{{ $permission->display_name }}</span>@empty<span class="app-muted text-sm">Không có quyền quản trị</span>@endforelse
        </div>
        @if($role->isEditable())
            @can('roles.manage')<a href="{{ route('admin.roles.edit', $role) }}" class="admin-btn-warning">Chỉnh quyền</a>@endcan
        @else
            <span class="text-xs app-muted"><i class="ph ph-lock"></i> Vai trò hệ thống được bảo vệ</span>
        @endif
    </section>
    @endforeach
</div>
@endsection
