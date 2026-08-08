<?php

namespace App\Policies;

use App\Models\User;
use App\Services\CinemaAccessService;

class UserPolicy
{
    public function __construct(private readonly CinemaAccessService $cinemaAccess) {}

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('users.view');
    }

    public function view(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return true;
        }
        if (! $actor->hasPermission('users.view')) {
            return false;
        }
        if ($this->cinemaAccess->hasGlobalAccess($actor)) {
            return true;
        }
        $cinemaId = $this->cinemaAccess->currentCinemaId($actor);

        return $target->hasRole('staff') && $cinemaId !== null
            && $target->activeCinemaAssignments()->where('cinema_id', $cinemaId)->exists();
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
