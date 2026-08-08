<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissionIds = Permission::query()->pluck('id', 'slug');

        Role::query()->where('slug', 'admin')->firstOrFail()
            ->permissions()->sync($permissionIds->except(Role::DEPRECATED_PERMISSION_SLUGS)->values()->all());

        Role::query()->where('slug', 'manager')->firstOrFail()
            ->permissions()->sync($permissionIds->only(Role::MANAGER_PERMISSION_SLUGS)->values()->all());

        Role::query()->where('slug', 'staff')->firstOrFail()
            ->permissions()->sync($permissionIds->only(Role::STAFF_PERMISSION_SLUGS)->values()->all());

        Role::query()->where('slug', 'user')->firstOrFail()
            ->permissions()->sync([]);
    }
}
