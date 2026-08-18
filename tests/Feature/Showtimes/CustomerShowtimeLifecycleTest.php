<?php

namespace Tests\Feature\Showtimes;

use App\Models\Movie;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesPublicDiscoveryFixtures;
use Tests\TestCase;

class CustomerShowtimeLifecycleTest extends TestCase
{
    use CreatesPublicDiscoveryFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_discovery_only_displays_customer_bookable_showtimes(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-11 09:14:59', 'Asia/Ho_Chi_Minh'));
        $movie = Movie::query()->create([
            'title' => 'Lifecycle Discovery Movie',
            'slug' => 'lifecycle-discovery-movie',
            'duration' => 90,
            'status' => 'now_showing',
        ]);
        $future = $this->publicScenario('LIFE-FUTURE', 'Future Cinema', '2026-08-11', ['movie' => $movie, 'show_time' => '10:00:00']);
        $playing = $this->publicScenario('LIFE-PLAYING', 'Playing Cinema', '2026-08-11', ['movie' => $movie, 'show_time' => '09:00:00']);
        $closed = $this->publicScenario('LIFE-CLOSED', 'Closed Cinema', '2026-08-11', ['movie' => $movie, 'show_time' => '08:59:00']);
        $completed = $this->publicScenario('LIFE-DONE', 'Completed Cinema', '2026-08-11', ['movie' => $movie, 'show_time' => '07:00:00']);
        $cancelled = $this->publicScenario('LIFE-CANCEL', 'Cancelled Cinema', '2026-08-11', [
            'movie' => $movie,
            'show_time' => '10:30:00',
            'showtime_status' => 'cancelled',
        ]);

        $response = $this->get(route('user.movies.show', ['slug' => $movie->slug, 'date' => '2026-08-11']))->assertOk();
        $response
            ->assertSee(route('user.bookings.selectSeat', ['showtime' => $future['showtime']->id, 'cinema' => $future['cinema']->code]))
            ->assertSee(route('user.bookings.selectSeat', ['showtime' => $playing['showtime']->id, 'cinema' => $playing['cinema']->code]))
            ->assertDontSee(route('user.bookings.selectSeat', ['showtime' => $closed['showtime']->id, 'cinema' => $closed['cinema']->code]))
            ->assertDontSee(route('user.bookings.selectSeat', ['showtime' => $completed['showtime']->id, 'cinema' => $completed['cinema']->code]))
            ->assertDontSee(route('user.bookings.selectSeat', ['showtime' => $cancelled['showtime']->id, 'cinema' => $cancelled['cinema']->code]))
            ->assertSee('10:00 ~ 11:30')
            ->assertSee('2 suất chiếu đang khả dụng')
            ->assertSee('09:00 ~ 10:30')
            ->assertDontSee('08:59 ~ 10:29')
            ->assertDontSee('09:00 ~ 10:45')
            ->assertDontSee('07:00 ~ 08:30')
            ->assertDontSee('10:30 ~ 12:00')
            ->assertDontSee('Phòng LIFE-PLAYING');
    }

    public function test_discovery_follows_the_authoritative_start_plus_fifteen_boundary(): void
    {
        $scenario = $this->publicScenario('LIFE-DISCOVERY-GUARD', 'Discovery Guard Cinema', '2026-08-11', ['show_time' => '09:00:00']);
        $url = route('user.movies.show', ['slug' => $scenario['movie']->slug, 'date' => '2026-08-11']);
        $bookingUrl = route('user.bookings.selectSeat', [
            'showtime' => $scenario['showtime']->id,
            'cinema' => $scenario['cinema']->code,
        ]);

        foreach (['08:59:59', '09:00:00', '09:14:59'] as $time) {
            CarbonImmutable::setTestNow(CarbonImmutable::parse("2026-08-11 {$time}", 'Asia/Ho_Chi_Minh'));
            $this->get($url)->assertOk()->assertSee($bookingUrl);
        }

        foreach (['09:15:00', '09:15:01'] as $time) {
            CarbonImmutable::setTestNow(CarbonImmutable::parse("2026-08-11 {$time}", 'Asia/Ho_Chi_Minh'));
            $this->get($url)->assertOk()->assertDontSee($bookingUrl);
        }
    }

    public function test_direct_seat_selection_closes_at_exactly_start_plus_fifteen_minutes(): void
    {
        $scenario = $this->publicScenario('LIFE-GUARD', 'Guard Cinema', '2026-08-11', ['show_time' => '09:00:00']);

        foreach (['08:59:59', '09:00:00', '09:14:59'] as $time) {
            CarbonImmutable::setTestNow(CarbonImmutable::parse("2026-08-11 {$time}", 'Asia/Ho_Chi_Minh'));
            $this->get(route('user.bookings.selectSeat', $scenario['showtime']))->assertOk();
        }

        foreach (['09:15:00', '09:15:01'] as $time) {
            CarbonImmutable::setTestNow(CarbonImmutable::parse("2026-08-11 {$time}", 'Asia/Ho_Chi_Minh'));
            $this->get(route('user.bookings.selectSeat', $scenario['showtime']))
                ->assertRedirect(route('user.movies.show', $scenario['movie']->slug))
                ->assertSessionHas('error', 'Suất chiếu này đã đóng nhận đặt vé.');
        }
    }

    public function test_client_lifecycle_uses_server_clock_and_thirty_second_refresh(): void
    {
        $javascript = file_get_contents(resource_path('js/showtime-lifecycle.js'));
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($javascript);
        $this->assertStringContainsString('serverClockOffset', $javascript);
        $this->assertStringContainsString('30_000', $javascript);
        $this->assertStringContainsString("'[data-showtime-lifecycle]'", $javascript);
        $this->assertStringContainsString("'[data-customer-showtime]'", $javascript);
        $this->assertStringContainsString("import './showtime-lifecycle';", $app);
    }
}
