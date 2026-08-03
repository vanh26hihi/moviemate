<?php

namespace App\Services;

use App\Exceptions\CinemaConfigurationException;
use App\Models\Cinema;

class CinemaContext
{
    public const CANONICAL_KEY = 'moviemate-fpt-polytechnic';

    public const SCHOOL_NAME = 'Trường Cao đẳng FPT Polytechnic';

    public const ADDRESS = 'Tòa nhà FPT Polytechnic, Cổng số 2, số 13 Trịnh Văn Bô, Xuân Phương, Hà Nội 100000, Việt Nam';

    public const CITY = 'Hà Nội';

    public const COUNTRY = 'Việt Nam';

    public const LATITUDE = '21.0381298';

    public const LONGITUDE = '105.44239119453124';

    private ?Cinema $resolved = null;

    public function current(): Cinema
    {
        if ($this->resolved) {
            return $this->resolved;
        }

        $matches = Cinema::query()->primary()->active()->limit(2)->get();

        if ($matches->count() !== 1 || $matches->first()?->canonical_key !== self::CANONICAL_KEY) {
            throw new CinemaConfigurationException(
                $matches->isEmpty()
                    ? 'Canonical FPT cinema is not configured.'
                    : ($matches->count() > 1
                        ? 'Multiple active primary cinemas are configured.'
                        : 'The active primary cinema is not the canonical FPT cinema.')
            );
        }

        return $this->resolved = $matches->sole();
    }

    public function id(): int
    {
        return $this->current()->getKey();
    }
}
