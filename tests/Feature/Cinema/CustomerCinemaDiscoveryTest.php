<?php

namespace Tests\Feature\Cinema;

use App\Models\ActivityLog;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Room;
use App\Models\Showtime;
use App\Services\CinemaAccessService;
use App\Services\CinemaContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesPublicDiscoveryFixtures;
use Tests\TestCase;

class CustomerCinemaDiscoveryTest extends TestCase
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

    public function test_directory_only_shows_active_branches_with_counts_and_discovery_coordinates(): void
    {
        $active = $this->publicScenario('PUB-CG', 'MovieMate Public Cầu Giấy', '2030-06-02');
        $inactive = $this->publicScenario('PUB-OFF', 'MovieMate Hidden', '2030-06-02', ['cinema_status' => 'inactive']);

        $this->get(route('cinemas.index'))->assertOk()
            ->assertSee($active['cinema']->name)->assertDontSee($inactive['cinema']->name)
            ->assertSee('Phim khả dụng')->assertSee('Suất sắp tới')
            ->assertSee('data-latitude="'.$active['cinema']->latitude.'"', false)
            ->assertDontSee('lat=', false)->assertDontSee('lng=', false)->assertDontSee('Manager');
    }

    public function test_directory_search_city_district_and_open_filters_are_server_backed(): void
    {
        $wanted = $this->publicScenario('PUB-HD', 'MovieMate Hà Đông Search', '2030-06-02', ['district' => 'Hà Đông']);
        $other = $this->publicScenario('PUB-NTL', 'MovieMate Nam Từ Liêm Other', '2030-06-02', ['district' => 'Nam Từ Liêm']);

        $this->get(route('cinemas.index', ['search' => 'Hà Đông', 'city' => 'Hà Nội', 'district' => 'Hà Đông', 'open' => 1]))
            ->assertOk()->assertSee($wanted['cinema']->name)->assertDontSee($other['cinema']->address);
    }

    public function test_nearby_sort_is_client_side_and_writes_no_activity_log(): void
    {
        $near = $this->publicScenario('PUB-NEAR', 'Near Cinema', '2030-06-02', ['latitude' => '21.0301', 'longitude' => '105.7801']);
        $far = $this->publicScenario('PUB-FAR', 'Far Cinema', '2030-06-02', ['latitude' => '20.0000', 'longitude' => '104.0000']);
        Cinema::query()->where('is_primary', true)->update(['latitude' => null, 'longitude' => null]);

        $response = $this->get(route('cinemas.index'))->assertOk();
        $response->assertSee('data-latitude="'.$near['cinema']->latitude.'"', false)
            ->assertSee('data-latitude="'.$far['cinema']->latitude.'"', false)
            ->assertDontSee('lat=', false)->assertDontSee('lng=', false);
        $source = file_get_contents(resource_path('js/showtime.js'));
        $this->assertStringContainsString('navigator.geolocation.getCurrentPosition', $source);
        $this->assertStringContainsString('haversineDistance', $source);
        $this->assertSame(0, ActivityLog::query()->count());
    }

    public function test_cinema_detail_is_branch_specific_date_scoped_and_priced_by_r4_service(): void
    {
        $branch = $this->publicScenario('PUB-DETAIL', 'Detail Cinema', '2030-06-02', ['room_type' => 'IMAX']);
        $other = $this->publicScenario('PUB-OTHER', 'Other Branch Movie', '2030-06-02');

        $this->get(route('cinemas.show', ['cinema' => $branch['cinema']->code, 'date' => '2030-06-02']))
            ->assertOk()->assertSee('Lịch chiếu tại '.$branch['cinema']->name)
            ->assertSee($branch['movie']->title)->assertSee('80.000 ₫')
            ->assertSee('Định dạng: Test 2D')->assertSee('Loại phòng: IMAX')
            ->assertDontSee($other['movie']->title);
        $this->get(route('cinemas.show', ['cinema' => $branch['cinema']->code, 'date' => '2030-06-03']))
            ->assertOk()->assertSee('Chưa có suất chiếu');
    }

    public function test_inactive_branch_detail_is_not_public(): void
    {
        $branch = $this->publicScenario('PUB-INACTIVE', 'Inactive Detail', '2030-06-02', ['cinema_status' => 'inactive']);
        $this->get(route('cinemas.show', $branch['cinema']->code))->assertNotFound();
    }

    public function test_guest_preference_accepts_code_supports_all_and_never_collides_with_admin_context(): void
    {
        $branch = $this->publicScenario('PUB-PREF', 'Preferred Branch', '2030-06-02');
        $this->withSession([CinemaAccessService::SESSION_KEY => 'all'])
            ->post(route('cinema-context.update'), ['cinema' => $branch['cinema']->code])
            ->assertRedirect()->assertSessionHas(CinemaContext::SESSION_KEY, $branch['cinema']->id)
            ->assertSessionHas(CinemaAccessService::SESSION_KEY, 'all');
        $this->get(route('user.movies.index'))->assertSee($branch['cinema']->name);
        $this->post(route('cinema-context.update'), ['cinema' => 'all'])->assertSessionMissing(CinemaContext::SESSION_KEY);
    }

    public function test_inactive_forged_and_invalid_stored_preferences_fail_safely(): void
    {
        $inactive = $this->publicScenario('PUB-DEAD', 'Dead Preference', '2030-06-02', ['cinema_status' => 'inactive']);
        $this->post(route('cinema-context.update'), ['cinema' => $inactive['cinema']->code])->assertNotFound();
        $this->post(route('cinema-context.update'), ['cinema' => '../../forged'])->assertSessionHasErrors('cinema');
        $this->withSession([CinemaContext::SESSION_KEY => 'forged'])->get(route('cinemas.index'))->assertOk()
            ->assertSessionMissing(CinemaContext::SESSION_KEY);
    }

    public function test_customer_navigation_has_cinema_link_one_shared_branch_selector_and_accessible_filters(): void
    {
        $this->publicScenario('PUB-NAV', 'Navigation Branch', '2030-06-02');
        $response = $this->get(route('cinemas.index'))->assertOk()->assertSee('Rạp')->assertSee('Tất cả rạp');
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'id="customer-cinema-selector"'));
        $this->assertSame(1, substr_count($html, 'id="nearbyCinemaBtn"'));
        $this->assertStringContainsString('aria-label="Lọc danh sách rạp"', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->get(route('home'))->assertDontSee('cinema_id=', false);
    }

    public function test_customer_discovery_pages_keep_queries_bounded_across_multiple_branches_rooms_dates_and_movies(): void
    {
        $scenarios = collect([
            $this->publicScenario('PERF-A', 'Performance A', '2030-06-02'),
            $this->publicScenario('PERF-B', 'Performance B', '2030-06-02'),
            $this->publicScenario('PERF-C', 'Performance C', '2030-06-02'),
        ]);
        foreach ($scenarios as $index => $scenario) {
            $room = Room::query()->create([
                'cinema_id' => $scenario['cinema']->id, 'code' => 'PERF-'.$index.'-02',
                'name' => 'PhÃ²ng phá»¥ '.$index, 'room_type' => '3D', 'width_mm' => 8_000, 'length_mm' => 10_000, 'status' => 'active',
            ]);
            $layout = $this->publishRoomForDiscovery($room);
            $movie = Movie::query()->create([
                'title' => 'Performance extra '.$index, 'slug' => 'performance-extra-'.$index,
                'duration' => 95, 'status' => 'now_showing',
            ]);
            Showtime::query()->create([
                'movie_id' => $movie->id, 'cinema_id' => $scenario['cinema']->id,
                'room_id' => $room->id, 'room_layout_id' => $layout->id,
                'presentation_format_id' => $this->presentationFormatFixture($movie, $room)->id,
                'show_date' => '2030-06-03', 'show_time' => '20:00:00',
                'status' => 'active',
            ]);
        }

        $first = $scenarios->first();
        $counts = [
            'home' => $this->countQueries(fn () => $this->get(route('home'))),
            'directory' => $this->countQueries(fn () => $this->get(route('cinemas.index'))),
            'cinema_detail' => $this->countQueries(fn () => $this->get(route('cinemas.show', ['cinema' => $first['cinema']->code, 'date' => '2030-06-02']))),
            'movie_index' => $this->countQueries(fn () => $this->get(route('user.movies.index', ['cinema' => $first['cinema']->code, 'date' => '2030-06-02']))),
            'movie_detail' => $this->countQueries(fn () => $this->get(route('user.movies.show', ['slug' => $first['movie']->slug, 'date' => '2030-06-02']))),
            'partial' => $this->countQueries(fn () => $this->get(route('showtimes.filter', ['context' => 'cinema', 'cinema' => $first['cinema']->code, 'date' => '2030-06-02']))),
            'nearby' => $this->countQueries(fn () => $this->get(route('cinemas.index'))),
        ];

        foreach ($counts as $page => $count) {
            $budget = match ($page) {
                'home' => 27,
                'movie_index' => 26,
                default => 25,
            };
            $this->assertLessThanOrEqual($budget, $count, "{$page} query count exceeded the bounded discovery budget: ".json_encode($counts));
        }
        $this->assertLessThanOrEqual($counts['cinema_detail'], $counts['partial']);
        if (getenv('REPORT_QUERY_COUNTS') === '1') {
            fwrite(STDOUT, 'DISCOVERY_QUERY_COUNTS='.json_encode($counts, JSON_THROW_ON_ERROR).PHP_EOL);
        }
    }

    private function countQueries(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request()->assertSuccessful();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
