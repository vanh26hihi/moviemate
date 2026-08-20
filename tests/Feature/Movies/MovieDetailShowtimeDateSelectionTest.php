<?php

namespace Tests\Feature\Movies;

use App\Models\Movie;
use App\Services\ShowtimeLifecycleService;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesPublicDiscoveryFixtures;
use Tests\TestCase;

class MovieDetailShowtimeDateSelectionTest extends TestCase
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

    public function test_no_date_uses_the_cinema_timezone_default_for_chip_and_showtimes(): void
    {
        $movie = $this->movie('date-default-movie');
        $today = $this->publicScenario('DATE-TODAY', 'Today Cinema', '2030-06-01', [
            'movie' => $movie,
            'show_time' => '19:00:00',
        ]);
        $tomorrow = $this->publicScenario('DATE-TOMORROW', 'Tomorrow Cinema', '2030-06-02', [
            'movie' => $movie,
            'show_time' => '20:00:00',
        ]);

        $response = $this->get(route('user.movies.show', $movie->slug))->assertOk();
        $xpath = $this->xpathFor($response->getContent());
        $results = $this->resultsHtml($response->getContent());

        $this->assertSame('Asia/Ho_Chi_Minh', config('cinema.timezone'));
        $this->assertSelectedDate($xpath, '2030-06-01');
        $this->assertStringContainsString('/booking/select-seat/'.$today['showtime']->id, $results);
        $this->assertStringNotContainsString('/booking/select-seat/'.$tomorrow['showtime']->id, $results);
        $this->assertCount(1, $xpath->query('//form[@data-showtime-filter-form and contains(@action, "/movies/date-default-movie#showtimes")]'));
        $this->assertCount(1, $xpath->query('//*[@id="showtimes" and contains(concat(" ", normalize-space(@class), " "), " scroll-mt-24 ")]'));
    }

    public function test_valid_date_is_the_single_source_for_active_chip_results_links_and_cinema_context(): void
    {
        $movie = $this->movie('date-source-movie');
        $first = $this->publicScenario('DATE-FIRST', 'First Date Cinema', '2030-06-02', [
            'movie' => $movie,
            'show_time' => '18:00:00',
        ]);
        $second = $this->publicScenario('DATE-SECOND', 'Second Date Cinema', '2030-06-03', [
            'movie' => $movie,
            'show_time' => '21:00:00',
        ]);

        $firstResponse = $this->get(route('user.movies.show', [
            'slug' => $movie->slug,
            'date' => '2030-06-02',
        ]))->assertOk();
        $this->assertSelectedDate($this->xpathFor($firstResponse->getContent()), '2030-06-02');
        $this->assertStringContainsString('/booking/select-seat/'.$first['showtime']->id, $this->resultsHtml($firstResponse->getContent()));

        $secondResponse = $this->get(route('user.movies.show', [
            'slug' => $movie->slug,
            'cinema' => $second['cinema']->code,
            'date' => '2030-06-03',
        ]))->assertOk();
        $xpath = $this->xpathFor($secondResponse->getContent());
        $results = $this->resultsHtml($secondResponse->getContent());

        $this->assertSelectedDate($xpath, '2030-06-03');
        $this->assertStringContainsString('/booking/select-seat/'.$second['showtime']->id, $results);
        $this->assertStringNotContainsString('/booking/select-seat/'.$first['showtime']->id, $results);
        $this->assertCount(1, $xpath->query('//select[@name="cinema"]/option[@value="'.$second['cinema']->code.'" and @selected]'));
        $this->assertCount(1, $xpath->query('//button[@name="date" and @value="2030-06-02"]'));
        $this->assertCount(1, $xpath->query('//button[@name="date" and @value="2030-06-03"]'));
        $this->assertCount(1, $xpath->query('//*[@data-showtime-result-meta and @data-showtime-result-date="2030-06-03" and @data-showtime-result-count="1"]'));
    }

    public function test_invalid_and_out_of_window_dates_fail_safely_without_rendering_an_impossible_selection(): void
    {
        $movie = $this->movie('invalid-date-movie');
        $returnUrl = route('user.movies.show', $movie->slug);

        foreach (['invalid', '2026-99-99', '2030-06-15'] as $invalidDate) {
            $this->from($returnUrl)
                ->get(route('user.movies.show', ['slug' => $movie->slug, 'date' => $invalidDate]))
                ->assertRedirect($returnUrl)
                ->assertSessionHasErrors('date');
        }
    }

    public function test_lifecycle_filtering_and_start_plus_fifteen_cutoff_remain_authoritative(): void
    {
        $movie = $this->movie('lifecycle-date-movie', 120);
        $withinCutoff = $this->publicScenario('CUTOFF-OPEN', 'Cutoff Open Cinema', '2030-06-01', [
            'movie' => $movie,
            'show_time' => '09:50:00',
        ]);
        $pastCutoff = $this->publicScenario('CUTOFF-CLOSED', 'Cutoff Closed Cinema', '2030-06-01', [
            'movie' => $movie,
            'show_time' => '09:40:00',
        ]);
        $cancelled = $this->publicScenario('CUTOFF-CANCEL', 'Cancelled Cinema', '2030-06-01', [
            'movie' => $movie,
            'show_time' => '19:00:00',
            'showtime_status' => 'cancelled',
        ]);

        $response = $this->get(route('user.movies.show', [
            'slug' => $movie->slug,
            'date' => '2030-06-01',
        ]))->assertOk();
        $results = $this->resultsHtml($response->getContent());

        $this->assertSame(15, ShowtimeLifecycleService::BOOKING_CUTOFF_MINUTES);
        $this->assertStringContainsString('/booking/select-seat/'.$withinCutoff['showtime']->id, $results);
        $this->assertStringNotContainsString('/booking/select-seat/'.$pastCutoff['showtime']->id, $results);
        $this->assertStringNotContainsString('/booking/select-seat/'.$cancelled['showtime']->id, $results);
    }

    public function test_ajax_contract_derives_visual_and_accessible_selection_from_the_effective_date(): void
    {
        $javascript = file_get_contents(resource_path('js/showtime.js'));
        $component = file_get_contents(resource_path('views/components/customer/showtimes/date-rail.blade.php'));
        $styles = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($javascript);
        $this->assertIsString($component);
        $this->assertIsString($styles);
        $this->assertStringContainsString("button.setAttribute('aria-current', 'date')", $javascript);
        $this->assertStringContainsString("button.removeAttribute('aria-current')", $javascript);
        $this->assertStringContainsString('metadata.dataset.showtimeResultDate', $javascript);
        $this->assertStringContainsString('form.dataset.defaultDate', $javascript);
        $this->assertStringContainsString('syncResultSummary(target)', $javascript);
        $this->assertStringContainsString('data-showtime-date-chip', $component);
        $this->assertStringContainsString('aria-current="date"', $component);
        $this->assertStringContainsString('showtime-date-chip__indicator', $component);
        $this->assertStringContainsString('.showtime-date-chip[aria-pressed="true"]', $styles);
        $this->assertStringContainsString('.showtime-date-chip:focus-visible', $styles);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $styles);
    }

    private function movie(string $slug, int $duration = 100): Movie
    {
        return Movie::query()->create([
            'title' => str($slug)->headline(),
            'slug' => $slug,
            'duration' => $duration,
            'status' => Movie::STATUS_NOW_SHOWING,
        ]);
    }

    private function assertSelectedDate(DOMXPath $xpath, string $date): void
    {
        $this->assertCount(1, $xpath->query('//button[@name="date" and @value="'.$date.'" and @aria-pressed="true" and @aria-current="date"]'));
        $this->assertCount(1, $xpath->query('//button[@name="date" and @aria-pressed="true"]'));
        $this->assertCount(1, $xpath->query('//button[@name="date" and @aria-current="date"]'));
    }

    private function resultsHtml(string $html): string
    {
        $xpath = $this->xpathFor($html);
        $result = $xpath->query('//*[@data-showtime-results]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $result);

        return $result->ownerDocument->saveHTML($result) ?: '';
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
