<?php

namespace Tests\Feature\Movies;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Movie;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesPublicDiscoveryFixtures;
use Tests\TestCase;

class CustomerMovieDiscoveryFlowTest extends TestCase
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

    public function test_movie_first_flow_filters_by_branch_and_date_and_preserves_other_branches_globally(): void
    {
        $movie = Movie::query()->create(['title' => 'Shared Discovery Movie', 'slug' => 'shared-discovery-movie', 'duration' => 100, 'status' => 'now_showing']);
        $first = $this->publicScenario('MOV-A', 'Cinema A', '2030-06-02', ['movie' => $movie]);
        $second = $this->publicScenario('MOV-B', 'Cinema B', '2030-06-02', ['movie' => $movie]);

        $this->get(route('user.movies.show', ['slug' => $movie->slug, 'date' => '2030-06-02']))
            ->assertOk()->assertSee($first['cinema']->name)->assertSee($second['cinema']->name)
            ->assertDontSee('cinema_id=', false);
        $this->get(route('user.movies.show', ['slug' => $movie->slug, 'cinema' => $first['cinema']->code, 'date' => '2030-06-02']))
            ->assertOk()->assertSee($first['cinema']->name)->assertDontSee($second['cinema']->address)
            ->assertSee('Từ 80.000 ₫');
        $this->get(route('user.movies.index', ['cinema' => $first['cinema']->code, 'date' => '2030-06-02']))
            ->assertOk()->assertSee($movie->title);
    }

    public function test_movie_card_uses_visual_only_poster_hover_and_real_zero_review_aggregate(): void
    {
        $movie = Movie::query()->create([
            'title' => 'Zero Review Movie',
            'slug' => 'zero-review-movie',
            'duration' => 120,
            'release_date' => '2030-06-01',
            'status' => Movie::STATUS_NOW_SHOWING,
        ]);

        $response = $this->get(route('user.movies.index'))->assertOk();
        $html = $response->getContent();
        $start = strpos($html, 'data-movie-card="'.$movie->id.'"');
        $this->assertNotFalse($start);
        $card = substr($html, $start, strpos($html, '</article>', $start) - $start);

        $this->assertStringContainsString('data-movie-poster', $card);
        $this->assertStringNotContainsString('Chưa có', $card);
        $this->assertStringContainsString('0.0', $card);
        $this->assertStringContainsString('0 đánh giá', $card);
        $this->assertSame(0, substr_count($card, 'data-movie-booking-action'));
        $this->assertSame(0, substr_count($card, 'Đặt vé'));
        $this->assertStringContainsString('Chi tiết', $card);
    }

    public function test_preferred_branch_is_prioritized_without_hiding_other_branches(): void
    {
        $movie = Movie::query()->create(['title' => 'Priority Movie', 'slug' => 'priority-movie', 'duration' => 100, 'status' => 'now_showing']);
        $first = $this->publicScenario('PRI-A', 'Alpha Cinema', '2030-06-02', ['movie' => $movie]);
        $preferred = $this->publicScenario('PRI-Z', 'Zulu Preferred Cinema', '2030-06-02', ['movie' => $movie]);
        $this->post(route('cinema-context.update'), ['cinema' => $preferred['cinema']->code]);

        $response = $this->get(route('user.movies.show', ['slug' => $movie->slug, 'date' => '2030-06-02']))->assertOk();
        $html = $response->getContent();
        $this->assertLessThan(strpos($html, $first['cinema']->name), strpos($html, $preferred['cinema']->name));
        $response->assertSee($first['cinema']->name);
    }

    public function test_cinema_first_links_preserve_branch_and_date_before_authoritative_showtime_selection(): void
    {
        $scenario = $this->publicScenario('FLOW-B', 'Cinema First Branch', '2030-06-02');
        $detail = $this->get(route('cinemas.show', ['cinema' => $scenario['cinema']->code, 'date' => '2030-06-02']))->assertOk();
        $detail->assertSee(route('user.movies.show', ['slug' => $scenario['movie']->slug, 'cinema' => $scenario['cinema']->code, 'date' => '2030-06-02']));
        $this->get(route('user.bookings.selectSeat', ['showtime' => $scenario['showtime'], 'cinema' => $scenario['cinema']->code]))
            ->assertOk()->assertSee($scenario['cinema']->name)->assertSee($scenario['cinema']->address)
            ->assertSee('80.000 VNĐ');
    }

    public function test_preference_change_does_not_override_showtime_or_existing_booking_branch(): void
    {
        $authoritative = $this->publicScenario('AUTH-A', 'Authoritative Cinema', '2030-06-02');
        $preference = $this->publicScenario('AUTH-B', 'Preference Cinema', '2030-06-02');
        $booking = Booking::query()->create([
            'showtime_id' => $authoritative['showtime']->id, 'cinema_id' => $authoritative['cinema']->id,
            'booking_code' => 'R5-BRANCH-SNAPSHOT', 'customer_email' => 'r5@example.test',
            'total_amount' => 80_000, 'payment_status' => 'unpaid', 'booking_status' => 'pending_payment',
        ]);
        $this->post(route('cinema-context.update'), ['cinema' => $preference['cinema']->code]);

        $this->get(route('user.bookings.selectSeat', $authoritative['showtime']))
            ->assertOk()->assertSee($authoritative['cinema']->name)->assertDontSee($preference['cinema']->address);
        $this->assertSame($authoritative['cinema']->id, $booking->fresh()->cinema_id);
    }

    public function test_ajax_endpoint_returns_read_only_partial_and_validates_all_filters(): void
    {
        $scenario = $this->publicScenario('AJAX-A', 'Ajax Cinema', '2030-06-02');
        $counts = [Booking::query()->count(), ActivityLog::query()->count()];
        $this->get(route('showtimes.filter', [
            'context' => 'cinema', 'cinema' => $scenario['cinema']->code, 'date' => '2030-06-02',
        ]), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertSee($scenario['movie']->title)->assertDontSee('<html', false);
        $this->getJson(route('showtimes.filter', ['context' => 'cinema', 'cinema' => $scenario['cinema']->code, 'date' => '2020-01-01']))
            ->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->getJson(route('showtimes.filter', ['context' => 'forged']))->assertUnprocessable();
        $this->assertSame($counts, [Booking::query()->count(), ActivityLog::query()->count()]);
    }

    public function test_javascript_contract_has_action_only_geolocation_ajax_history_abort_and_fallback(): void
    {
        $javascript = file_get_contents(resource_path('js/showtime.js'));
        $this->assertIsString($javascript);
        $this->assertStringContainsString("closest('#nearbyCinemaBtn')", $javascript);
        $this->assertStringContainsString('navigator.geolocation.getCurrentPosition', $javascript);
        $this->assertStringContainsString('fetch(', $javascript);
        $this->assertStringContainsString('AbortController', $javascript);
        $this->assertStringContainsString('requestSequence', $javascript);
        $this->assertStringContainsString("button.setAttribute('aria-pressed'", $javascript);
        $this->assertStringContainsString('window.history.pushState', $javascript);
        $this->assertStringContainsString("window.addEventListener('popstate'", $javascript);
        $this->assertStringContainsString('window.location.assign(pageUrl.toString())', $javascript);
        $this->assertSame(1, substr_count($javascript, "document.addEventListener('submit'"));
        $this->assertStringNotContainsString('{ ... }', $javascript);
    }

    public function test_missing_pricing_unpublished_layout_and_inactive_records_are_not_sellable(): void
    {
        $missingPrice = $this->publicScenario('NO-PRICE', 'No Price', '2030-06-02', ['with_pricing' => false]);
        $draftLayout = $this->publicScenario('NO-LAYOUT', 'No Layout', '2030-06-02', ['layout_status' => 'draft']);
        $inactiveRoom = $this->publicScenario('NO-ROOM', 'No Room', '2030-06-02', ['room_status' => 'inactive']);
        $inactiveShow = $this->publicScenario('NO-SHOW', 'No Showtime', '2030-06-02', ['showtime_status' => 'cancelled']);

        foreach ([$missingPrice, $draftLayout, $inactiveRoom, $inactiveShow] as $scenario) {
            $this->get(route('user.bookings.selectSeat', $scenario['showtime']))
                ->assertRedirect(route('user.movies.show', $scenario['movie']->slug));
        }
    }

    public function test_inactive_branch_showtime_and_cross_branch_cinema_hint_are_rejected(): void
    {
        $scenario = $this->publicScenario('VALID-A', 'Valid A', '2030-06-02');
        $other = $this->publicScenario('VALID-B', 'Valid B', '2030-06-02');
        $this->get(route('user.bookings.selectSeat', ['showtime' => $scenario['showtime'], 'cinema' => $other['cinema']->code]))->assertNotFound();
        $scenario['cinema']->update(['status' => 'inactive', 'archived_at' => now()]);
        $this->get(route('user.bookings.selectSeat', $scenario['showtime']))
            ->assertRedirect(route('user.movies.show', $scenario['movie']->slug));
    }
}
