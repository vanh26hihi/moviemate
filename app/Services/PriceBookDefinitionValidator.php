<?php

namespace App\Services;

use App\Exceptions\PriceBookException;
use App\Models\PriceBookAdjustment;
use App\Models\PriceBookVersion;
use App\Models\Showtime;
use Illuminate\Support\Collection;

final class PriceBookDefinitionValidator
{
    /** @param Collection<int, PriceBookAdjustment> $adjustments */
    public function validateForPublish(PriceBookVersion $version, Collection $adjustments): void
    {
        $base = $version->base_price_vnd;
        if (! is_int($base) || $base <= 0 || $base > Showtime::MAX_PRICE) {
            $this->fail('Published PriceBookVersion requires one positive supported integer VND base price.');
        }
        if ($version->effective_from === null) {
            $this->fail('Published PriceBookVersion requires effective_from.');
        }
        if ($version->effective_until !== null
            && ! $version->effective_until->greaterThan($version->effective_from)) {
            $this->fail('PriceBookVersion effective period must be a non-empty [from, until) range.');
        }

        $this->validateAdjustments($adjustments);
        $this->validateSupportedResultRange($base, $adjustments);
    }

    /** @param Collection<int, PriceBookAdjustment> $adjustments */
    public function validateAdjustments(Collection $adjustments): void
    {
        foreach ($adjustments as $adjustment) {
            $this->validateShape($adjustment);
        }

        foreach (['seat_type' => 'seat_type_id', 'room_type' => 'room_type_id', 'cinema' => 'cinema_id', 'room' => 'room_id'] as $dimension => $column) {
            $scopes = collect($adjustments->where('dimension', $dimension)->pluck($column)->all());
            if ($scopes->duplicates()->isNotEmpty()) {
                $this->ambiguous("Duplicate {$dimension} adjustment scope.");
            }
        }

        $this->validateWeekendDays($adjustments->where('dimension', 'weekend')->values());
        $this->validateTimeWindows($adjustments->where('dimension', 'time_window')->values());
        $this->validateHolidayRanges($adjustments->where('dimension', 'holiday')->values());
    }

    public function canonicalize(array $attributes): array
    {
        if (($attributes['dimension'] ?? null) === 'weekend' && isset($attributes['weekend_days'])) {
            $days = array_map('intval', (array) $attributes['weekend_days']);
            $days = array_values(array_unique($days));
            sort($days);
            $attributes['weekend_days'] = $days;
        }

        foreach (['time_start', 'time_end'] as $field) {
            if (isset($attributes[$field]) && preg_match('/^\d{2}:\d{2}$/', (string) $attributes[$field]) === 1) {
                $attributes[$field] .= ':00';
            }
        }

        return $attributes;
    }

    private function validateShape(PriceBookAdjustment $adjustment): void
    {
        if (! in_array($adjustment->dimension, PriceBookAdjustment::DIMENSIONS, true)) {
            $this->fail('Unapproved PriceBook adjustment dimension.');
        }
        if (! is_int($adjustment->amount_vnd) || $adjustment->amount_vnd === 0
            || abs($adjustment->amount_vnd) > Showtime::MAX_PRICE) {
            $this->fail('Adjustment must be a non-zero signed integer within the supported VND range.');
        }
        if (trim((string) $adjustment->label) === '') {
            $this->fail('Adjustment label is required.');
        }

        $fields = [
            'seat_type_id', 'room_type_id', 'cinema_id', 'room_id',
            'time_start', 'time_end', 'holiday_date_from', 'holiday_date_until', 'weekend_days',
        ];
        $required = match ($adjustment->dimension) {
            'seat_type' => ['seat_type_id'],
            'room_type' => ['room_type_id'],
            'cinema' => ['cinema_id'],
            'room' => ['room_id'],
            'time_window' => ['time_start', 'time_end'],
            'holiday' => ['holiday_date_from', 'holiday_date_until'],
            'weekend' => ['weekend_days'],
        };

        foreach ($fields as $field) {
            $present = $adjustment->{$field} !== null;
            if (in_array($field, $required, true) !== $present) {
                $this->fail("Contradictory {$adjustment->dimension} adjustment shape at {$field}.");
            }
        }

        if ($adjustment->dimension === 'time_window'
            && $this->clock($adjustment->time_start) === $this->clock($adjustment->time_end)) {
            $this->fail('Time-window start and end must differ.');
        }
        if ($adjustment->dimension === 'holiday'
            && ! $adjustment->holiday_date_until->greaterThan($adjustment->holiday_date_from)) {
            $this->fail('Holiday period must be a non-empty [from, until) range.');
        }
    }

    /** @param Collection<int, PriceBookAdjustment> $weekends */
    private function validateWeekendDays(Collection $weekends): void
    {
        $occupied = [];
        foreach ($weekends as $weekend) {
            $days = $weekend->weekend_days;
            if (! is_array($days) || $days === []) {
                $this->fail('Weekend days must be a non-empty canonical ISO weekday array.');
            }
            $canonical = array_values(array_unique(array_map('intval', $days)));
            sort($canonical);
            if ($canonical !== $days || collect($canonical)->contains(fn (int $day): bool => $day < 1 || $day > 7)) {
                $this->fail('Weekend days must be unique sorted ISO weekday integers 1..7.');
            }
            foreach ($canonical as $day) {
                if (isset($occupied[$day])) {
                    $this->ambiguous('Weekend adjustments overlap on an ISO weekday.');
                }
                $occupied[$day] = true;
            }
        }
    }

    /** @param Collection<int, PriceBookAdjustment> $windows */
    private function validateTimeWindows(Collection $windows): void
    {
        $segments = [];
        foreach ($windows as $window) {
            [$start, $end] = [$this->clock($window->time_start), $this->clock($window->time_end)];
            $candidate = $start < $end ? [[$start, $end]] : [[$start, 1440], [0, $end]];
            foreach ($candidate as [$from, $until]) {
                foreach ($segments as [$existingFrom, $existingUntil]) {
                    if ($from < $existingUntil && $existingFrom < $until) {
                        $this->ambiguous('Time-window adjustments overlap in clock applicability.');
                    }
                }
                $segments[] = [$from, $until];
            }
        }
    }

    /** @param Collection<int, PriceBookAdjustment> $holidays */
    private function validateHolidayRanges(Collection $holidays): void
    {
        foreach ($holidays as $index => $holiday) {
            foreach ($holidays->slice($index + 1) as $other) {
                if ($holiday->holiday_date_from->lessThan($other->holiday_date_until)
                    && $other->holiday_date_from->lessThan($holiday->holiday_date_until)) {
                    $this->ambiguous('Holiday adjustment periods overlap.');
                }
            }
        }
    }

    /** @param Collection<int, PriceBookAdjustment> $adjustments */
    private function validateSupportedResultRange(int $base, Collection $adjustments): void
    {
        $minimum = $base;
        $maximum = $base;
        foreach (['seat_type', 'room_type', 'time_window', 'cinema', 'room'] as $dimension) {
            $amounts = $adjustments->where('dimension', $dimension)->pluck('amount_vnd')->map(fn ($value): int => (int) $value);
            $minimum += min(0, $amounts->min() ?? 0);
            $maximum += max(0, $amounts->max() ?? 0);
        }

        $weekendAmounts = $adjustments->where('dimension', 'weekend')->pluck('amount_vnd')->map(fn ($value): int => (int) $value);
        $holidayAmounts = $adjustments->where('dimension', 'holiday')->pluck('amount_vnd')->map(fn ($value): int => (int) $value);
        $calendarMinimum = min(0, $weekendAmounts->min() ?? 0, $holidayAmounts->min() ?? 0);
        $calendarMaximum = max(0, $weekendAmounts->max() ?? 0, $holidayAmounts->max() ?? 0);
        $minimum += $calendarMinimum;
        $maximum += $calendarMaximum;

        if ($minimum <= 0) {
            throw new PriceBookException(
                PriceBookException::RESULT_NOT_POSITIVE,
                'A supported PriceBook context can produce a zero or negative logical ticket amount.',
            );
        }
        if ($maximum > Showtime::MAX_PRICE) {
            $this->fail('A supported PriceBook context exceeds the supported VND ticket ceiling.');
        }
    }

    private function clock(?string $time): int
    {
        if (! is_string($time) || preg_match('/^(\d{2}):(\d{2})(?::\d{2})?$/', $time, $matches) !== 1) {
            $this->fail('Invalid time-window clock value.');
        }
        $hour = (int) $matches[1];
        $minute = (int) $matches[2];
        if ($hour > 23 || $minute > 59) {
            $this->fail('Invalid time-window clock value.');
        }

        return ($hour * 60) + $minute;
    }

    private function fail(string $message): never
    {
        throw new PriceBookException(PriceBookException::INVALID_ADJUSTMENT, $message);
    }

    private function ambiguous(string $message): never
    {
        throw new PriceBookException(PriceBookException::AMBIGUOUS_ADJUSTMENT, $message);
    }
}
