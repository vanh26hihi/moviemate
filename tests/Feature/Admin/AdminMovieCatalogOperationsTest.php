<?php

namespace Tests\Feature\Admin;

use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Room;
use App\Models\Showtime;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesPublicDiscoveryFixtures;
use Tests\TestCase;

final class AdminMovieCatalogOperationsTest extends TestCase
{
    use CreatesPublicDiscoveryFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
        CarbonImmutable::setTestNow('2026-08-17 10:00:00 Asia/Ho_Chi_Minh');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_stored_lifecycle_status_remains_authoritative_when_release_dates_differ(): void
    {
        $due = $this->movie('Due Release', 'due-release', Movie::STATUS_COMING_SOON, '2026-08-14');
        $future = $this->movie('Future Release', 'future-release', Movie::STATUS_NOW_SHOWING, '2026-08-20');
        $undated = $this->movie('Undated Release', 'undated-release', Movie::STATUS_COMING_SOON, null);

        $response = $this->actingAs($this->userWithRole('admin'))->get(route('admin.movies.index'));
        $response->assertOk()
            ->assertSee($due->title)->assertSee('Sắp khởi chiếu')
            ->assertSee($future->title)->assertSee('Đã khởi chiếu')
            ->assertSee($undated->title)->assertSee('Chưa nhập');

        $this->get(route('admin.movies.index', ['status' => Movie::STATUS_NOW_SHOWING]))
            ->assertOk()->assertSee($future->title)->assertDontSee($due->title)->assertDontSee($undated->title);
        $this->get(route('admin.movies.index', ['status' => Movie::STATUS_COMING_SOON]))
            ->assertOk()->assertSee($due->title)->assertSee($undated->title)->assertDontSee($future->title);
    }

    public function test_duplicate_titles_remain_distinct_catalog_rows_and_release_dates_stay_metadata(): void
    {
        $first = $this->movie('Dear You', 'dear-you-2024', Movie::STATUS_COMING_SOON, '2026-08-14');
        $second = $this->movie('Dear You', 'dear-you-2026', Movie::STATUS_NOW_SHOWING, '2026-08-20');

        $response = $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.movies.index', ['search' => 'Dear You', 'sort' => 'release_date', 'direction' => 'asc']))
            ->assertOk()
            ->assertSeeInOrder(['14/08/2026', '20/08/2026']);

        $this->assertSame([$first->id, $second->id], $response->viewData('movies')->pluck('id')->all());
        $this->assertDatabaseCount('movies', 2);
        $this->assertDatabaseHas('movies', ['id' => $first->id, 'title' => 'Dear You', 'status' => Movie::STATUS_COMING_SOON]);
        $this->assertDatabaseHas('movies', ['id' => $second->id, 'title' => 'Dear You', 'status' => Movie::STATUS_NOW_SHOWING]);
    }

    public function test_sorting_summaries_and_upcoming_counts_respect_the_selected_branch(): void
    {
        $primary = Cinema::query()->active()->primary()->firstOrFail();
        $primaryRoom = Room::factory()->create(['cinema_id' => $primary->id, 'code' => 'PRIMARY-CATALOG']);
        $primaryLayout = $this->publishRoomForDiscovery($primaryRoom);
        $foreign = Cinema::factory()->create([
            'code' => 'FOREIGN-CATALOG', 'status' => 'active', 'archived_at' => null,
        ]);
        $foreignRoom = Room::factory()->create(['cinema_id' => $foreign->id, 'code' => 'FOREIGN-CATALOG']);
        $foreignLayout = $this->publishRoomForDiscovery($foreignRoom);
        $format = $this->presentationFormatForDiscovery();
        $alpha = $this->movie('Alpha Catalog', 'alpha-catalog', Movie::STATUS_NOW_SHOWING, null);
        $zulu = $this->movie('Zulu Catalog', 'zulu-catalog', Movie::STATUS_DRAFT, '2026-08-20');
        $alpha->supportedPresentationFormats()->syncWithoutDetaching($format);

        foreach ([[$primary, $primaryRoom, $primaryLayout], [$foreign, $foreignRoom, $foreignLayout]] as [$cinema, $room, $layout]) {
            Showtime::query()->create([
                'movie_id' => $alpha->id,
                'cinema_id' => $cinema->id,
                'room_id' => $room->id,
                'room_layout_id' => $layout->id,
                'presentation_format_id' => $format->id,
                'show_date' => '2026-08-18',
                'show_time' => '19:00:00',
                'status' => 'active',
            ]);
        }

        $response = $this->actingAs($this->userWithRole('manager'))
            ->get(route('admin.movies.index', ['sort' => 'title', 'direction' => 'asc']))
            ->assertOk();

        $this->assertSame([$alpha->id, $zulu->id], $response->viewData('movies')->pluck('id')->all());
        $this->assertSame(2, $response->viewData('summary')['movies']);
        $this->assertSame(1, $response->viewData('summary')['drafts']);
        $this->assertSame(1, $response->viewData('summary')['missing_release_dates']);
        $this->assertSame(1, $response->viewData('summary')['upcoming_showtimes']);
        $this->assertSame(1, $response->viewData('movies')->firstWhere('id', $alpha->id)->upcoming_showtimes_count);
    }

    public function test_index_filters_are_validated_and_branch_context_does_not_hide_the_global_catalog(): void
    {
        $movie = $this->movie('Shared Chain Movie', 'shared-chain-movie', Movie::STATUS_NOW_SHOWING, '2026-08-10');
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->get(route('admin.movies.index'))
            ->assertOk()->assertSee($movie->title)
            ->assertSee('Danh mục không thay đổi khi chọn chi nhánh')
            ->assertDontSee(route('admin.movies.edit', $movie), false)
            ->assertDontSee('Thêm phim');

        $this->actingAs($this->userWithRole('admin'))
            ->get(route('admin.movies.index', ['status' => 'cancelled']))
            ->assertRedirect()->assertSessionHasErrors('status');
    }

    public function test_catalog_query_count_stays_bounded_as_movie_rows_grow(): void
    {
        $admin = $this->userWithRole('admin');
        $this->movie('Query Movie 1', 'query-movie-1', Movie::STATUS_NOW_SHOWING, '2026-08-10');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('admin.movies.index'))->assertOk();
        $one = count(DB::getQueryLog());

        foreach (range(2, 20) as $number) {
            $this->movie("Query Movie {$number}", "query-movie-{$number}", Movie::STATUS_NOW_SHOWING, '2026-08-10');
        }

        DB::flushQueryLog();
        $this->get(route('admin.movies.index'))->assertOk();
        $many = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual($one + 2, $many, "Movie catalog queries grew from {$one} to {$many}.");
    }

    private function movie(string $title, string $slug, string $status, ?string $releaseDate): Movie
    {
        return Movie::query()->create([
            'title' => $title,
            'slug' => $slug,
            'duration' => 90,
            'release_date' => $releaseDate,
            'status' => $status,
        ]);
    }
}
