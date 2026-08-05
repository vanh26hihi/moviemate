<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
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

        return User::factory()->create(['status' => 'active', ...$attributes, 'role_id' => $roleId]);
    }
}
