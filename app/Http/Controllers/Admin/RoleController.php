<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Role::class);

        return view('admin.roles.index', [
            'roles' => Role::query()->with('permissions')->withCount('users')->orderBy('id')->get(),
        ]);
    }

    public function edit(Role $role): View
    {
        Gate::authorize('update', $role);

        return view('admin.roles.edit', [
            'role' => $role->load('permissions'),
            'permissionGroups' => $this->allowedPermissions($role)->groupBy('group'),
        ]);
    }

    public function update(Request $request, Role $role, ActivityLogger $activityLogger): RedirectResponse
    {
        Gate::authorize('update', $role);
        $allowed = $this->allowedPermissions($role);
        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in($allowed->pluck('slug')->all())],
        ]);
        $before = $role->permissions()->orderBy('slug')->pluck('slug')->all();
        $permissionIds = $allowed->whereIn('slug', $validated['permissions'] ?? [])->pluck('id')->all();
        DB::transaction(function () use ($role, $permissionIds, $before, $activityLogger): void {
            $role->permissions()->sync($permissionIds);
            $after = $role->permissions()->orderBy('slug')->pluck('slug')->all();
            $activityLogger->log(
                'role.permissions_updated',
                $role,
                ['permission_slugs' => $before],
                ['permission_slugs' => $after],
                ['count' => count($after)],
            );
        });

        return redirect()->route('admin.roles.index')->with('success', 'Đã cập nhật quyền cho '.$role->name.'.');
    }

    private function allowedPermissions(Role $role): Collection
    {
        $query = Permission::query()->orderBy('group')->orderBy('slug');
        if ($role->slug === 'manager') {
            $query->whereIn('slug', Role::MANAGER_PERMISSION_SLUGS);
        } elseif ($role->slug === 'staff') {
            $query->whereIn('slug', Role::STAFF_PERMISSION_SLUGS);
        }

        return $query->get();
    }
}
