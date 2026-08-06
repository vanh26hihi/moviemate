<?php

namespace Tests;

use App\Models\Cinema;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCinemaAssignment;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\DatabaseSafetyGuard;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');

        DatabaseSafetyGuard::assertSafe(
            $app['config']->get("database.connections.{$connection}")
        );

        return $app;
    }

    protected function seedRbac(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    protected function userWithRole(string $role, array $attributes = []): User
    {
        $roleId = Role::query()->where('slug', $role)->value('id');

        $user = User::factory()->create(['status' => 'active', ...$attributes, 'role_id' => $roleId]);
        if (in_array($role, ['manager', 'staff'], true)
            && ($cinemaId = Cinema::query()->active()->primary()->value('id'))) {
            UserCinemaAssignment::query()->create([
                'user_id' => $user->id,
                'cinema_id' => $cinemaId,
                'status' => UserCinemaAssignment::STATUS_ACTIVE,
                'assigned_at' => now(),
            ]);
        }

        return $user;
    }
}
