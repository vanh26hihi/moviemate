<?php

namespace Tests\Feature\Showtimes;

use App\Services\RoomLayoutService;
use Illuminate\Support\Facades\DB;

class ShowtimeUpdateTest extends ShowtimeTestCase
{
    public function test_unchanged_update_does_not_conflict_with_itself_and_keeps_layout_version(): void
    {
        $movie = $this->movie(120);
        $room = $this->rooms['P01'];
        $showtime = $this->existing($movie, $room);
        $pinnedLayout = $showtime->room_layout_id;
        $layouts = app(RoomLayoutService::class);
        $latest = $layouts->publish($layouts->clonePublishedToDraft($room));

        $this->actingAs($this->userWithRole('admin'))->put(route('admin.showtimes.update', $showtime), $this->payload($movie, $room))
            ->assertRedirect(route('admin.showtimes.index'))->assertSessionHasNoErrors();

        $showtime->refresh();
        $this->assertSame($pinnedLayout, $showtime->room_layout_id);
        $this->assertNotSame($latest->id, $showtime->room_layout_id);
    }

    public function test_update_to_free_time_succeeds(): void
    {
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];
        $showtime = $this->existing($movie, $room);

        $this->actingAs($this->userWithRole('manager'))->put(route('admin.showtimes.update', $showtime), $this->payload($movie, $room, [
            'show_time' => '21:00', 'price' => 95000,
        ]))->assertRedirect(route('admin.showtimes.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('showtimes', ['id' => $showtime->id, 'show_time' => '21:00:00', 'price' => 95000]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'showtime.updated', 'subject_id' => (string) $showtime->id]);
    }

    public function test_delete_records_a_showtime_cancellation_event(): void
    {
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];
        $showtime = $this->existing($movie, $room);

        $this->actingAs($this->userWithRole('admin'))
            ->delete(route('admin.showtimes.destroy', $showtime))
            ->assertRedirect(route('admin.showtimes.index'));

        $this->assertDatabaseMissing('showtimes', ['id' => $showtime->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'showtime.cancelled', 'subject_id' => (string) $showtime->id]);
    }

    public function test_conflicting_update_rolls_back_every_field(): void
    {
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];
        $showtime = $this->existing($movie, $room, ['show_time' => '10:00:00', 'price' => 80000]);
        $this->existing($movie, $room, ['show_time' => '18:00:00']);
        $before = $showtime->getAttributes();

        $this->actingAs($this->userWithRole('admin'))->put(route('admin.showtimes.update', $showtime), $this->payload($movie, $room, [
            'show_time' => '18:30', 'price' => 999999, 'status' => 'finished',
        ]))->assertSessionHasErrors('show_time');

        $after = $showtime->fresh()->getAttributes();
        foreach (['movie_id', 'room_id', 'room_layout_id', 'show_date', 'show_time', 'price', 'status'] as $field) {
            $this->assertSame((string) $before[$field], (string) $after[$field]);
        }
    }

    public function test_changing_to_longer_movie_can_conflict_and_shorter_movie_can_succeed(): void
    {
        $short = $this->movie(30);
        $long = $this->movie(180);
        $room = $this->rooms['P01'];
        $showtime = $this->existing($short, $room, ['show_time' => '18:00:00']);
        $this->existing($short, $room, ['show_time' => '19:30:00']);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->put(route('admin.showtimes.update', $showtime), $this->payload($long, $room))
            ->assertSessionHasErrors('show_time');
        $this->assertSame($short->id, $showtime->fresh()->movie_id);

        $this->actingAs($admin)->put(route('admin.showtimes.update', $showtime), $this->payload($short, $room, ['show_time' => '18:30']))
            ->assertRedirect(route('admin.showtimes.index'))->assertSessionHasNoErrors();
        $this->assertSame('18:30:00', $showtime->fresh()->show_time);
    }

    public function test_room_change_uses_target_latest_layout_and_ignores_client_layout(): void
    {
        $movie = $this->movie(90);
        $source = $this->rooms['P01'];
        $target = $this->rooms['P02'];
        $showtime = $this->existing($movie, $source);
        $layouts = app(RoomLayoutService::class);
        $targetLatest = $layouts->publish($layouts->clonePublishedToDraft($target));

        $this->actingAs($this->userWithRole('admin'))->put(route('admin.showtimes.update', $showtime), [
            ...$this->payload($movie, $target),
            'room_layout_id' => $source->latestPublishedLayout()->firstOrFail()->id,
        ])->assertRedirect(route('admin.showtimes.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('showtimes', [
            'id' => $showtime->id,
            'room_id' => $target->id,
            'room_layout_id' => $targetLatest->id,
        ]);
    }

    public function test_room_change_to_conflicting_room_is_rejected_and_rolled_back(): void
    {
        $movie = $this->movie(90);
        $source = $this->rooms['P01'];
        $target = $this->rooms['P02'];
        $showtime = $this->existing($movie, $source);
        $this->existing($movie, $target);
        $originalLayout = $showtime->room_layout_id;

        $this->actingAs($this->userWithRole('admin'))->put(route('admin.showtimes.update', $showtime), $this->payload($movie, $target, [
            'price' => 999999,
        ]))->assertSessionHasErrors('show_time');

        $showtime->refresh();
        $this->assertSame($source->id, $showtime->room_id);
        $this->assertSame($originalLayout, $showtime->room_layout_id);
        $this->assertSame('80000.00', $showtime->price);
    }

    public function test_room_change_locks_both_rooms_in_stable_id_order_before_showtime(): void
    {
        $movie = $this->movie(90);
        $source = $this->rooms['P02'];
        $target = $this->rooms['P01'];
        $showtime = $this->existing($movie, $source);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = ['sql' => $query->sql, 'bindings' => $query->bindings];
        });

        $this->actingAs($this->userWithRole('admin'))->put(route('admin.showtimes.update', $showtime), $this->payload($movie, $target, [
            'show_time' => '21:00',
        ]))->assertRedirect(route('admin.showtimes.index'));

        $roomLock = collect($queries)->first(fn (array $query) => str_contains($query['sql'], 'from "rooms"')
            && str_contains($query['sql'], 'where "id" in') && str_contains($query['sql'], 'order by "id" asc'));
        $showtimeLockIndex = collect($queries)->search(fn (array $query) => str_contains($query['sql'], 'from "showtimes"')
            && str_contains($query['sql'], 'where "showtimes"."id"'));
        $roomLockIndex = collect($queries)->search(fn (array $query) => $query === $roomLock);

        $this->assertNotNull($roomLock);
        $this->assertSame([$target->id, $source->id], $roomLock['bindings']);
        $this->assertIsInt($showtimeLockIndex);
        $this->assertLessThan($showtimeLockIndex, $roomLockIndex);
    }
}
