<?php

namespace App\Services\Seats;

use App\Models\RoomLayout;
use App\Models\RoomLayoutCell;
use Illuminate\Support\Collection;

/**
 * Server-authoritative "no isolated single seat" rule.
 *
 * Physical adjacency is derived from the published layout cells (x_position / y_position),
 * never from seat codes or seat numbers. A contiguous run ends at an aisle, a structural
 * empty coordinate, a non-seat cell, or the edge of the row, so H12 and H13 are NOT adjacent
 * when an aisle sits between them.
 *
 * Compatibility rule: the policy only rejects orphans that the CURRENT selection introduces.
 * Availability is compared before and after the proposed selection, so a pre-existing orphan
 * (from history, maintenance or another transaction) never blocks an unrelated selection, and
 * selecting that already-isolated seat is allowed because it removes the orphan.
 */
final class SeatSelectionPolicy
{
    public const MESSAGE_ISOLATED_SEAT = 'Không thể chọn ghế này vì lựa chọn hiện tại sẽ để trống một ghế đơn trong hàng. Vui lòng chọn các ghế liền nhau.';

    /**
     * @param  Collection<int, RoomLayoutCell>|null  $cells  pre-loaded layout cells, when available
     * @param  array<int, bool>|Collection<int, int>  $unavailableSeatIds  sold / held / maintenance seats
     * @param  array<int, int>|Collection<int, int>  $selectedSeatIds  seats the customer wants now
     */
    public function violates(
        RoomLayout $layout,
        iterable $unavailableSeatIds,
        iterable $selectedSeatIds,
        ?Collection $cells = null,
    ): bool {
        return $this->newlyIsolatedSeatIds($layout, $unavailableSeatIds, $selectedSeatIds, $cells) !== [];
    }

    public function violationMessage(
        RoomLayout $layout,
        iterable $unavailableSeatIds,
        iterable $selectedSeatIds,
        ?Collection $cells = null,
    ): ?string {
        $orphanId = $this->newlyIsolatedSeatIds(
            $layout,
            $unavailableSeatIds,
            $selectedSeatIds,
            $cells,
        )[0] ?? null;

        if ($orphanId === null) {
            return null;
        }

        $cells ??= $layout->relationLoaded('cells')
            ? $layout->cells
            : $layout->cells()->with('seat')->get();
        $seat = $cells->firstWhere('seat_id', $orphanId)?->seat;
        $seatCode = is_string($seat?->seat_code) ? trim($seat->seat_code) : '';
        $row = is_string($seat?->row) ? trim($seat->row) : '';

        return $seatCode !== '' && $row !== ''
            ? "Không thể tiếp tục vì ghế {$seatCode} sẽ bị bỏ trống một mình trong hàng {$row}."
            : self::MESSAGE_ISOLATED_SEAT;
    }

    /**
     * Seats that become isolated *because of* this selection.
     *
     * @return list<int>
     */
    public function newlyIsolatedSeatIds(
        RoomLayout $layout,
        iterable $unavailableSeatIds,
        iterable $selectedSeatIds,
        ?Collection $cells = null,
    ): array {
        $segments = $this->rowSegments($layout, $cells);
        $blocked = $this->toIdSet($unavailableSeatIds);
        $selected = $this->toIdSet($selectedSeatIds);

        // A seat already unavailable cannot also be "selected" for gap purposes.
        $after = $blocked + $selected;

        $before = $this->isolatedSeatIds($segments, $blocked);
        $afterIsolated = $this->isolatedSeatIds($segments, $after);

        return array_values(array_diff($afterIsolated, $before));
    }

    /**
     * Isolated = an available seat whose immediate physical neighbours inside its own
     * contiguous run are all unavailable (including the run boundaries / walls).
     *
     * @param  list<list<int>>  $segments
     * @param  array<int, bool>  $unavailable
     * @return list<int>
     */
    private function isolatedSeatIds(array $segments, array $unavailable): array
    {
        $isolated = [];

        foreach ($segments as $segment) {
            $length = count($segment);

            for ($index = 0; $index < $length; $index++) {
                $seatId = $segment[$index];
                if (isset($unavailable[$seatId])) {
                    continue;
                }

                $leftBlocked = $index === 0 || isset($unavailable[$segment[$index - 1]]);
                $rightBlocked = $index === $length - 1 || isset($unavailable[$segment[$index + 1]]);

                if ($leftBlocked && $rightBlocked) {
                    $isolated[] = $seatId;
                }
            }
        }

        return $isolated;
    }

    /**
     * Build contiguous physical runs per row from layout coordinates.
     *
     * Only bookable seat cells participate. A run breaks on an aisle cell, a missing
     * coordinate (sparse/irregular layouts) or any non-seat cell.
     *
     * @return list<list<int>>
     */
    private function rowSegments(RoomLayout $layout, ?Collection $cells = null): array
    {
        $cells ??= $layout->relationLoaded('cells')
            ? $layout->cells
            : $layout->cells()->with('seat')->get();

        $rows = [];
        foreach ($cells as $cell) {
            $rows[(int) $cell->y_position][(int) $cell->x_position] = $cell;
        }

        $segments = [];
        foreach ($rows as $row) {
            ksort($row);
            $current = [];
            $previousX = null;

            foreach ($row as $x => $cell) {
                $seatId = $cell->cell_type === 'seat' ? (int) $cell->seat_id : 0;
                $isBreak = $seatId === 0
                    || ($previousX !== null && $x !== $previousX + 1);

                if ($isBreak && $current !== []) {
                    $segments[] = $current;
                    $current = [];
                }

                if ($seatId === 0) {
                    $previousX = $x;

                    continue;
                }

                $current[] = $seatId;
                $previousX = $x;
            }

            if ($current !== []) {
                $segments[] = $current;
            }
        }

        return $segments;
    }

    /** @return array<int, bool> */
    private function toIdSet(iterable $ids): array
    {
        $set = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $set[$id] = true;
            }
        }

        return $set;
    }
}
