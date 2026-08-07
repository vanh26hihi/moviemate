<?php

namespace App\Services\Reports;

use App\Models\Cinema;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final readonly class ReportScope
{
    /** @param Collection<int, Cinema> $cinemas */
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public Collection $cinemas,
        public Collection $availableCinemas,
        public ?int $selectedCinemaId,
        public ?string $salesChannel,
        public ?string $provider,
        public string $metric,
    ) {}

    /** @return list<int> */
    public function cinemaIds(): array
    {
        return $this->cinemas->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
    }

    /** @return Collection<string, Collection<int, Cinema>> */
    public function cinemasByTimezone(): Collection
    {
        return $this->cinemas->groupBy(fn (Cinema $cinema): string => $cinema->timezone ?: (string) config('app.timezone', 'UTC'));
    }

    /** @return array<string, string> */
    public function query(): array
    {
        return array_filter([
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'cinema' => $this->selectedCinemaId === null ? 'all' : (string) $this->selectedCinemaId,
            'sales_channel' => $this->salesChannel,
            'provider' => $this->provider,
            'metric' => $this->metric,
        ], fn ($value): bool => $value !== null && $value !== '');
    }
}
