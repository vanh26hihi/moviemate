<?php

namespace App\Services\Reports;

use App\Models\Cinema;
use App\Models\User;
use App\Services\CinemaAccessService;
use Carbon\CarbonImmutable;

final class ReportScopeFactory
{
    public function __construct(private readonly CinemaAccessService $cinemaAccess) {}

    /** @param array<string, mixed> $filters */
    public function forUser(User $user, array $filters): ReportScope
    {
        $timezone = (string) config('app.timezone', 'Asia/Ho_Chi_Minh');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $from = $this->date($filters['from'] ?? null, $today->subDays(6), $timezone);
        $to = $this->date($filters['to'] ?? null, $today, $timezone);
        $available = $this->cinemaAccess->reportingCinemas($user);
        $selection = (string) ($filters['cinema'] ?? 'all');
        $selected = null;

        if ($selection !== '' && $selection !== 'all') {
            $selected = $available->first(function (Cinema $cinema) use ($selection): bool {
                return ctype_digit($selection)
                    ? (int) $cinema->id === (int) $selection
                    : strcasecmp((string) $cinema->code, $selection) === 0;
            });
            abort_unless($selected instanceof Cinema, 403, 'Bạn không có quyền xem báo cáo của chi nhánh này.');
        }

        return new ReportScope(
            $from,
            $to,
            $selected ? $available->where('id', $selected->id)->values() : $available->values(),
            $available->values(),
            $selected?->id,
            $filters['sales_channel'] ?? null,
            $filters['provider'] ?? null,
            $filters['metric'] ?? 'revenue',
        );
    }

    private function date(mixed $value, CarbonImmutable $default, string $timezone): CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return $default;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $value, $timezone) ?: $default;
    }
}
