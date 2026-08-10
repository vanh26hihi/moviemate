<?php

namespace Tests\Feature\Showtimes;

use App\Models\Showtime;
use App\Services\ShowtimeLifecycleService;
use Carbon\CarbonImmutable;

class ShowtimeLifecycleTest extends ShowtimeTestCase
{
    public function test_exact_lifecycle_and_booking_cutoff_boundaries_use_movie_end_not_room_readiness(): void
    {
        $showtime = $this->existing($this->movie(90), $this->rooms['P01'], [
            'show_date' => '2026-08-11',
            'show_time' => '09:00:00',
        ]);
        $service = app(ShowtimeLifecycleService::class);

        foreach ([
            ['2026-08-11 08:59:59', ShowtimeLifecycleService::UPCOMING, true],
            ['2026-08-11 09:00:00', ShowtimeLifecycleService::PLAYING, true],
            ['2026-08-11 09:14:59', ShowtimeLifecycleService::PLAYING, true],
            ['2026-08-11 09:15:00', ShowtimeLifecycleService::PLAYING, false],
            ['2026-08-11 10:29:59', ShowtimeLifecycleService::PLAYING, false],
            ['2026-08-11 10:30:00', ShowtimeLifecycleService::COMPLETED, false],
            ['2026-08-11 10:30:01', ShowtimeLifecycleService::COMPLETED, false],
        ] as [$now, $expectedState, $expectedBookable]) {
            $current = CarbonImmutable::parse($now, 'Asia/Ho_Chi_Minh');
            $this->assertSame($expectedState, $service->state($showtime, $current), $now);
            $this->assertSame($expectedBookable, $service->isCustomerBookingOpen($showtime, $current), $now);
        }

        $snapshot = $service->snapshot($showtime, CarbonImmutable::parse('2026-08-11 10:35:00', 'Asia/Ho_Chi_Minh'));
        $this->assertSame('2026-08-11 10:30:00', $snapshot['ends_at']->format('Y-m-d H:i:s'));
        $this->assertSame($snapshot['ends_at']->toIso8601String(), $snapshot['cleaning_starts_at']->toIso8601String());
        $this->assertSame('2026-08-11 10:45:00', $snapshot['room_ready_at']->format('Y-m-d H:i:s'));
        $this->assertSame(ShowtimeLifecycleService::COMPLETED, $snapshot['state']);
    }

    public function test_cancelled_precedence_wins_for_future_playing_and_historical_showtimes(): void
    {
        $showtime = $this->existing($this->movie(90), $this->rooms['P01'], [
            'show_date' => '2026-08-11',
            'show_time' => '09:00:00',
            'status' => 'cancelled',
        ]);
        $service = app(ShowtimeLifecycleService::class);

        foreach (['08:00:00', '09:05:00', '11:00:00'] as $time) {
            $now = CarbonImmutable::parse("2026-08-11 {$time}", 'Asia/Ho_Chi_Minh');
            $this->assertSame(ShowtimeLifecycleService::CANCELLED, $service->state($showtime, $now));
            $this->assertFalse($service->isCustomerBookingOpen($showtime, $now));
        }
    }

    public function test_cross_midnight_uses_next_day_movie_end_and_independent_cutoff(): void
    {
        $showtime = $this->existing($this->movie(120), $this->rooms['P01'], [
            'show_date' => '2026-08-11',
            'show_time' => '23:30:00',
        ]);
        $snapshot = app(ShowtimeLifecycleService::class)->snapshot(
            $showtime,
            CarbonImmutable::parse('2026-08-12 00:30:00', 'Asia/Ho_Chi_Minh'),
        );

        $this->assertSame('2026-08-12 01:30:00', $snapshot['ends_at']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-11 23:45:00', $snapshot['booking_closes_at']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-12 01:45:00', $snapshot['room_ready_at']->format('Y-m-d H:i:s'));
        $this->assertSame(ShowtimeLifecycleService::PLAYING, $snapshot['state']);
        $this->assertFalse(app(ShowtimeLifecycleService::class)->isCustomerBookingOpen($showtime, $snapshot['now']));
    }

    public function test_admin_index_renders_and_filters_derived_lifecycle_states_at_frozen_time(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-11 00:37:00', 'Asia/Ho_Chi_Minh'));

        try {
            $completed = $this->namedShowtime('Historical 09 August', '2026-08-09', '16:30:00');
            $playing = $this->namedShowtime('Playing lifecycle', '2026-08-11', '00:00:00');
            $upcoming = $this->namedShowtime('Upcoming lifecycle', '2026-08-11', '12:00:00');
            $cancelled = $this->namedShowtime('Cancelled lifecycle', '2026-08-11', '13:00:00', 'cancelled');
            $admin = $this->userWithRole('admin');

            $this->actingAs($admin)->get(route('admin.showtimes.index'))
                ->assertOk()
                ->assertSee('Sắp chiếu')
                ->assertSee('Đang chiếu')
                ->assertSee('Đã chiếu xong')
                ->assertSee('Đã hủy')
                ->assertSee('Historical 09 August')
                ->assertDontSee('Đang hoạt động');

            foreach ([
                'upcoming' => $upcoming,
                'playing' => $playing,
                'completed' => $completed,
                'cancelled' => $cancelled,
            ] as $filter => $expected) {
                $response = $this->actingAs($admin)->get(route('admin.showtimes.index', ['lifecycle' => $filter]))->assertOk();
                $this->assertSame([$expected->id], $response->viewData('showtimes')->getCollection()->pluck('id')->all(), $filter);
            }
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    private function namedShowtime(string $title, string $date, string $time, string $status = 'active'): Showtime
    {
        return $this->existing($this->movie(90, ['title' => $title]), $this->rooms['P01'], [
            'show_date' => $date,
            'show_time' => $time,
            'status' => $status,
        ]);
    }
}
