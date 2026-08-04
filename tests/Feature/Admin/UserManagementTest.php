<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_admin_can_promote_user_to_staff_and_staff_to_manager(): void
    {
        $admin = $this->userWithRole('admin');
        $user = $this->userWithRole('user');
        $staff = $this->userWithRole('staff');

        $this->actingAs($admin)->patch(route('admin.users.role.update', $user), ['role' => 'staff'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->hasRole('staff'));

        $this->actingAs($admin)->patch(route('admin.users.role.update', $staff), ['role' => 'manager'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($staff->fresh()->hasRole('manager'));
    }

    public function test_manager_staff_and_customer_cannot_change_roles(): void
    {
        $target = $this->userWithRole('user');

        foreach (['manager', 'staff', 'user'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->patch(route('admin.users.role.update', $target), ['role' => 'admin'])
                ->assertForbidden();
            $this->assertTrue($target->fresh()->hasRole('user'));
        }
    }

    public function test_last_active_admin_cannot_be_demoted_or_deactivated(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->from(route('admin.users.edit', $admin))
            ->patch(route('admin.users.role.update', $admin), ['role' => 'manager'])
            ->assertSessionHasErrors('admin');
        $this->assertTrue($admin->fresh()->hasRole('admin'));

        $this->actingAs($admin)->from(route('admin.users.edit', $admin))
            ->patch(route('admin.users.status.update', $admin), ['status' => 'inactive'])
            ->assertSessionHasErrors('admin');
        $this->assertSame('active', $admin->fresh()->status);
    }

    public function test_with_two_active_admins_one_admin_can_update_the_other(): void
    {
        $actor = $this->userWithRole('admin');
        $target = $this->userWithRole('admin');

        $this->actingAs($actor)
            ->patch(route('admin.users.status.update', $target), ['status' => 'inactive'])
            ->assertSessionHasNoErrors();

        $this->assertSame('inactive', $target->fresh()->status);
        $this->assertSame('active', $actor->fresh()->status);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $admin = $this->userWithRole('admin');
        $target = $this->userWithRole('user');

        $this->actingAs($admin)
            ->patch(route('admin.users.status.update', $target), ['status' => 'banned'])
            ->assertSessionHasErrors('status');

        $this->assertSame('active', $target->fresh()->status);
    }

    public function test_admin_and_user_system_role_permissions_cannot_be_edited(): void
    {
        $admin = $this->userWithRole('admin');

        foreach (['admin', 'user'] as $slug) {
            $role = Role::query()->where('slug', $slug)->firstOrFail();
            $this->actingAs($admin)->get(route('admin.roles.edit', $role))->assertForbidden();
        }
    }

    public function test_manager_permission_editor_rejects_user_management_permissions(): void
    {
        $admin = $this->userWithRole('admin');
        $managerRole = Role::query()->where('slug', 'manager')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.roles.permissions.update', $managerRole), ['permissions' => ['users.view']])
            ->assertSessionHasErrors('permissions.0');

        $this->assertFalse($managerRole->fresh()->hasPermission('users.view'));
    }
}
