<?php

namespace App\Services;

use App\Models\Cinema;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class CinemaAccessService
{
    public const SESSION_KEY = 'admin_cinema_context';

    private ?Collection $accessible = null;

    private bool $resolved = false;

    private ?int $currentCinemaId = null;

    private ?object $memoRequest = null;

    private ?int $memoUserId = null;

    /**
     * Memoization is a per-request, per-user optimisation only. The container binding is
     * scoped, but a scoped instance survives several requests under Octane and inside the
     * test harness, so stale access lists must never outlive the request or the actor that
     * produced them. Revoked assignments therefore take effect on the very next request.
     */
    private function syncMemo(User $user): void
    {
        $request = request();
        if ($this->memoRequest === $request && $this->memoUserId === (int) $user->id) {
            return;
        }

        $this->memoRequest = $request;
        $this->memoUserId = (int) $user->id;
        $this->accessible = null;
        $this->resolved = false;
        $this->currentCinemaId = null;
    }

    public function hasGlobalAccess(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /** @return Collection<int, Cinema> */
    public function accessibleCinemas(User $user, bool $includeInactiveForGlobal = false): Collection
    {
        $this->syncMemo($user);
        if ($this->accessible !== null && ! $includeInactiveForGlobal) {
            return $this->accessible;
        }

        $query = Cinema::query()->orderBy('name');
        if (! ($this->hasGlobalAccess($user) && $includeInactiveForGlobal)) {
            $query->active();
        }
        if (! $this->hasGlobalAccess($user)) {
            $query->whereHas('activeAssignments', fn (Builder $query): Builder => $query->where('user_id', $user->id));
        }

        $cinemas = $query->get(['id', 'code', 'name', 'address', 'city', 'status', 'timezone']);

        return $includeInactiveForGlobal ? $cinemas : ($this->accessible = $cinemas);
    }

    /**
     * Reporting is history-preserving: an inactive branch remains selectable by Global Admin,
     * and by a Manager who still has an active assignment to it. This intentionally does not
     * alter the active-branch behaviour used by operational mutation screens.
     *
     * @return Collection<int, Cinema>
     */
    public function reportingCinemas(User $user): Collection
    {
        $this->syncMemo($user);

        return Cinema::query()
            ->when(! $this->hasGlobalAccess($user), fn (Builder $query): Builder => $query
                ->whereHas('activeAssignments', fn (Builder $assignment): Builder => $assignment
                    ->where('user_id', $user->id)))
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'status', 'timezone', 'archived_at']);
    }

    public function resolve(User $user): ?Cinema
    {
        $this->syncMemo($user);
        if ($this->resolved) {
            return $this->currentCinema($user);
        }

        $this->resolved = true;
        $stored = request()->session()->get(self::SESSION_KEY);
        if ($this->hasGlobalAccess($user) && ($stored === null || $stored === 'all')) {
            $this->currentCinemaId = null;

            return null;
        }

        $available = $this->accessibleCinemas($user);
        if ($stored === null) {
            $this->currentCinemaId = $available->count() === 1 ? (int) $available->first()->id : null;
            if ($this->currentCinemaId !== null) {
                request()->session()->put(self::SESSION_KEY, $this->currentCinemaId);
            }

            return $available->firstWhere('id', $this->currentCinemaId);
        }

        if (! ctype_digit((string) $stored) || ! $available->contains('id', (int) $stored)) {
            abort(403, 'Ngữ cảnh chi nhánh không hợp lệ.');
        }

        $this->currentCinemaId = (int) $stored;

        return $available->firstWhere('id', $this->currentCinemaId);
    }

    public function select(User $user, ?int $cinemaId): void
    {
        $this->syncMemo($user);
        if ($cinemaId === null) {
            abort_unless($this->hasGlobalAccess($user), 403);
            request()->session()->put(self::SESSION_KEY, 'all');
        } else {
            abort_unless($this->canAccessCinema($user, $cinemaId), 403);
            request()->session()->put(self::SESSION_KEY, $cinemaId);
        }

        $this->resolved = false;
        $this->currentCinemaId = null;
    }

    public function currentCinema(User $user): ?Cinema
    {
        $this->syncMemo($user);
        if (! $this->resolved) {
            return $this->resolve($user);
        }

        return $this->currentCinemaId === null
            ? null
            : $this->accessibleCinemas($user)->firstWhere('id', $this->currentCinemaId);
    }

    public function currentCinemaId(User $user): ?int
    {
        $this->resolve($user);

        return $this->currentCinemaId;
    }

    public function canAccessCinema(User $user, int $cinemaId): bool
    {
        return $this->hasGlobalAccess($user)
            || $this->accessibleCinemas($user)->contains('id', $cinemaId);
    }

    public function authorizeCinema(User $user, int $cinemaId): void
    {
        abort_unless($this->canAccessCinema($user, $cinemaId), 404);
        $selected = $this->currentCinemaId($user);
        if ($selected !== null) {
            abort_unless($selected === $cinemaId, 404);
        } elseif (! $this->hasGlobalAccess($user)) {
            abort(404);
        }
    }

    public function allowsInCurrentContext(User $user, int $cinemaId): bool
    {
        if (! $this->canAccessCinema($user, $cinemaId)) {
            return false;
        }
        $selected = $this->currentCinemaId($user);

        return $selected !== null ? $selected === $cinemaId : $this->hasGlobalAccess($user);
    }

    public function scope(Builder $query, User $user, string $column = 'cinema_id'): Builder
    {
        $cinemaId = $this->currentCinemaId($user);
        if ($cinemaId !== null) {
            return $query->where($column, $cinemaId);
        }

        return $this->hasGlobalAccess($user) ? $query : $query->whereRaw('1 = 0');
    }
}
