<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('users.view');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->is($target) || $actor->hasPermission('users.view');
    }

    public function manageRole(User $actor): bool
    {
        return $actor->hasPermission('users.manage-role');
    }

    public function manageStatus(User $actor): bool
    {
        return $actor->hasPermission('users.manage-status');
    }
}
