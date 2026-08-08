<?php

namespace Tests\Unit\Seats;

use App\Models\RoomLayout;
use App\Models\RoomLayoutCell;
use App\Services\Seats\SeatSelectionPolicy;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Pure adjacency/orphan tests. Layout cells are built in memory so the algorithm can be
 * exercised against sparse, irregular and aisle-separated geometry without touching the DB.
 */
final class SeatSelectionPolicyTest extends TestCase
{
    private SeatSelectionPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new SeatSelectionPolicy;
    }

    /**
     * Build one row. "S" = seat (id follows position), "|" = aisle, "." = structural gap.
     *
     * @return Collection<int, RoomLayoutCell>
     */
    private function row(string $pattern, int $y = 1, int $idBase = 0): Collection
    {
        $cells = collect();
        foreach (str_split($pattern) as $index => $token) {
            $x = $index + 1;
            if ($token === '.') {
                continue; // missing coordinate -> structural gap
            }
            $cell = new RoomLayoutCell;
            $cell->x_position = $x;
            $cell->y_position = $y;
            if ($token === '|') {
                $cell->cell_type = 'aisle';
                $cell->seat_id = null;
            } else {
                $cell->cell_type = 'seat';
                $cell->seat_id = $idBase + $x;
            }
            $cells->push($cell);
        }

        return $cells;
    }

    private function layout(): RoomLayout
    {
        return new RoomLayout;
    }

    public function test_internal_one_seat_gap_is_rejected(): void
    {
        // Seats 1..3 free; selecting 1 and 3 strands seat 2.
        $this->assertTrue($this->policy->violates($this->layout(), [], [1, 3], $this->row('SSS')));
    }

    public function test_left_edge_one_seat_remainder_is_rejected(): void
    {
        // Wall | S1 S2 S3 -> selecting 2 and 3 strands seat 1 against the wall.
        $this->assertTrue($this->policy->violates($this->layout(), [], [2, 3], $this->row('SSS')));
    }

    public function test_right_edge_one_seat_remainder_is_rejected(): void
    {
        $this->assertTrue($this->policy->violates($this->layout(), [], [1, 2], $this->row('SSS')));
    }

    public function test_existing_sold_seat_creating_orphan_is_rejected(): void
    {
        // [sold 1] [free 2] [select 3]
        $this->assertTrue($this->policy->violates($this->layout(), [1], [3], $this->row('SSS')));
    }

    public function test_two_remaining_adjacent_seats_are_allowed(): void
    {
        // Four seats, take the first two, leaving a usable pair.
        $this->assertFalse($this->policy->violates($this->layout(), [], [1, 2], $this->row('SSSS')));
    }

    public function test_selecting_the_whole_row_is_allowed(): void
    {
        $this->assertFalse($this->policy->violates($this->layout(), [], [1, 2, 3], $this->row('SSS')));
    }

    public function test_aisle_separates_segments(): void
    {
        // S1 S2 | S4 S5 : taking 1+2 leaves the far segment untouched, which is fine.
        $this->assertFalse($this->policy->violates($this->layout(), [], [1, 2], $this->row('SS|SS')));

        // But stranding seat 1 behind the aisle is still an orphan.
        $this->assertTrue($this->policy->violates($this->layout(), [], [2], $this->row('SS|SS')));
    }

    public function test_structural_gap_separates_segments(): void
    {
        // S1 S2 . S4 S5 -> the missing coordinate breaks adjacency exactly like an aisle.
        $this->assertFalse($this->policy->violates($this->layout(), [], [1, 2], $this->row('SS.SS')));
        $this->assertTrue($this->policy->violates($this->layout(), [], [2], $this->row('SS.SS')));
    }

    public function test_codes_across_an_aisle_are_not_adjacent(): void
    {
        // Consecutive seat numbers across an aisle must not be treated as neighbours.
        // S1 | S3 : selecting S3 leaves S1 alone in its own segment, already isolated
        // before the action, so the selection is permitted.
        $this->assertFalse($this->policy->violates($this->layout(), [], [3], $this->row('S|S')));
    }

    public function test_pre_existing_orphan_does_not_block_an_unrelated_selection(): void
    {
        // Row: S1 S2 S3 | S5 S6 S7 S8. Seat 2 is already stranded by maintenance on 1 and 3,
        // which is a pre-existing orphan the customer did not cause.
        $cells = $this->row('SSS|SSSS');
        $unavailable = [1, 3];

        // Taking 5+6 leaves 7+8 as a usable pair, so nothing new is stranded and the
        // pre-existing orphan on seat 2 must not block this unrelated selection.
        $this->assertFalse($this->policy->violates($this->layout(), $unavailable, [5, 6], $cells));

        // Sanity: the pre-existing orphan really is present in the "before" state.
        $this->assertSame([], $this->policy->newlyIsolatedSeatIds($this->layout(), $unavailable, [5, 6], $cells));
    }

    public function test_selecting_an_already_isolated_seat_is_allowed(): void
    {
        // Taking the stranded seat removes the orphan rather than creating one.
        $this->assertFalse($this->policy->violates($this->layout(), [1, 3], [2], $this->row('SSS')));
    }

    public function test_replacing_an_existing_orphan_with_a_different_orphan_is_rejected(): void
    {
        // Before: seat 2 is isolated by blocked seats 1 and 3. Selecting 2 removes that
        // orphan, but selecting 5 simultaneously creates a new orphan at seat 4. The orphan
        // count remains one, so identity comparison (not count comparison) is required.
        $this->assertSame(
            [4],
            $this->policy->newlyIsolatedSeatIds($this->layout(), [1, 3], [2, 5], $this->row('SSSSS')),
        );
    }

    public function test_worsening_an_existing_invalid_arrangement_is_rejected(): void
    {
        // Row of 5. Seat 1 already sold (seat 2 not yet orphaned since 3 is free).
        // Selecting 3 and 5 strands both seat 2 and seat 4 -> newly created orphans.
        $this->assertTrue($this->policy->violates($this->layout(), [1], [3, 5], $this->row('SSSSS')));
    }

    public function test_irregular_rows_of_different_widths(): void
    {
        $cells = $this->row('SSS', y: 1, idBase: 0)
            ->merge($this->row('SSSSS', y: 2, idBase: 100));

        // Row 2 orphan detection is independent of row 1 width.
        $this->assertTrue($this->policy->violates($this->layout(), [], [101, 103], $cells));
        $this->assertFalse($this->policy->violates($this->layout(), [], [101, 102], $cells));
    }

    public function test_sparse_coordinates_are_supported(): void
    {
        // Only x=4 and x=5 exist in this row.
        $cells = collect();
        foreach ([4, 5] as $x) {
            $cell = new RoomLayoutCell;
            $cell->x_position = $x;
            $cell->y_position = 3;
            $cell->cell_type = 'seat';
            $cell->seat_id = $x;
            $cells->push($cell);
        }

        $this->assertTrue($this->policy->violates($this->layout(), [], [4], $cells));
        $this->assertFalse($this->policy->violates($this->layout(), [], [4, 5], $cells));
    }

    public function test_couple_pair_taken_together_does_not_self_trigger(): void
    {
        // A couple occupies two physical positions; taking both must not look like a gap.
        $this->assertFalse($this->policy->violates($this->layout(), [], [1, 2], $this->row('SSSS')));
    }

    public function test_couple_pair_between_two_usable_normal_pairs_is_allowed(): void
    {
        // Physical seats 3+4 are one authoritative couple pair. Their normal neighbours
        // remain in usable pairs on both sides.
        $this->assertFalse($this->policy->violates($this->layout(), [], [3, 4], $this->row('SSSSSS')));
    }

    public function test_couple_pair_leaving_an_edge_orphan_is_rejected(): void
    {
        $this->assertSame(
            [5],
            $this->policy->newlyIsolatedSeatIds($this->layout(), [], [3, 4], $this->row('SSSSS')),
        );
    }

    public function test_couple_pair_can_expose_an_internal_orphan(): void
    {
        $newOrphans = $this->policy->newlyIsolatedSeatIds(
            $this->layout(),
            [1],
            [2, 3, 5],
            $this->row('SSSSSS'),
        );

        $this->assertContains(4, $newOrphans);
    }

    public function test_newly_isolated_ids_are_reported(): void
    {
        $this->assertSame([2], $this->policy->newlyIsolatedSeatIds($this->layout(), [], [1, 3], $this->row('SSS')));
    }
}
