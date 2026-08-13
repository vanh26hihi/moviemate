<?php

namespace Tests\Feature\Showtimes;

use App\Models\Movie;
use App\Models\Room;
use App\Models\Showtime;
use App\Services\CinemaContext;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesPublicDiscoveryFixtures;
use Tests\TestCase;

class HomeShowtimeCalendarTest extends TestCase
{
    use CreatesPublicDiscoveryFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-05 09:00:00', 'Asia/Ho_Chi_Minh'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_homepage_renders_accessible_non_navigating_date_buttons(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('id="home-showtime-calendar"', false)
            ->assertSee('#home-showtime-calendar', false)
            ->assertSee('data-showtime-empty', false);

        $xpath = $this->xpathFor($response->getContent());
        $calendar = $xpath->query('//*[@id="home-showtime-calendar"]');
        $navigationLinks = $xpath->query('//a[contains(@href, "#home-showtime-calendar")]');
        $buttons = $xpath->query('//*[@id="home-showtime-calendar"]//button[@data-showtime-date]');

        $this->assertCount(1, $calendar);
        $this->assertGreaterThanOrEqual(1, $navigationLinks->length);
        $this->assertCount(7, $buttons);
        $this->assertCount(1, $xpath->query('//*[@data-showtime-empty]'));

        $selectedCount = 0;
        foreach ($buttons as $button) {
            $this->assertInstanceOf(DOMElement::class, $button);
            $this->assertSame('button', $button->tagName);
            $this->assertSame('button', $button->getAttribute('type'));
            $this->assertFalse($button->hasAttribute('href'));
            $this->assertContains($button->getAttribute('aria-pressed'), ['true', 'false']);
            $this->assertNotSame('', $button->getAttribute('aria-controls'));
            $this->assertSame(0, $xpath->query('ancestor::form', $button)->length);

            if ($button->getAttribute('aria-pressed') === 'true') {
                $selectedCount++;
            }
        }

        $this->assertSame(1, $selectedCount);
    }

    public function test_homepage_removes_redundant_nearest_showtimes_without_query_regression(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->get(route('home'));
        $queryCount = count(DB::getQueryLog());

        DB::disableQueryLog();

        $response->assertOk()
            ->assertDontSee('Lịch chiếu nhanh')
            ->assertDontSee('Suất chiếu gần nhất')
            ->assertDontSee('Chưa có suất chiếu gần nhất')
            ->assertSee('Lịch chiếu MovieMate')
            ->assertSee('Chọn chi nhánh, ngày và suất chiếu phù hợp')
            ->assertSee('Gần bạn');

        $this->assertLessThanOrEqual(42, $queryCount, "Homepage query count increased to {$queryCount}.");
    }

    public function test_selected_date_panel_can_render_showtimes_and_other_dates_remain_safe_empty_states(): void
    {
        $cinema = app(CinemaContext::class)->current();
        $room = Room::factory()->create(['cinema_id' => $cinema->id]);
        $layout = $this->publishRoomForDiscovery($room);
        $movie = Movie::query()->create([
            'title' => 'Calendar Regression Movie',
            'slug' => 'calendar-regression-movie',
            'status' => 'now_showing',
        ]);
        $format = $this->presentationFormatForDiscovery();
        $movie->supportedPresentationFormats()->attach($format);
        Showtime::query()->create([
            'movie_id' => $movie->id,
            'cinema_id' => $cinema->id,
            'room_id' => $room->id,
            'room_layout_id' => $layout->id,
            'presentation_format_id' => $format->id,
            'show_date' => '2026-08-07',
            'show_time' => '19:30:00',
            'price' => 90000,
            'pricing_version' => 'cinema-pricing-v1',
            'status' => 'active',
        ]);

        $response = $this->get(route('home', ['date' => '2026-08-07']));

        $response->assertOk()->assertSee('Calendar Regression Movie');
        $xpath = $this->xpathFor($response->getContent());
        $selectedButton = $xpath->query('//*[@data-showtime-date="2026-08-07" and @aria-pressed="true"]');
        $selectedPanel = $xpath->query('//*[@data-showtime-panel="2026-08-07" and not(@hidden)]');
        $emptyPanel = $xpath->query('//*[@data-showtime-empty-panel and @hidden]');

        $this->assertCount(1, $selectedButton);
        $this->assertCount(1, $selectedPanel);
        $this->assertCount(1, $emptyPanel);
    }

    public function test_out_of_range_date_falls_back_to_the_visible_calendar_window(): void
    {
        $response = $this->get(route('home', ['date' => '2030-01-01']));
        $xpath = $this->xpathFor($response->getContent());

        $this->assertCount(
            1,
            $xpath->query('//*[@data-showtime-date="2026-08-05" and @aria-pressed="true"]')
        );
    }

    public function test_calendar_javascript_has_no_navigation_or_vertical_scroll_side_effects(): void
    {
        $javascript = file_get_contents(resource_path('js/showtime-calendar.js'));
        $applicationEntry = file_get_contents(resource_path('js/app.js'));
        $partial = file_get_contents(resource_path('views/user/partials/showtime-section.blade.php'));
        $viteConfiguration = file_get_contents(base_path('vite.config.js'));

        $this->assertIsString($javascript);
        $this->assertIsString($applicationEntry);
        $this->assertIsString($partial);
        $this->assertIsString($viteConfiguration);
        $this->assertStringContainsString("button.setAttribute('aria-pressed'", $javascript);
        $this->assertStringContainsString("document.querySelector('#home-showtime-calendar[data-showtime-calendar]')", $javascript);
        $this->assertStringContainsString("document.addEventListener('DOMContentLoaded'", $javascript);
        $this->assertStringContainsString("window.addEventListener('pageshow'", $javascript);
        $this->assertStringContainsString('window.history.pushState', $javascript);
        $this->assertStringContainsString("window.addEventListener('popstate'", $javascript);
        $this->assertStringContainsString('strip.scrollLeft', $javascript);
        $this->assertStringContainsString('showtimeCalendarInitialized', $javascript);
        $this->assertStringNotContainsString('scrollIntoView', $javascript);
        $this->assertStringNotContainsString('window.scrollTo', $javascript);
        $this->assertStringNotContainsString('location.hash', $javascript);
        $this->assertStringNotContainsString('.submit(', $javascript);
        $this->assertStringNotContainsString('fetch(', $javascript);
        $this->assertSame(1, substr_count($applicationEntry, "import './showtime-calendar';"));
        $this->assertStringNotContainsString("@vite('resources/js/showtime-calendar.js')", $partial);
        $this->assertStringNotContainsString("'resources/js/showtime-calendar.js'", $viteConfiguration);
    }

    public function test_calendar_excludes_inactive_or_non_public_schedule_records(): void
    {
        $cinema = app(CinemaContext::class)->current();
        $activeRoom = Room::factory()->create(['cinema_id' => $cinema->id, 'status' => 'active']);
        $layout = $this->publishRoomForDiscovery($activeRoom);
        $inactiveRoom = Room::factory()->create(['cinema_id' => $cinema->id, 'status' => 'inactive']);
        $inactiveLayout = $this->publishedRoomLayoutFixture($inactiveRoom);
        $publicMovie = Movie::query()->create([
            'title' => 'Public calendar movie',
            'slug' => 'public-calendar-movie',
            'status' => 'now_showing',
        ]);
        $stoppedMovie = Movie::query()->create([
            'title' => 'Stopped calendar movie',
            'slug' => 'stopped-calendar-movie',
            'status' => 'stopped',
        ]);
        $format = $this->presentationFormatForDiscovery();
        $publicMovie->supportedPresentationFormats()->attach($format);
        $stoppedMovie->supportedPresentationFormats()->attach($format);
        $inactiveRoom->presentationCapabilities()->attach($format);

        foreach ([
            [$publicMovie, $activeRoom, 'active'],
            [$publicMovie, $inactiveRoom, 'active'],
            [$publicMovie, $activeRoom, 'cancelled'],
            [$stoppedMovie, $activeRoom, 'active'],
        ] as $index => [$movie, $room, $status]) {
            Showtime::query()->create([
                'movie_id' => $movie->id,
                'cinema_id' => $cinema->id,
                'room_id' => $room->id,
                'room_layout_id' => $room->is($activeRoom) ? $layout->id : $inactiveLayout->id,
                'presentation_format_id' => $format->id,
                'show_date' => '2026-08-07',
                'show_time' => sprintf('%02d:30:00', 16 + $index),
                'price' => 90000,
                'pricing_version' => 'cinema-pricing-v1',
                'status' => $status,
            ]);
        }

        $response = $this->get(route('home', ['date' => '2026-08-07']));

        $response->assertOk()
            ->assertSee('Public calendar movie')
            ->assertDontSee('Stopped calendar movie');

        $xpath = $this->xpathFor($response->getContent());
        $links = $xpath->query('//*[@data-showtime-panel="2026-08-07"]//a[contains(@href, "/booking/select-seat/")]');
        $this->assertCount(1, $links);
    }

    private function xpathFor(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }
}
