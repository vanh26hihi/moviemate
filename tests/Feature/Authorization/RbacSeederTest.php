<?php

namespace Tests\Feature\Authorization;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_are_idempotent_and_apply_the_required_matrix(): void
    {
        $this->seedRbac();
        $this->seedRbac();

        $this->assertDatabaseCount('roles', 4);
        $this->assertDatabaseCount('permissions', count(PermissionSeeder::PERMISSIONS));
        $this->assertSame(Permission::query()->count(), Permission::query()->distinct()->count('slug'));

        $admin = Role::query()->where('slug', 'admin')->firstOrFail();
        $manager = Role::query()->where('slug', 'manager')->firstOrFail();
        $staff = Role::query()->where('slug', 'staff')->firstOrFail();
        $user = Role::query()->where('slug', 'user')->firstOrFail();

        $this->assertSame(Permission::query()->count(), $admin->permissions()->count());
        $this->assertTrue($manager->hasPermission('admin.access'));
        $this->assertTrue($manager->hasPermission('cinema.update'));
        $this->assertFalse($manager->hasPermission('cinema.delete'));
        $this->assertFalse($manager->hasPermission('users.view'));
        foreach ([
            'bookings.view',
            'payments.view',
            'payments.reconcile',
            'ticket_deliveries.view',
            'ticket_deliveries.retry',
            'ticket_checkins.view',
            'seats.maintenance.view',
            'seats.maintenance.update',
            'discounts.view',
            'discounts.manage',
            'reviews.view',
            'reviews.moderate',
            'reports.view',
        ] as $permission) {
            $this->assertTrue($manager->hasPermission($permission), "Manager thiếu quyền {$permission}");
        }
        $this->assertFalse($manager->hasPermission('activity_logs.view'));
        $this->assertTrue($admin->hasPermission('activity_logs.view'));
        $this->assertTrue($staff->hasPermission('ticket_checkins.view'));
        $this->assertFalse($staff->hasPermission('activity_logs.view'));
        $this->assertTrue($staff->hasPermission('tickets.checkin'));
        $this->assertFalse($staff->hasPermission('admin.access'));
        $this->assertSame(0, $user->permissions()->count());
    }
}
