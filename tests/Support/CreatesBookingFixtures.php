<?php

namespace Tests\Support;

use App\Models\Booking;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\PresentationFormat;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\RoomLayoutCell;
use App\Models\RoomType;
use App\Models\Seat;
use App\Models\Showtime;
use App\Services\BookingCheckoutResult;
use App\Services\BookingCheckoutService;
use App\Services\BookingTokenService;
use App\Services\CinemaContext;

trait CreatesBookingFixtures
{
    use CreatesPriceBookFixtures;

    protected function bookingScenario(
        bool $withCouple = true,
        array $extraLayoutCells = [],
        int $layoutRows = 2,
        int $layoutColumns = 4,
        array $extraSeats = [],
        int $basePrice = 50_000,
    ): array {
        $cinema = Cinema::query()->where('canonical_key', CinemaContext::CANONICAL_KEY)->firstOrFail();
        $roomType = RoomType::query()->firstOrCreate(['code' => '2D'], [
            'name' => '2D', 'slug' => '2d', 'is_active' => true, 'status' => true, 'sort_order' => 1,
        ]);
        $room = Room::query()->create([
            'cinema_id' => $cinema->id,
            'code' => 'T'.str()->upper(str()->random(7)),
            'name' => 'Test booking room',
            'room_type' => '2D',
            'room_type_id' => $roomType->id,
            'width_mm' => 8_000,
            'length_mm' => 10_000,
            'status' => 'active',
        ]);
        $movie = Movie::query()->create([
            'title' => 'Booking Foundation Movie',
            'slug' => 'booking-foundation-'.str()->lower(str()->random(8)),
            'duration' => 100,
            'status' => 'now_showing',
        ]);
        $format = PresentationFormat::query()->firstOrCreate(['code' => 'TEST_2D'], [
            'name' => 'Test 2D',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $movie->supportedPresentationFormats()->attach($format);
        $room->presentationCapabilities()->attach($format);
        $seats = collect([
            Seat::query()->create([
                'room_id' => $room->id, 'row' => 'A', 'number' => 1,
                'seat_code' => 'A1', 'type' => 'normal', 'status' => 'active',
            ]),
            Seat::query()->create([
                'room_id' => $room->id, 'row' => 'A', 'number' => 2,
                'seat_code' => 'A2', 'type' => 'normal', 'status' => 'maintenance',
            ]),
        ]);

        if ($withCouple) {
            $seats->push(
                Seat::query()->create([
                    'room_id' => $room->id, 'row' => 'B', 'number' => 1,
                    'seat_code' => 'B1', 'type' => 'couple', 'status' => 'active',
                    'pair_code' => 'B-PAIR-1', 'pair_position' => 'left',
                ]),
                Seat::query()->create([
                    'room_id' => $room->id, 'row' => 'B', 'number' => 2,
                    'seat_code' => 'B2', 'type' => 'couple', 'status' => 'active',
                    'pair_code' => 'B-PAIR-1', 'pair_position' => 'right',
                ]),
            );
        }
        foreach ($extraSeats as $attributes) {
            $seats->push(Seat::query()->create([
                'room_id' => $room->id,
                'row' => $attributes['row'],
                'number' => $attributes['number'],
                'seat_code' => $attributes['seat_code'],
                'type' => $attributes['type'] ?? 'normal',
                'status' => $attributes['status'] ?? 'active',
                'pair_code' => $attributes['pair_code'] ?? null,
                'pair_position' => $attributes['pair_position'] ?? null,
            ]));
        }
        $seats->each(fn (Seat $seat) => $this->assignLogicalSeatType($seat));

        $layout = RoomLayout::query()->create([
            'room_id' => $room->id,
            'version' => 1,
            'name' => 'Test layout',
            'rows' => $layoutRows,
            'columns' => $layoutColumns,
            'status' => 'draft',
        ]);
        foreach ($seats as $index => $seat) {
            RoomLayoutCell::query()->create([
                'room_layout_id' => $layout->id,
                'x_position' => $index + 1,
                'y_position' => 1,
                'cell_type' => 'seat',
                'seat_id' => $seat->id,
            ]);
        }
        foreach ($extraLayoutCells as $cell) {
            RoomLayoutCell::query()->create([
                'room_layout_id' => $layout->id,
                'x_position' => $cell['x_position'],
                'y_position' => $cell['y_position'],
                'cell_type' => $cell['cell_type'],
                'seat_id' => $cell['seat_id'] ?? null,
            ]);
        }
        $layout->update(['status' => 'published', 'published_at' => now()]);
        $this->ensurePublishedPriceBook($basePrice);

        $showtime = Showtime::query()->create([
            'movie_id' => $movie->id,
            'cinema_id' => $cinema->id,
            'room_id' => $room->id,
            'room_layout_id' => $layout->id,
            'presentation_format_id' => $format->id,
            'show_date' => now()->addDays(5)->toDateString(),
            'show_time' => '19:00:00',
            'status' => 'active',
        ]);
        $this->snapshotShowtime($showtime);

        return compact('cinema', 'room', 'movie', 'seats', 'layout', 'showtime');
    }

    protected function reserve(array $scenario, array $seatIds, ?int $userId = null, ?string $token = null): BookingCheckoutResult
    {
        $token ??= app(BookingTokenService::class)->issueCheckoutToken();

        return app(BookingCheckoutService::class)->createPendingBooking(
            $scenario['showtime']->id,
            $seatIds,
            $userId,
            'guest@example.test',
            $token,
        );
    }

    protected function bookingForScenario(array $scenario, array $overrides = []): Booking
    {
        return Booking::query()->create(array_merge([
            'user_id' => null,
            'customer_email' => 'guest@example.test',
            'showtime_id' => $scenario['showtime']->id,
            'booking_code' => 'TEST-'.str()->upper(str()->random(12)),
            'total_amount' => 50000,
            'payment_status' => 'unpaid',
            'booking_status' => 'pending_payment',
            'expires_at' => now()->addMinutes(15),
        ], $overrides));
    }
}
