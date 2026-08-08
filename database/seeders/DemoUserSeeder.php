<?php

namespace Database\Seeders;

use App\Models\Cinema;
use App\Models\Role;
use App\Models\User;
use App\Models\UserCinemaAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $password = Hash::make('MovieMateDemo2026!');
        $roles = Role::query()->pluck('id', 'slug');
        User::query()->updateOrCreate(['email' => 'admin@moviemate.test'], [
            'name' => 'MovieMate Global Admin', 'password' => $password,
            'role_id' => $roles['admin'], 'status' => 'active', 'email_verified_at' => now(),
        ]);
        User::query()->updateOrCreate(['email' => 'customer@moviemate.test'], [
            'name' => 'MovieMate Demo Customer', 'password' => $password,
            'role_id' => $roles['user'], 'status' => 'active', 'email_verified_at' => now(),
        ]);

        foreach (Cinema::query()->active()->orderBy('code')->get() as $cinema) {
            $manager = $this->demoUser('manager.'.$cinema->code.'@moviemate.test', 'Manager '.$cinema->code, $roles['manager'], $password);
            $staff = $this->demoUser('staff.'.$cinema->code.'@moviemate.test', 'Staff '.$cinema->code, $roles['staff'], $password);
            foreach ([$manager, $staff] as $user) {
                UserCinemaAssignment::query()->updateOrCreate(
                    ['user_id' => $user->id, 'cinema_id' => $cinema->id],
                    ['status' => UserCinemaAssignment::STATUS_ACTIVE, 'assigned_at' => now(), 'assigned_by_user_id' => null],
                );
            }
        }
    }

    private function demoUser(string $email, string $name, int $roleId, string $password): User
    {
        return User::query()->updateOrCreate(['email' => mb_strtolower($email)], [
            'name' => $name, 'password' => $password, 'role_id' => $roleId,
            'status' => 'active', 'email_verified_at' => now(),
        ]);
    }
}
