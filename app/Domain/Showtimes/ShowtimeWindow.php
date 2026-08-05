<?php

namespace App\Domain\Showtimes;

use Carbon\CarbonImmutable;

final readonly class ShowtimeWindow
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $movieEnd,
        public CarbonImmutable $operationalEnd,
        public int $runtimeMinutes,
        public int $cleaningBufferMinutes,
    ) {}

    public function overlaps(self $other): bool
    {
        return $this->start->lt($other->operationalEnd)
            && $this->operationalEnd->gt($other->start);
    }
}
