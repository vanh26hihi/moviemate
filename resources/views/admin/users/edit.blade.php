@extends('layouts.admin')

@section('title', 'Quản lý tài khoản - MovieMate')
@section('page-title', 'Quản lý tài khoản')

@section('content')
<div class="admin-page-header">
    <div><h1 class="admin-page-title">{{ $managedUser->name }}</h1><p class="admin-page-subtitle">{{ $managedUser->email }}</p></div>
    <a href="{{ route('admin.users.index') }}" class="admin-btn-secondary">Quay lại</a>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    @can('users.manage-role')
    <form method="POST" action="{{ route('admin.users.role.update', $managedUser) }}" class="app-card border app-border rounded-2xl p-6">@csrf @method('PATCH')
        <h2 class="font-bold text-lg mb-4">Vai trò</h2>
        <label class="block text-sm app-muted mb-2" for="role">Vai trò hệ thống</label>
        <select id="role" name="role" class="app-input border app-border rounded-xl px-4 py-3 w-full" required>
            @foreach($roles as $role)<option value="{{ $role->slug }}" @selected(old('role', $managedUser->role?->slug) === $role->slug)>{{ $role->display_name }}</option>@endforeach
        </select>
        <button class="admin-btn-primary mt-5" type="submit">Cập nhật vai trò</button>
    </form>
    @endcan

    @can('users.manage-status')
    <form method="POST" action="{{ route('admin.users.status.update', $managedUser) }}" class="app-card border app-border rounded-2xl p-6">@csrf @method('PATCH')
        <h2 class="font-bold text-lg mb-4">Trạng thái</h2>
        <label class="block text-sm app-muted mb-2" for="status">Trạng thái tài khoản</label>
        <select id="status" name="status" class="app-input border app-border rounded-xl px-4 py-3 w-full" required>
            <option value="active" @selected(old('status', $managedUser->status) === 'active')>{{ \App\Support\StatusLabel::for('user', 'active') }}</option>
            <option value="inactive" @selected(old('status', $managedUser->status) === 'inactive')>{{ \App\Support\StatusLabel::for('user', 'inactive') }}</option>
        </select>
        <p class="text-xs app-muted mt-3">Không thể ngừng hoạt động tài khoản quản trị viên cuối cùng.</p>
        <button class="admin-btn-primary mt-5" type="submit">Cập nhật trạng thái</button>
    </form>
    @endcan
</div>

@if($managedUser->hasRole(['manager', 'staff']) && auth()->user()->can('cinema_assignments.manage'))
<section class="app-card mt-6 rounded-2xl border app-border p-6">
    <h2 class="text-lg font-bold app-text">Phân công chi nhánh</h2>
    <form method="POST" action="{{ route('admin.users.cinema-assignments.store', $managedUser) }}" class="mt-4 flex flex-col gap-3 sm:flex-row">@csrf
        <select name="cinema_id" class="app-input flex-1 rounded-xl border app-border px-4 py-3" required>
            @foreach($assignableCinemas as $cinema)<option value="{{ $cinema->id }}">{{ $cinema->name }}</option>@endforeach
        </select>
        <button class="admin-btn-primary" type="submit">Phân công</button>
    </form>
    <div class="mt-5 divide-y app-border">
        @forelse($managedUser->cinemaAssignments->where('status', 'active') as $assignment)
            <div class="flex items-center justify-between gap-4 py-3"><div><strong class="app-text">{{ $assignment->cinema->name }}</strong><p class="text-xs app-muted">Từ {{ $assignment->assigned_at?->format('d/m/Y H:i') }}</p></div><form method="POST" action="{{ route('admin.users.cinema-assignments.destroy', [$managedUser, $assignment]) }}">@csrf @method('DELETE')<button class="admin-btn-secondary" type="submit">Thu hồi</button></form></div>
        @empty<p class="py-4 app-muted">Chưa có phân công đang hoạt động.</p>@endforelse
    </div>
</section>
@endif
@endsection
