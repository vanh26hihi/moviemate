<?php

namespace App\Support;

use Illuminate\Support\Collection;

final class SeatPresentation
{
    /**
     * Group physical seat records into the visual/admission units shown to people.
     *
     * @param  Collection<int, object>  $seats
     * @return Collection<int, array{seats: Collection, seat_ids: array<int>, seat_code: string, label: string, type: string, is_couple: bool, is_valid: bool}>
     */
    public static function groups(Collection $seats): Collection
    {
        $ordered = $seats
            ->filter()
            ->sortBy(fn ($seat): string => sprintf(
                '%s:%04d:%04d',
                (string) ($seat->row ?? ''),
                (int) ($seat->number ?? 0),
                (int) ($seat->id ?? 0),
            ))
            ->values();
        $used = [];
        $groups = collect();

        foreach ($ordered as $seat) {
            $identity = self::identity($seat);
            if (isset($used[$identity])) {
                continue;
            }

            if (strtolower((string) ($seat->type ?? '')) !== 'couple') {
                $used[$identity] = true;
                $code = (string) ($seat->seat_code ?? '');
                $groups->push(self::group(collect([$seat]), $code, $code, false, true));

                continue;
            }

            $pairCode = trim((string) ($seat->pair_code ?? ''));
            $pair = $pairCode === ''
                ? collect([$seat])
                : $ordered->filter(fn ($candidate): bool => (
                    strtolower((string) ($candidate->type ?? '')) === 'couple'
                    && (string) ($candidate->pair_code ?? '') === $pairCode
                ))->values();
            $valid = self::isValidCouple($pair);

            foreach ($pair as $member) {
                $used[self::identity($member)] = true;
            }

            if ($valid) {
                $pair = $pair->sortBy(fn ($member): int => ($member->pair_position ?? null) === 'left' ? 0 : 1)->values();
                $code = $pair->pluck('seat_code')->join('–');
                $groups->push(self::group($pair, $code, "Ghế đôi {$code}", true, true));

                continue;
            }

            foreach ($pair as $member) {
                $code = (string) ($member->seat_code ?? '');
                $groups->push(self::group(collect([$member]), $code, "Ghế đôi {$code} (không hợp lệ)", true, false));
            }
        }

        return $groups;
    }

    /** @param Collection<int, object> $pair */
    public static function isValidCouple(Collection $pair): bool
    {
        if ($pair->count() !== 2) {
            return false;
        }

        $ordered = $pair->sortBy('number')->values();
        $left = $ordered[0];
        $right = $ordered[1];
        $positions = $pair->pluck('pair_position')->sort()->values()->all();
        $sameRow = (string) ($left->row ?? '') !== ''
            && (string) ($left->row ?? '') === (string) ($right->row ?? '');
        $adjacentNumbers = abs((int) ($left->number ?? 0) - (int) ($right->number ?? 0)) === 1;
        $hasCoordinates = $left->x_position !== null && $right->x_position !== null
            && $left->y_position !== null && $right->y_position !== null;
        $adjacentCoordinates = ! $hasCoordinates || (
            (int) $left->y_position === (int) $right->y_position
            && abs((int) $left->x_position - (int) $right->x_position) === 1
        );

        return $sameRow
            && $adjacentNumbers
            && $adjacentCoordinates
            && $positions === ['left', 'right'];
    }

    private static function identity(object $seat): string
    {
        return $seat->id !== null
            ? 'id:'.(string) $seat->id
            : 'object:'.spl_object_id($seat);
    }

    /** @return array{seats: Collection, seat_ids: array<int>, seat_code: string, label: string, type: string, is_couple: bool, is_valid: bool} */
    private static function group(
        Collection $seats,
        string $seatCode,
        string $label,
        bool $isCouple,
        bool $isValid,
    ): array {
        return [
            'seats' => $seats,
            'seat_ids' => $seats->pluck('id')->filter()->map(fn ($id): int => (int) $id)->values()->all(),
            'seat_code' => $seatCode,
            'label' => $label,
            'type' => $isCouple ? 'couple' : (string) ($seats->first()?->type ?? 'normal'),
            'is_couple' => $isCouple,
            'is_valid' => $isValid,
        ];
    }
}
