<?php

namespace Tests\Feature\Showtimes;

use App\Models\Showtime;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ShowtimeOverlapTest extends ShowtimeTestCase
{
    public function test_exact_operational_end_boundary_and_later_time_are_allowed(): void
    {
        $existingMovie = $this->movie(120);
        $newMovie = $this->movie(30);
        $room = $this->rooms['P01'];
        $this->existing($existingMovie, $room);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->post(route('admin.showtimes.store'), $this->payload($newMovie, $room, [
            'show_time' => '20:15',
        ]))->assertRedirect(route('admin.showtimes.index'))->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.showtimes.store'), $this->payload($newMovie, $room, [
            'show_date' => '2030-06-11', 'show_time' => '09:00',
        ]))->assertRedirect(route('admin.showtimes.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('showtimes', ['room_id' => $room->id, 'show_time' => '20:15:00']);
        $this->assertDatabaseCount('showtimes', 3);
    }

    public function test_one_minute_early_exact_start_and_contained_windows_are_rejected(): void
    {
        $existingMovie = $this->movie(120);
        $newMovie = $this->movie(30);
        $room = $this->rooms['P01'];
        $admin = $this->userWithRole('admin');

        foreach (['20:14', '18:00', '19:00'] as $index => $time) {
            $date = '2030-06-'.str_pad((string) (10 + $index), 2, '0', STR_PAD_LEFT);
            $this->existing($existingMovie, $room, ['show_date' => $date]);
            $response = $this->actingAs($admin)->from(route('admin.showtimes.create'))
                ->post(route('admin.showtimes.store'), $this->payload($newMovie, $room, [
                    'show_date' => $date, 'show_time' => $time,
                ]));

            $response->assertRedirect(route('admin.showtimes.create'))->assertSessionHasErrors('show_time');
            $this->assertSame(1, Showtime::query()->whereDate('show_date', $date)->count());
        }
    }

    public function test_containing_head_and_tail_overlaps_are_rejected(): void
    {
        $room = $this->rooms['P01'];
        $admin = $this->userWithRole('admin');
        $shortMovie = $this->movie(30);
        $longMovie = $this->movie(180);

        $this->existing($shortMovie, $room, ['show_date' => '2030-06-13', 'show_time' => '19:00:00']);
        $this->actingAs($admin)->post(route('admin.showtimes.store'), $this->payload($longMovie, $room, [
            'show_date' => '2030-06-13', 'show_time' => '18:00',
        ]))->assertSessionHasErrors('show_time');

        $this->existing($shortMovie, $room, ['show_date' => '2030-06-14', 'show_time' => '18:00:00']);
        $this->actingAs($admin)->post(route('admin.showtimes.store'), $this->payload($shortMovie, $room, [
            'show_date' => '2030-06-14', 'show_time' => '17:30',
        ]))->assertSessionHasErrors('show_time');

        $this->existing($shortMovie, $room, ['show_date' => '2030-06-15', 'show_time' => '18:00:00']);
        $this->actingAs($admin)->post(route('admin.showtimes.store'), $this->payload($shortMovie, $room, [
            'show_date' => '2030-06-15', 'show_time' => '18:30',
        ]))->assertSessionHasErrors('show_time');

        $this->assertDatabaseCount('showtimes', 3);
    }

    public function test_new_operational_end_equal_to_existing_start_is_allowed(): void
    {
        $room = $this->rooms['P01'];
        $existingMovie = $this->movie(60);
        $earlierMovie = $this->movie(45);
        $this->existing($existingMovie, $room, ['show_time' => '18:00:00']);

        $this->actingAs($this->userWithRole('admin'))->post(route('admin.showtimes.store'), $this->payload($earlierMovie, $room, [
            'show_time' => '17:00',
        ]))->assertRedirect(route('admin.showtimes.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('showtimes', ['movie_id' => $earlierMovie->id, 'show_time' => '17:00:00']);
    }

    public function test_same_time_is_allowed_in_a_different_room(): void
    {
        $movie = $this->movie(120);
        $this->existing($movie, $this->rooms['P01']);

        $this->actingAs($this->userWithRole('admin'))->post(route('admin.showtimes.store'), $this->payload($movie, $this->rooms['P02']))
            ->assertRedirect(route('admin.showtimes.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('showtimes', 2);
        $this->assertDatabaseHas('showtimes', [
            'room_id' => $this->rooms['P02']->id,
            'room_layout_id' => $this->rooms['P02']->latestPublishedLayout()->firstOrFail()->id,
        ]);
    }

    public function test_cancelled_showtime_does_not_occupy_the_room_and_history_is_kept(): void
    {
        $movie = $this->movie(120);
        $room = $this->rooms['P01'];
        $cancelled = $this->existing($movie, $room, ['status' => 'cancelled']);

        $this->actingAs($this->userWithRole('admin'))->post(route('admin.showtimes.store'), $this->payload($movie, $room))
            ->assertRedirect(route('admin.showtimes.index'))->assertSessionHasNoErrors();
        $this->actingAs($this->userWithRole('admin'))->post(route('admin.showtimes.store'), $this->payload($movie, $room, [
            'status' => 'cancelled',
        ]))->assertRedirect(route('admin.showtimes.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('showtimes', ['id' => $cancelled->id, 'status' => 'cancelled']);
        $this->assertDatabaseCount('showtimes', 3);
    }

    public function test_previous_day_occupancy_blocks_after_midnight_and_boundary_is_allowed(): void
    {
        $movie = $this->movie(120);
        $shortMovie = $this->movie(30);
        $room = $this->rooms['P01'];
        $this->existing($movie, $room, ['show_date' => '2030-06-10', 'show_time' => '23:30:00']);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->post(route('admin.showtimes.store'), $this->payload($shortMovie, $room, [
            'show_date' => '2030-06-11', 'show_time' => '01:44',
        ]))->assertSessionHasErrors('show_time');
        $this->actingAs($admin)->post(route('admin.showtimes.store'), $this->payload($shortMovie, $room, [
            'show_date' => '2030-06-11', 'show_time' => '01:45',
        ]))->assertRedirect(route('admin.showtimes.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('showtimes', 2);
    }

    public function test_new_window_crossing_midnight_conflicts_with_next_day_existing_showtime(): void
    {
        $movie = $this->movie(120);
        $room = $this->rooms['P01'];
        $this->existing($movie, $room, ['show_date' => '2030-06-11', 'show_time' => '01:30:00']);

        $this->actingAs($this->userWithRole('admin'))->post(route('admin.showtimes.store'), $this->payload($movie, $room, [
            'show_date' => '2030-06-10', 'show_time' => '23:30',
        ]))->assertSessionHasErrors('show_time');

        $this->assertDatabaseCount('showtimes', 1);
    }

    public function test_tampered_derived_fields_are_ignored_and_cannot_bypass_overlap(): void
    {
        $movie = $this->movie(120);
        $otherRoom = $this->rooms['P02'];
        $room = $this->rooms['P01'];
        $this->existing($movie, $room);
        $legacyCinema = $this->cinema->replicate(['canonical_key']);
        $legacyCinema->canonical_key = 'legacy-'.uniqid();
        $legacyCinema->code = 'LEGACY-'.str()->upper(str()->random(8));
        $legacyCinema->name = 'Legacy';
        $legacyCinema->is_primary = false;
        $legacyCinema->save();

        $response = $this->actingAs($this->userWithRole('admin'))->post(route('admin.showtimes.store'), [
            ...$this->payload($movie, $room, ['show_time' => '17:00']),
            'runtime' => 1,
            'duration' => 1,
            'end_time' => '17:01',
            'operational_end' => '17:01',
            'cleaning_buffer' => 0,
            'cinema_id' => $legacyCinema->id,
            'room_layout_id' => $otherRoom->latestPublishedLayout()->firstOrFail()->id,
        ]);

        $response->assertSessionHasErrors('show_time');
        $this->assertDatabaseCount('showtimes', 1);
    }

    public function test_insert_occurs_inside_transaction_after_room_and_candidate_queries(): void
    {
        $movie = $this->movie(60);
        $room = $this->rooms['P01'];
        $queries = [];
        $transactionLevel = null;
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        Showtime::creating(function () use (&$transactionLevel): void {
            $transactionLevel = DB::transactionLevel();
        });

        $this->actingAs($this->userWithRole('admin'))->post(route('admin.showtimes.store'), $this->payload($movie, $room))
            ->assertRedirect(route('admin.showtimes.index'));

        $roomQuery = collect($queries)->search(fn (string $sql) => str_contains($sql, 'from "rooms"') && str_contains($sql, 'limit 1'));
        $candidateQuery = collect($queries)->search(fn (string $sql) => str_contains($sql, 'from "showtimes"') && str_contains($sql, 'room_id'));
        $insertQuery = collect($queries)->search(fn (string $sql) => str_starts_with($sql, 'insert into "showtimes"'));
        $this->assertGreaterThan(0, $transactionLevel);
        $this->assertIsInt($roomQuery);
        $this->assertIsInt($candidateQuery);
        $this->assertIsInt($insertQuery);
        $this->assertLessThan($candidateQuery, $roomQuery);
        $this->assertLessThan($insertQuery, $candidateQuery);
    }

    public function test_insert_failure_rolls_back_showtime_row(): void
    {
        $movie = $this->movie(60);
        $room = $this->rooms['P01'];
        Showtime::creating(fn () => throw new RuntimeException('forced insert failure'));

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->userWithRole('admin'))->post(route('admin.showtimes.store'), $this->payload($movie, $room));
            $this->fail('Insert failure must bubble out of the transaction.');
        } catch (RuntimeException $exception) {
            $this->assertSame('forced insert failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('showtimes', 0);
    }
}
