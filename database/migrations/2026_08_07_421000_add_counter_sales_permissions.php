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
            'counter_sales.view' => 'Xem khu vực bán vé tại quầy',
            'counter_sales.create' => 'Tạo đơn bán vé tại quầy',
            'counter_sales.settle' => 'Xác nhận thu tiền mặt tại quầy',
            'counter_sales.cancel' => 'Hủy đơn giữ chỗ tại quầy',
        ];
        $now = now();

        foreach ($definitions as $slug => $name) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $slug],
                ['name' => $name, 'group' => 'counter_sales', 'created_at' => $now, 'updated_at' => $now],
            );
            $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');
            foreach (DB::table('roles')->whereIn('slug', ['admin', 'manager', 'staff'])->pluck('id') as $roleId) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('R8 counter-sale permissions are forward-only and must not be removed by rollback.');
    }
};
