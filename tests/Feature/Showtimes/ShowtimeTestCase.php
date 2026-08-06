<?php

namespace Tests\Feature\Showtimes;

use App\Models\Cinema;
use App\Models\CinemaPricingRule;
use App\Models\Movie;
use App\Models\Room;
use App\Models\Showtime;
use App\Services\CinemaContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class ShowtimeTestCase extends TestCase
{
    use RefreshDatabase;

    protected Cinema $cinema;

    /** @var Collection<string, Room> */
    protected $rooms;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cinema.timezone', 'Asia/Ho_Chi_Minh');
        config()->set('cinema.showtime_cleaning_buffer_minutes', 15);
        $this->seedRbac();
        $this->cinema = Cinema::query()->where('canonical_key', CinemaContext::CANONICAL_KEY)->firstOrFail();
        CinemaPricingRule::query()->create([
            'name' => 'Giá cơ bản kiểm thử', 'rule_type' => 'base', 'cinema_id' => $this->cinema->id,
            'amount_vnd' => 80_000, 'priority' => 100, 'status' => 'active',
        ]);
        CinemaPricingRule::query()->create([
            'name' => 'Phụ thu VIP kiểm thử', 'rule_type' => 'seat_type', 'cinema_id' => $this->cinema->id,
            'seat_type' => 'vip', 'amount_vnd' => 30_000, 'priority' => 100, 'status' => 'active',
        ]);
        CinemaPricingRule::query()->create([
            'name' => 'Phụ thu ghế đôi kiểm thử', 'rule_type' => 'seat_type', 'cinema_id' => $this->cinema->id,
            'seat_type' => 'couple', 'amount_vnd' => 80_000, 'priority' => 100, 'status' => 'active',
        ]);

        foreach (['P01', 'P02', 'P03'] as $index => $code) {
            Room::query()->create([
                'cinema_id' => $this->cinema->id,
                'code' => $code,
                'name' => 'Phòng '.($index + 1),
                'room_type' => '2D',
                'total_seats' => 0,
                'status' => 'active',
            ]);
        }

        $this->artisan('moviemate:rebuild-seat-layouts', ['--force' => true])->assertSuccessful();
        $this->rooms = Room::query()->whereIn('code', ['P01', 'P02', 'P03'])->get()->keyBy('code');
    }

    protected function movie(int $duration = 120, array $attributes = []): Movie
    {
        return Movie::query()->create([
            'title' => 'Phim '.Str::lower(Str::random(8)),
            'slug' => 'movie-'.Str::lower(Str::random(12)),
            'duration' => $duration,
            'age_rating' => 'P',
            'status' => 'now_showing',
            ...$attributes,
        ]);
    }

    protected function payload(Movie $movie, Room $room, array $overrides = []): array
    {
        return [
            'movie_id' => $movie->id,
            'room_id' => $room->id,
            'show_date' => '2030-06-10',
            'show_time' => '18:00',
            'price' => 80000,
            'vip_price' => 110000,
            'status' => 'active',
            ...$overrides,
        ];
    }

    protected function existing(Movie $movie, Room $room, array $overrides = []): Showtime
    {
        return Showtime::query()->create([
            'movie_id' => $movie->id,
            'cinema_id' => $this->cinema->id,
            'room_id' => $room->id,
            'room_layout_id' => $room->latestPublishedLayout()->firstOrFail()->id,
            'show_date' => '2030-06-10',
            'show_time' => '18:00:00',
            'price' => 80000,
            'vip_price' => 110000,
            'status' => 'active',
            ...$overrides,
        ]);
    }
}
