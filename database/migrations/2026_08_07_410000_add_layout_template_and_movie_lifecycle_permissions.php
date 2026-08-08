<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('permission_role')) {
            return;
        }

        $definitions = [
            'layout_templates.view' => ['Xem mẫu sơ đồ', ['admin', 'manager']],
            'layout_templates.manage' => ['Quản lý mẫu sơ đồ', ['admin']],
            'room_layouts.apply_template' => ['Áp dụng mẫu sơ đồ cho phòng', ['admin', 'manager']],
            'movies.lifecycle' => ['Quản lý vòng đời phim', ['admin']],
        ];
        $now = now();
        foreach ($definitions as $slug => [$name, $roles]) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'group' => str($slug)->before('.')->toString(), 'updated_at' => $now, 'created_at' => $now],
            );
            $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');
            foreach (DB::table('roles')->whereIn('slug', $roles)->pluck('id') as $roleId) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $deletePermissionIds = DB::table('permissions')->whereIn('slug', ['movies.delete', 'rooms.delete'])->pluck('id');
        if ($deletePermissionIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $deletePermissionIds)->delete();
        }
    }

    public function down(): void
    {
        throw new RuntimeException('R7 operational permissions are forward-only and must not be removed by rollback.');
    }
};
