<?php

namespace App\Domain\Showtimes;

use App\Exceptions\ShowtimeScheduleException;
use App\Models\RoomLayout;

final readonly class ShowtimeScheduleValidationResult
{
    private function __construct(
        public ?string $timezone,
        public ?ShowtimeWindow $window,
        public ?RoomLayout $layout,
        public ?bool $isFuture,
        public ?bool $isWithinOperatingHours,
        public ?bool $isConflictFree,
        public ?ShowtimeScheduleException $failure,
    ) {}

    public static function valid(string $timezone, ShowtimeWindow $window, RoomLayout $layout): self
    {
        return new self($timezone, $window, $layout, true, true, true, null);
    }

    public static function invalid(
        ShowtimeScheduleException $failure,
        ?string $timezone = null,
        ?ShowtimeWindow $window = null,
        ?RoomLayout $layout = null,
        ?bool $isFuture = null,
        ?bool $isWithinOperatingHours = null,
        ?bool $isConflictFree = null,
    ): self {
        return new self(
            $timezone,
            $window,
            $layout,
            $isFuture,
            $isWithinOperatingHours,
            $isConflictFree,
            $failure,
        );
    }

    public function isValid(): bool
    {
        return $this->failure === null;
    }

    public function failureCode(): ?string
    {
        return $this->failure?->failureCode;
    }

    public function field(): ?string
    {
        return $this->failure?->field;
    }

    public function message(): ?string
    {
        return $this->failure?->getMessage();
    }

    public function requireValid(): self
    {
        if ($this->failure) {
            throw $this->failure;
        }

        return $this;
    }
}
