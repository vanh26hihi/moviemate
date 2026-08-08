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

        $permissions = [
            'bookings.view' => ['Xem đơn đặt vé', ['admin', 'manager', 'staff']],
            'payments.view' => ['Xem thanh toán', ['admin', 'manager']],
            'payments.reconcile' => ['Đối soát giao dịch', ['admin', 'manager']],
            'ticket_deliveries.view' => ['Xem lịch sử gửi vé điện tử', ['admin', 'manager']],
            'ticket_deliveries.retry' => ['Gửi lại vé điện tử', ['admin', 'manager']],
            'ticket_checkins.view' => ['Xem lịch sử soát vé', ['admin', 'manager', 'staff']],
            'seats.maintenance.view' => ['Xem trạng thái bảo trì ghế', ['admin', 'manager']],
            'seats.maintenance.update' => ['Cập nhật trạng thái bảo trì ghế', ['admin', 'manager']],
            'discounts.view' => ['Xem mã giảm giá', ['admin', 'manager']],
            'discounts.manage' => ['Quản lý mã giảm giá', ['admin', 'manager']],
            'reviews.view' => ['Xem đánh giá phim', ['admin', 'manager']],
            'reviews.moderate' => ['Kiểm duyệt đánh giá phim', ['admin', 'manager']],
            'reports.view' => ['Xem báo cáo', ['admin', 'manager']],
            'activity_logs.view' => ['Xem nhật ký hoạt động', ['admin']],
        ];
        $now = now();

        foreach ($permissions as $slug => [$name, $roleSlugs]) {
            $permission = DB::table('permissions')->where('slug', $slug);
            if ($permission->exists()) {
                $permission->update([
                    'name' => $name,
                    'group' => str($slug)->before('.')->toString(),
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('permissions')->insert([
                    'slug' => $slug,
                    'name' => $name,
                    'group' => str($slug)->before('.')->toString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');
            $roleIds = DB::table('roles')->whereIn('slug', $roleSlugs)->pluck('id');

            foreach ($roleIds as $roleId) {
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
        throw new RuntimeException('Operations permissions are forward-only and must not be removed by rollback.');
    }
};
