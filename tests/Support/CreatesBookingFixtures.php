<?php

namespace Tests\Support;

use App\Models\Booking;
use App\Models\Cinema;
use App\Models\CinemaPricingRule;
use App\Models\Movie;
use App\Models\PresentationFormat;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\RoomLayoutCell;
use App\Models\Seat;
use App\Models\Showtime;
use App\Services\BookingCheckoutResult;
use App\Services\BookingCheckoutService;
use App\Services\BookingTokenService;
use App\Services\CinemaContext;

trait CreatesBookingFixtures
{
    protected function bookingScenario(bool $withCouple = true): array
    {
        $cinema = Cinema::query()->where('canonical_key', CinemaContext::CANONICAL_KEY)->firstOrFail();
        $room = Room::query()->create([
            'cinema_id' => $cinema->id,
            'code' => 'T'.str()->upper(str()->random(7)),
            'name' => 'Test booking room',
            'room_type' => '2D',
            'total_seats' => $withCouple ? 4 : 2,
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

        $layout = RoomLayout::query()->create([
            'room_id' => $room->id,
            'version' => 1,
            'name' => 'Test layout',
            'rows' => 2,
            'columns' => 4,
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
        $layout->update(['status' => 'published', 'published_at' => now()]);

        foreach ([
            ['name' => 'Booking fixture base', 'rule_type' => 'base', 'seat_type' => null, 'amount_vnd' => 50000],
            ['name' => 'Booking fixture VIP', 'rule_type' => 'seat_type', 'seat_type' => 'vip', 'amount_vnd' => 20000],
            ['name' => 'Booking fixture couple', 'rule_type' => 'seat_type', 'seat_type' => 'couple', 'amount_vnd' => 50000],
        ] as $rule) {
            CinemaPricingRule::query()->updateOrCreate(
                ['name' => $rule['name'], 'cinema_id' => $cinema->id],
                [
                    'rule_type' => $rule['rule_type'],
                    'room_id' => null,
                    'seat_type' => $rule['seat_type'],
                    'amount_vnd' => $rule['amount_vnd'],
                    'priority' => 1000,
                    'status' => 'active',
                ],
            );
        }

        $showtime = Showtime::query()->create([
            'movie_id' => $movie->id,
            'cinema_id' => $cinema->id,
            'room_id' => $room->id,
            'room_layout_id' => $layout->id,
            'presentation_format_id' => $format->id,
            'show_date' => now()->addDays(5)->toDateString(),
            'show_time' => '19:00:00',
            'price' => 50000,
            'vip_price' => 70000,
            'pricing_version' => 'cinema-pricing-v1',
            'status' => 'active',
        ]);

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
