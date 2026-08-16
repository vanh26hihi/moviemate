<?php

namespace App\Services;

use App\Models\User;
use App\Services\Reports\AdminReportingService;
use App\Services\Reports\ReportScope;
use Carbon\CarbonImmutable;

final class AdminDashboardService
{
    public function __construct(
        private readonly AdminReportingService $reports,
        private readonly CinemaAccessService $cinemaAccess,
    ) {}

    /** @return array<string, mixed> */
    public function overview(User $user): array
    {
        $availableCinemas = $this->cinemaAccess->accessibleCinemas($user);
        $selectedCinema = $this->cinemaAccess->currentCinema($user);
        $cinemaTimezones = $availableCinemas->pluck('timezone')->filter()->unique()->values();
        $timezone = (string) ($selectedCinema?->timezone
            ?? ($cinemaTimezones->count() === 1 ? $cinemaTimezones->first() : config('app.timezone', 'Asia/Ho_Chi_Minh')));
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $cinemas = $selectedCinema
            ? $availableCinemas->where('id', $selectedCinema->id)->values()
            : $availableCinemas->values();
        $scope = new ReportScope(
            $today,
            $today,
            $cinemas,
            $availableCinemas,
            $selectedCinema?->id,
            null,
            null,
            'revenue',
        );

        $summary = $this->reports->summary($scope);
        $timeline = collect($this->reports->todayShowtimes($scope))
            ->reject(fn (array $showtime): bool => $showtime['status'] === 'cancelled')
            ->map(function (array $showtime): array {
                $now = CarbonImmutable::now($showtime['start']->getTimezone());
                $state = match (true) {
                    $now->lt($showtime['start']) => 'upcoming',
                    $now->lt($showtime['end']) => 'showing',
                    $now->lt($showtime['cleaningUntil']) => 'cleaning',
                    default => 'completed',
                };

                return [...$showtime, 'operationalState' => $state];
            });
        $timelineStats = collect(['showing' => 0, 'cleaning' => 0, 'upcoming' => 0, 'completed' => 0]);
        foreach ($timeline->countBy('operationalState') as $state => $count) {
            $timelineStats[$state] = $count;
        }
        $stateOrder = ['showing' => 0, 'cleaning' => 1, 'upcoming' => 2, 'completed' => 3];

        return [
            'generatedAt' => CarbonImmutable::now($timezone),
            'scope' => $scope,
            'filters' => $scope->query(),
            'summary' => $summary,
            'attention' => $this->reports->attention($scope),
            'ticketOperations' => $this->reports->ticketOperations($scope),
            'timelineStats' => $timelineStats->all(),
            'operationalShowtimes' => $timeline
                ->whereIn('operationalState', ['showing', 'cleaning', 'upcoming'])
                ->sortBy(fn (array $showtime): string => sprintf(
                    '%d-%s',
                    $stateOrder[$showtime['operationalState']],
                    $showtime['start']->format('Y-m-d H:i:s'),
                ))
                ->take(12)
                ->values()
                ->all(),
        ];
    }
}
