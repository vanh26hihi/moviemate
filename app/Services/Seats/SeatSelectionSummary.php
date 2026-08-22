<?php

namespace App\Services\Seats;

final class SeatSelectionSummary
{
    /**
     * @param  list<int>  $selected
     * @param  list<int>  $available
     * @param  list<int>  $conflicts
     * @param  list<int>  $blocked
     */
    public function __construct(
        public readonly array $selected,
        public readonly array $available,
        public readonly array $conflicts,
        public readonly array $blocked,
        public readonly bool $valid,
        public readonly ?string $message,
    ) {}

    /**
     * @return array{selected:list<int>, available:list<int>, conflicts:list<int>, blocked:list<int>, valid:bool, message:?string}
     */
    public function toArray(): array
    {
        return [
            'selected' => $this->selected,
            'available' => $this->available,
            'conflicts' => $this->conflicts,
            'blocked' => $this->blocked,
            'valid' => $this->valid,
            'message' => $this->message,
        ];
    }

    public static function fromSelection(
        iterable $selectedSeatIds,
        iterable $availableSeatIds,
        iterable $conflictingSeatIds,
        iterable $blockedSeatIds,
        bool $valid,
        ?string $message = null,
    ): self {
        return new self(
            array_values(array_map('intval', is_array($selectedSeatIds) ? $selectedSeatIds : iterator_to_array($selectedSeatIds))),
            array_values(array_map('intval', is_array($availableSeatIds) ? $availableSeatIds : iterator_to_array($availableSeatIds))),
            array_values(array_map('intval', is_array($conflictingSeatIds) ? $conflictingSeatIds : iterator_to_array($conflictingSeatIds))),
            array_values(array_map('intval', is_array($blockedSeatIds) ? $blockedSeatIds : iterator_to_array($blockedSeatIds))),
            $valid,
            $message,
        );
    }
}
