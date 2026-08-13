<?php

namespace App\Services\Admin;

use App\Models\Cinema;
use App\Models\DiscountCode;
use App\Models\User;
use App\Services\CinemaAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class PromotionAdminAccess
{
    public function __construct(private readonly CinemaAccessService $cinemas) {}

    public function visibleQuery(Builder $query, User $actor): Builder
    {
        if ($this->cinemas->hasGlobalAccess($actor)) {
            return $query;
        }

        $cinemaId = $this->cinemas->currentCinemaId($actor);

        return $query->where(function (Builder $scope) use ($cinemaId): void {
            $scope->whereDoesntHave('cinemas');
            if ($cinemaId !== null) {
                $scope->orWhereHas('cinemas', fn (Builder $cinemas): Builder => $cinemas->whereKey($cinemaId));
            }
        });
    }

    /** @return Collection<int, Cinema> */
    public function mutationCinemas(User $actor): Collection
    {
        if ($this->cinemas->hasGlobalAccess($actor)) {
            return $this->cinemas->accessibleCinemas($actor)->sortBy('name')->values();
        }

        $cinema = $this->cinemas->currentCinema($actor);

        return $cinema === null ? collect() : collect([$cinema]);
    }

    /** @return Collection<int, int> */
    public function mutationCinemaIds(User $actor): Collection
    {
        return $this->mutationCinemas($actor)->pluck('id')->map(fn ($id): int => (int) $id)->values();
    }

    public function canManage(User $actor, DiscountCode $discount): bool
    {
        if ($this->cinemas->hasGlobalAccess($actor)) {
            return true;
        }

        $discount->loadMissing('cinemas:id');
        $scope = $discount->cinemas->pluck('id')->map(fn ($id): int => (int) $id);

        return $scope->isNotEmpty()
            && $scope->diff($this->mutationCinemaIds($actor))->isEmpty();
    }

    public function authorizeManage(User $actor, DiscountCode $discount): void
    {
        abort_unless($this->canManage($actor, $discount), 404);
    }
}
