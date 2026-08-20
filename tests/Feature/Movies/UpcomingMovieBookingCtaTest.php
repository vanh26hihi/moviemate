<?php

namespace Tests\Feature\Movies;

use App\Models\Booking;
use App\Models\Movie;
use App\Services\ShowtimeLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesPublicDiscoveryFixtures;
use Tests\TestCase;

class UpcomingMovieBookingCtaTest extends TestCase
{
    use CreatesPublicDiscoveryFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-01 10:00:00', 'Asia/Ho_Chi_Minh'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_upcoming_movie_has_details_but_no_booking_action_on_customer_surfaces(): void
    {
        $movie = Movie::query()->create([
            'title' => 'Stored Upcoming Movie',
            'slug' => 'stored-upcoming-movie',
            'duration' => 100,
            'release_date' => '2029-01-01',
            'status' => Movie::STATUS_COMING_SOON,
        ]);
        $scenario = $this->publicScenario('UPCOMING-CTA', 'Upcoming CTA Cinema', '2030-06-02', ['movie' => $movie]);

        $homeCard = $this->cardFragment(
            $this->get(route('home'))->assertOk()->getContent(),
            'data-home-movie-card="'.$movie->id.'"',
        );
        $this->assertStringContainsString('Sắp chiếu', $homeCard);
        $this->assertStringContainsString('Chi tiết', $homeCard);
        $this->assertStringNotContainsString('data-home-movie-booking-action', $homeCard);

        $listingCard = $this->cardFragment(
            $this->get(route('user.movies.index', ['status' => Movie::STATUS_COMING_SOON]))->assertOk()->getContent(),
            'data-movie-card="'.$movie->id.'"',
        );
        $this->assertStringContainsString('Sắp chiếu', $listingCard);
        $this->assertStringContainsString('Chi tiết', $listingCard);
        $this->assertStringNotContainsString('data-movie-booking-action', $listingCard);

        $this->get(route('user.movies.show', $movie->slug))
            ->assertOk()
            ->assertSee('Sắp chiếu')
            ->assertDontSee('data-movie-detail-booking-action', false);
        $this->get(route('user.bookings.selectSeat', $scenario['showtime']))
            ->assertRedirect(route('user.movies.show', $movie->slug))
            ->assertSessionHas('error', 'Suất chiếu này đã đóng nhận đặt vé.');

        $movie->refresh();
        $this->assertSame(Movie::STATUS_COMING_SOON, $movie->status);
        $this->assertSame('2029-01-01', $movie->release_date->toDateString());
        $this->assertDatabaseCount((new Booking)->getTable(), 0);
    }

    public function test_now_showing_movie_with_bookable_showtime_exposes_booking_actions(): void
    {
        $movie = Movie::query()->create([
            'title' => 'Bookable Showing Movie',
            'slug' => 'bookable-showing-movie',
            'duration' => 100,
            'release_date' => '2031-01-01',
            'status' => Movie::STATUS_NOW_SHOWING,
        ]);
        $scenario = $this->publicScenario('SHOWING-CTA', 'Showing CTA Cinema', '2030-06-02', ['movie' => $movie]);

        $homeCard = $this->cardFragment(
            $this->get(route('home'))->assertOk()->getContent(),
            'data-home-movie-card="'.$movie->id.'"',
        );
        $this->assertStringContainsString('data-home-movie-booking-action', $homeCard);

        $listingCard = $this->cardFragment(
            $this->get(route('user.movies.index'))->assertOk()->getContent(),
            'data-movie-card="'.$movie->id.'"',
        );
        $this->assertStringContainsString('data-movie-booking-action', $listingCard);

        $this->get(route('user.movies.show', $movie->slug))
            ->assertOk()
            ->assertSee('data-movie-detail-booking-action', false);
        $this->get(route('user.bookings.selectSeat', $scenario['showtime']))->assertOk();
        $this->assertSame(Movie::STATUS_NOW_SHOWING, $movie->fresh()->status);
    }

    public function test_now_showing_movie_without_bookable_showtime_has_no_booking_action(): void
    {
        $movie = Movie::query()->create([
            'title' => 'Unavailable Showing Movie',
            'slug' => 'unavailable-showing-movie',
            'duration' => 100,
            'status' => Movie::STATUS_NOW_SHOWING,
        ]);

        $homeCard = $this->cardFragment(
            $this->get(route('home'))->assertOk()->getContent(),
            'data-home-movie-card="'.$movie->id.'"',
        );
        $listingCard = $this->cardFragment(
            $this->get(route('user.movies.index'))->assertOk()->getContent(),
            'data-movie-card="'.$movie->id.'"',
        );

        $this->assertStringNotContainsString('data-home-movie-booking-action', $homeCard);
        $this->assertStringNotContainsString('data-movie-booking-action', $listingCard);
        $this->get(route('user.movies.show', $movie->slug))
            ->assertOk()
            ->assertDontSee('data-movie-detail-booking-action', false);
    }

    public function test_now_showing_movie_with_invalid_pricing_has_no_booking_action(): void
    {
        $scenario = $this->publicScenario('INVALID-CTA', 'Invalid CTA Cinema', '2030-06-02', ['with_pricing' => false]);

        $listingCard = $this->cardFragment(
            $this->get(route('user.movies.index'))->assertOk()->getContent(),
            'data-movie-card="'.$scenario['movie']->id.'"',
        );

        $this->assertStringNotContainsString('data-movie-booking-action', $listingCard);
        $this->get(route('user.movies.show', $scenario['movie']->slug))
            ->assertOk()
            ->assertDontSee('data-movie-detail-booking-action', false);
    }

    public function test_customer_cutoff_remains_start_plus_fifteen_minutes(): void
    {
        $this->assertSame(15, ShowtimeLifecycleService::BOOKING_CUTOFF_MINUTES);
        $scenario = $this->publicScenario('CTA-CUTOFF', 'CTA Cutoff Cinema', '2030-06-01', ['show_time' => '10:00:00']);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-01 10:14:59', 'Asia/Ho_Chi_Minh'));
        $this->get(route('user.bookings.selectSeat', $scenario['showtime']))->assertOk();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-01 10:15:00', 'Asia/Ho_Chi_Minh'));
        $this->get(route('user.bookings.selectSeat', $scenario['showtime']))
            ->assertRedirect(route('user.movies.show', $scenario['movie']->slug));
    }

    private function cardFragment(string $html, string $marker): string
    {
        $start = strpos($html, $marker);
        $this->assertNotFalse($start, "Missing card marker: {$marker}");
        $end = strpos($html, '</article>', $start);
        $this->assertNotFalse($end, "Missing card closing tag: {$marker}");

        return substr($html, $start, $end - $start);
    }
}
