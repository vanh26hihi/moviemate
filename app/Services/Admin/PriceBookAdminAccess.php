<?php

namespace App\Services\Admin;

use App\Models\Cinema;
use App\Models\PriceBookVersion;
use App\Models\Room;
use App\Models\User;
use App\Services\CinemaAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class PriceBookAdminAccess
{
    public function __construct(private readonly CinemaAccessService $cinemas) {}

    public function canView(User $user): bool
    {
        return $user->hasPermission('pricing.view')
            && ($this->isGlobal($user) || $this->cinemas->currentCinema($user) !== null);
    }

    public function canManage(User $user): bool
    {
        return $user->hasPermission('pricing.manage') && $this->isGlobal($user);
    }

    public function authorizeView(User $user): void
    {
        abort_unless($this->canView($user), 403);
    }

    public function authorizeManage(User $user): void
    {
        abort_unless($this->canManage($user), 403);
    }

    public function authorizeVersionView(User $user, PriceBookVersion $version): void
    {
        $this->authorizeView($user);
        if (! $this->isGlobal($user)) {
            abort_unless($version->status === PriceBookVersion::STATUS_PUBLISHED, 404);
        }
    }

    public function visibleVersions(Builder $query, User $user): Builder
    {
        $this->authorizeView($user);

        return $this->isGlobal($user)
            ? $query
            : $query->where('status', PriceBookVersion::STATUS_PUBLISHED);
    }

    /** @return Collection<int, Cinema> */
    public function previewCinemas(User $user): Collection
    {
        $this->authorizeView($user);
        if ($this->isGlobal($user)) {
            return $this->cinemas->accessibleCinemas($user)->sortBy('name')->values();
        }

        $cinema = $this->cinemas->currentCinema($user);

        return $cinema ? collect([$cinema]) : collect();
    }

    public function authorizePreviewCinema(User $user, Cinema $cinema): void
    {
        $this->authorizeView($user);
        abort_unless($cinema->status === 'active' && $cinema->archived_at === null, 404);
        if (! $this->isGlobal($user)) {
            abort_unless($this->cinemas->currentCinemaId($user) === (int) $cinema->id, 404);
        }
    }

    public function authorizePreviewRoom(User $user, Cinema $cinema, Room $room): void
    {
        $this->authorizePreviewCinema($user, $cinema);
        abort_unless(
            (int) $room->cinema_id === (int) $cinema->id && $room->status === 'active',
            404,
        );
    }

    public function isGlobal(User $user): bool
    {
        return $this->cinemas->hasGlobalAccess($user);
    }
}
