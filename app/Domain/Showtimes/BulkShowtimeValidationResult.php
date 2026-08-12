<?php

namespace App\Domain\Showtimes;

final readonly class BulkShowtimeValidationResult
{
    /** @param list<BulkShowtimeRowResult> $rows */
    public function __construct(
        public ?int $cinemaId,
        public ?string $timezone,
        public array $rows,
    ) {}

    public function isValid(): bool
    {
        return $this->rows !== []
            && collect($this->rows)->every(fn (BulkShowtimeRowResult $row): bool => $row->isValid());
    }

    public function validCount(): int
    {
        return collect($this->rows)->filter(fn (BulkShowtimeRowResult $row): bool => $row->isValid())->count();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $validCount = $this->validCount();

        return [
            'valid' => $this->isValid(),
            'cinema_id' => $this->cinemaId,
            'timezone' => $this->timezone,
            'summary' => [
                'total' => count($this->rows),
                'valid_count' => $validCount,
                'invalid_count' => count($this->rows) - $validCount,
            ],
            'rows' => array_map(
                fn (BulkShowtimeRowResult $row): array => $row->toArray(),
                $this->rows,
            ),
        ];
    }
}
