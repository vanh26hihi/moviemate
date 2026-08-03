<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.view');
    }

    public function view(User $user): bool
    {
        return $user->hasPermission('roles.view');
    }

    public function update(User $user, Role $role): bool
    {
        return $role->isEditable() && $user->hasPermission('roles.manage');
    }
}
