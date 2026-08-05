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
        $this->assertTrue($staff->hasPermission('tickets.checkin'));
        $this->assertFalse($staff->hasPermission('admin.access'));
        $this->assertSame(0, $user->permissions()->count());
    }
}
