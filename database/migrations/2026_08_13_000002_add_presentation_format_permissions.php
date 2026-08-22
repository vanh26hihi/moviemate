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

        $now = now();
        foreach ([
            'presentation_formats.view' => 'Xem danh mục định dạng trình chiếu',
            'presentation_formats.manage' => 'Quản lý danh mục định dạng trình chiếu',
        ] as $slug => $name) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'group' => 'presentation_formats', 'updated_at' => $now, 'created_at' => $now],
            );
            $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');
            $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');
            if ($permissionId && $adminRoleId) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $adminRoleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Presentation-format permissions are forward-only and must not be removed by rollback.');
    }
};
