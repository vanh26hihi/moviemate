<?php

namespace Tests\Feature\Showtimes;

class OperatingHoursAndCleaningTest extends ShowtimeTestCase
{
    protected bool $prepareSingleShowtimeFormats = true;

    public function test_operating_hours_reject_before_and_after_latest_start(): void
    {
        $movie = $this->movie(180);
        $room = $this->rooms['P01'];
        $admin = $this->userWithRole('admin');
        $this->cinema->operatingHours()->create([
            'day_of_week' => 1, 'opens_at' => '09:00', 'latest_show_start_at' => '22:30', 'is_closed' => false,
        ]);

        $this->actingAs($admin)->post(route('admin.showtimes.store'), $this->payload($movie, $room, ['show_time' => '08:59']))
            ->assertSessionHasErrors('show_time');
        $this->actingAs($admin)->post(route('admin.showtimes.store'), $this->payload($movie, $room, ['show_time' => '22:31']))
            ->assertSessionHasErrors('show_time');
    }

    public function test_latest_start_is_accepted_even_when_film_finishes_after_midnight(): void
    {
        $movie = $this->movie(180);
        $room = $this->rooms['P01'];
        $admin = $this->userWithRole('admin');
        $this->cinema->operatingHours()->create(['day_of_week' => 1, 'opens_at' => '09:00', 'latest_show_start_at' => '22:30', 'is_closed' => false]);
        $this->actingAs($admin)->post(route('admin.showtimes.store'), $this->payload($movie, $room, ['show_time' => '22:30']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('showtimes', ['show_time' => '22:30:00']);
    }

    public function test_closed_day_is_rejected(): void
    {
        $movie = $this->movie(90);
        $room = $this->rooms['P01'];
        $admin = $this->userWithRole('admin');
        $this->cinema->operatingHours()->create(['day_of_week' => 2, 'is_closed' => true]);
        $this->actingAs($admin)->post(route('admin.showtimes.store'), $this->payload($movie, $room, ['show_date' => '2030-06-11', 'show_time' => '10:00']))
            ->assertSessionHasErrors('show_date');
    }

    public function test_room_buffer_overrides_cinema_default_and_rejects_early_start(): void
    {
        $this->cinema->update(['default_cleaning_buffer_minutes' => 20]);
        $room = $this->rooms['P01'];
        $room->update(['cleaning_buffer_minutes' => 30]);
        $movie = $this->movie(60);
        $this->existing($movie, $room, ['show_time' => '18:00:00']);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->post(route('admin.showtimes.store'), $this->payload($movie, $room, ['show_time' => '19:29']))
            ->assertSessionHasErrors('show_time');
    }

    public function test_exact_room_buffer_boundary_is_available(): void
    {
        $this->cinema->update(['default_cleaning_buffer_minutes' => 20]);
        $room = $this->rooms['P01'];
        $room->update(['cleaning_buffer_minutes' => 30]);
        $movie = $this->movie(60);
        $this->existing($movie, $room, ['show_time' => '18:00:00']);
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->post(route('admin.showtimes.store'), $this->payload($movie, $room, ['show_time' => '19:30']))
            ->assertRedirect(route('admin.showtimes.index'))->assertSessionHasNoErrors();
    }
}
