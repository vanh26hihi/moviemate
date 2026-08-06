<?php

namespace App\Services;

use App\Exceptions\CinemaConfigurationException;
use App\Models\Cinema;
use Illuminate\Support\Collection;

/** Customer-facing branch selection. Admin authorization uses CinemaAccessService. */
class CinemaContext
{
    public const CANONICAL_KEY = 'moviemate-fpt-polytechnic';

    public const SCHOOL_NAME = 'Trường Cao đẳng FPT Polytechnic';

    public const ADDRESS = 'Tòa nhà FPT Polytechnic, Cổng số 2, số 13 Trịnh Văn Bô, Xuân Phương, Hà Nội 100000, Việt Nam';

    public const CITY = 'Hà Nội';

    public const COUNTRY = 'Việt Nam';

    public const LATITUDE = '21.0381298';

    public const LONGITUDE = '105.44239119453124';

    public const SESSION_KEY = 'customer_cinema_id';

    private ?Cinema $resolved = null;

    /** @return Collection<int, Cinema> */
    public function activeCinemas(): Collection
    {
        return Cinema::query()->active()->orderBy('name')
            ->get(['id', 'code', 'name', 'address', 'city', 'district', 'timezone']);
    }

    public function preference(): ?Cinema
    {
        if ($this->resolved) {
            return $this->resolved;
        }
        $selectedId = request()->hasSession() ? request()->session()->get(self::SESSION_KEY) : null;
        if ($selectedId === null) {
            return null;
        }
        if (! is_int($selectedId) && ! ctype_digit((string) $selectedId)) {
            request()->session()->forget(self::SESSION_KEY);

            return null;
        }
        $selected = Cinema::query()->active()->find((int) $selectedId);
        if (! $selected) {
            request()->session()->forget(self::SESSION_KEY);

            return null;
        }

        return $this->resolved = $selected;
    }

    public function current(): Cinema
    {
        if ($this->resolved) {
            return $this->resolved;
        }

        if ($selected = $this->preference()) {
            return $selected;
        }

        // More than one active primary branch is a configuration fault, not a fallback case.
        // Failing loudly here keeps the customer default deterministic across branches.
        $primaries = Cinema::query()->active()->primary()->limit(2)->get();
        if ($primaries->count() > 1) {
            throw new CinemaConfigurationException('Multiple active primary cinemas are configured.');
        }
        $primary = $primaries->first();
        if (! $primary) {
            throw new CinemaConfigurationException('No active primary cinema is configured.');
        }

        return $this->resolved = $primary;
    }

    public function select(Cinema $cinema): void
    {
        if ($cinema->status !== 'active' || $cinema->archived_at !== null) {
            throw new \InvalidArgumentException('Inactive cinema cannot be selected.');
        }
        request()->session()->put(self::SESSION_KEY, $cinema->id);
        $this->resolved = $cinema;
    }

    public function clearPreference(): void
    {
        request()->session()->forget(self::SESSION_KEY);
        $this->resolved = null;
    }

    public function id(): int
    {
        return (int) $this->current()->getKey();
    }
}
