@extends('layouts.admin')

@section('title', 'Quản lý người dùng - MovieMate')
@section('page-title', 'Quản lý người dùng')

@section('content')
<div class="admin-page-header">
    <div><h1 class="admin-page-title">Người dùng</h1><p class="admin-page-subtitle">Tra cứu vai trò và trạng thái tài khoản.</p></div>
</div>

<form method="GET" class="app-card border app-border rounded-2xl p-4 mb-6 grid gap-3 md:grid-cols-4">
    <input class="app-input border app-border rounded-xl px-4 py-2" name="search" value="{{ request('search') }}" placeholder="Tên hoặc email">
    <select class="app-input border app-border rounded-xl px-4 py-2" name="role">
        <option value="">Tất cả vai trò</option>
        @foreach($roles as $role)<option value="{{ $role->slug }}" @selected(request('role') === $role->slug)>{{ $role->name }}</option>@endforeach
    </select>
    <select class="app-input border app-border rounded-xl px-4 py-2" name="status">
        <option value="">Tất cả trạng thái</option>
        <option value="active" @selected(request('status') === 'active')>Đang hoạt động</option>
        <option value="inactive" @selected(request('status') === 'inactive')>Đã vô hiệu hóa</option>
    </select>
    <button class="admin-btn-primary" type="submit"><i class="ph ph-magnifying-glass"></i> Lọc</button>
</form>

<div class="admin-table-card">
    <div class="overflow-x-auto"><table class="admin-table">
        <thead><tr><th>Người dùng</th><th>Vai trò</th><th>Trạng thái</th><th>Ngày tạo</th><th></th></tr></thead>
        <tbody>
        @forelse($users as $managedUser)
            <tr>
                <td><div class="font-semibold app-text">{{ $managedUser->name }}</div><div class="text-sm app-muted">{{ $managedUser->email }}</div></td>
                <td>{{ $managedUser->role?->name ?? 'Chưa có vai trò' }}</td>
                <td><span class="font-semibold {{ $managedUser->status === 'active' ? 'text-success' : 'text-error' }}">{{ $managedUser->status === 'active' ? 'Hoạt động' : 'Vô hiệu hóa' }}</span></td>
                <td>{{ $managedUser->created_at?->format('d/m/Y') }}</td>
                <td class="text-right"><a class="admin-btn-warning" href="{{ route('admin.users.edit', $managedUser) }}">Quản lý</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center app-muted py-10">Không tìm thấy người dùng.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
<div class="mt-5">{{ $users->links() }}</div>
@endsection
