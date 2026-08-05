@extends('layouts.admin')

@section('title', 'Chỉnh quyền '.$role->name.' - MovieMate')
@section('page-title', 'Chỉnh quyền '.$role->name)

@section('content')
<div class="admin-page-header"><div><h1 class="admin-page-title">Quyền của {{ $role->name }}</h1><p class="admin-page-subtitle">Chỉ các quyền nằm trong phạm vi an toàn của vai trò này được chấp nhận.</p></div><a href="{{ route('admin.roles.index') }}" class="admin-btn-secondary">Quay lại</a></div>
<form method="POST" action="{{ route('admin.roles.permissions.update', $role) }}">@csrf @method('PATCH')
    <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
        @foreach($permissionGroups as $group => $permissions)
        <fieldset class="app-card border app-border rounded-2xl p-5">
            <legend class="font-bold uppercase text-sm tracking-wide px-2">{{ $group }}</legend>
            <div class="space-y-3 mt-2">
                @foreach($permissions as $permission)
                <label class="flex gap-3 items-start cursor-pointer"><input class="mt-1" type="checkbox" name="permissions[]" value="{{ $permission->slug }}" @checked(in_array($permission->slug, old('permissions', $role->permissions->pluck('slug')->all())))><span><span class="block font-medium">{{ $permission->slug }}</span><span class="block text-xs app-muted">{{ $permission->name }}</span></span></label>
                @endforeach
            </div>
        </fieldset>
        @endforeach
    </div>
    <button class="admin-btn-primary mt-6" type="submit"><i class="ph ph-shield-check"></i> Lưu quyền</button>
</form>
@endsection
