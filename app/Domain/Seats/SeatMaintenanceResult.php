<?php

namespace App\Domain\Seats;

final readonly class SeatMaintenanceResult
{
    /** @param list<string> $unitLabels */
    public function __construct(
        public bool $changed,
        public array $unitLabels,
        public string $status,
    ) {}
}
