<?php

namespace App\Domain\Showtimes;

use App\Exceptions\ShowtimeScheduleException;
use App\Models\PresentationFormat;
use App\Models\RoomLayout;
use Illuminate\Support\Collection;

final readonly class ShowtimeScheduleValidationResult
{
    private function __construct(
        public ?string $timezone,
        public ?ShowtimeWindow $window,
        public ?RoomLayout $layout,
        public ?PresentationFormat $presentationFormat,
        public ?Collection $ticketPriceSnapshots,
        public ?bool $isFuture,
        public ?bool $isWithinOperatingHours,
        public ?bool $isConflictFree,
        public ?ShowtimeScheduleException $failure,
    ) {}

    public static function valid(
        string $timezone,
        ShowtimeWindow $window,
        RoomLayout $layout,
        ?PresentationFormat $presentationFormat = null,
        ?Collection $ticketPriceSnapshots = null,
    ): self {
        return new self($timezone, $window, $layout, $presentationFormat, $ticketPriceSnapshots, true, true, true, null);
    }

    public static function invalid(
        ShowtimeScheduleException $failure,
        ?string $timezone = null,
        ?ShowtimeWindow $window = null,
        ?RoomLayout $layout = null,
        ?PresentationFormat $presentationFormat = null,
        ?bool $isFuture = null,
        ?bool $isWithinOperatingHours = null,
        ?bool $isConflictFree = null,
    ): self {
        return new self(
            $timezone,
            $window,
            $layout,
            $presentationFormat,
            null,
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
