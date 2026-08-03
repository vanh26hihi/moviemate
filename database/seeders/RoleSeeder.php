<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'admin' => ['name' => 'Admin', 'description' => 'Quản trị toàn hệ thống'],
            'manager' => ['name' => 'Manager', 'description' => 'Quản lý vận hành'],
            'staff' => ['name' => 'Staff', 'description' => 'Nhân viên vận hành'],
            'user' => ['name' => 'User', 'description' => 'Khách hàng'],
        ];

        foreach ($roles as $slug => $attributes) {
            Role::query()->updateOrCreate(
                ['slug' => $slug],
                [...$attributes, 'is_system' => true],
            );
        }
    }
}
