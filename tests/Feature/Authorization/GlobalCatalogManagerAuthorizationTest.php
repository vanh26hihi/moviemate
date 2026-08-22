<?php

namespace Tests\Feature\Authorization;

use App\Models\Cinema;
use App\Models\FoodItem;
use App\Models\Genre;
use App\Models\Movie;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UserCinemaAssignment;
use App\Services\CinemaAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class GlobalCatalogManagerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Cinema $cinema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
        Storage::fake('public');
        $this->cinema = Cinema::query()->active()->primary()->firstOrFail();
    }

    public function test_authoritative_role_seed_makes_global_catalog_read_only_for_manager(): void
    {
        $manager = Role::query()->where('slug', 'manager')->firstOrFail();
        $staff = Role::query()->where('slug', 'staff')->firstOrFail();
        $admin = Role::query()->where('slug', 'admin')->firstOrFail();
        $mutations = $this->mutationPermissions();

        $this->assertSame([], $manager->permissions()->whereIn('slug', $mutations)->pluck('slug')->all());
        $this->assertSame([], $staff->permissions()->whereIn('slug', $mutations)->pluck('slug')->all());
        $this->assertEqualsCanonicalizing(
            $mutations,
            $admin->permissions()->whereIn('slug', $mutations)->pluck('slug')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['movies.view', 'genres.view', 'foods.view'],
            $manager->permissions()->whereIn('slug', ['movies.view', 'genres.view', 'foods.view'])->pluck('slug')->all(),
        );
    }

    public function test_default_manager_direct_movie_genre_and_food_mutations_are_forbidden_and_unchanged(): void
    {
        $manager = $this->userWithRole('manager');
        $movie = $this->movie();
        $genre = Genre::query()->create(['name' => 'Action', 'slug' => 'action']);
        $food = FoodItem::query()->create(['name' => 'Bắp rang', 'price' => 45_000, 'active' => true]);

        $this->assertManagerMutationMatrixIsForbidden($manager, $movie, $genre, $food);
    }

    public function test_manually_granted_permissions_still_cannot_bypass_global_admin_authority(): void
    {
        $manager = $this->userWithRole('manager');
        $manager->role->permissions()->syncWithoutDetaching(
            Permission::query()->whereIn('slug', $this->mutationPermissions())->pluck('id'),
        );
        $manager->unsetRelation('role');

        $movie = $this->movie();
        $genre = Genre::query()->create(['name' => 'Kịch tính', 'slug' => 'kich-tinh']);
        $food = FoodItem::query()->create(['name' => 'Nước ngọt', 'price' => 30_000, 'active' => true]);

        $this->assertManagerMutationMatrixIsForbidden($manager, $movie, $genre, $food);

        $movieIndex = $this->actingAs($manager)->get(route('admin.movies.index'))->assertOk();
        $movieIndex->assertDontSee(route('admin.movies.create'), false)
            ->assertDontSee(route('admin.movies.edit', $movie), false);
        $this->get(route('admin.movies.show', $movie))->assertOk()
            ->assertDontSee(route('admin.movies.edit', $movie), false)
            ->assertDontSee(route('admin.movies.lifecycle', $movie), false);
        $this->get(route('admin.genres.index'))->assertOk()
            ->assertDontSee(route('admin.genres.create'), false)
            ->assertDontSee(route('admin.genres.edit', $genre), false)
            ->assertDontSee(route('admin.genres.destroy', $genre), false);
        $this->get(route('admin.foods.index'))->assertOk()
            ->assertDontSee(route('admin.foods.create'), false)
            ->assertDontSee(route('admin.foods.edit', $food), false)
            ->assertDontSee(route('admin.foods.destroy', $food), false);
    }

    public function test_multi_branch_manager_with_mutation_permissions_is_still_forbidden(): void
    {
        $manager = $this->userWithRole('manager');
        $secondCinema = Cinema::factory()->create([
            'status' => 'active',
            'archived_at' => null,
        ]);
        UserCinemaAssignment::query()->create([
            'user_id' => $manager->id,
            'cinema_id' => $secondCinema->id,
            'status' => UserCinemaAssignment::STATUS_ACTIVE,
            'assigned_at' => now(),
        ]);
        $manager->role->permissions()->syncWithoutDetaching(
            Permission::query()->whereIn('slug', $this->mutationPermissions())->pluck('id'),
        );
        $manager->unsetRelation('role');
        $movie = $this->movie();

        $this->actingAs($manager)
            ->withSession([CinemaAccessService::SESSION_KEY => $this->cinema->id])
            ->put(route('admin.movies.update', $movie), $this->moviePayload(['title' => 'Không được đổi']))
            ->assertForbidden();

        $this->assertSame('Phim gốc', $movie->fresh()->title);
    }

    public function test_global_admin_retains_movie_genre_and_food_mutation_in_active_cinema_context(): void
    {
        $admin = $this->userWithRole('admin');
        $session = [CinemaAccessService::SESSION_KEY => $this->cinema->id];

        $this->actingAs($admin)->withSession($session)
            ->post(route('admin.movies.store'), $this->moviePayload(['title' => 'Phim quản trị']))
            ->assertRedirect(route('admin.movies.index'));
        $movie = Movie::query()->where('title', 'Phim quản trị')->sole();
        $this->put(route('admin.movies.update', $movie), $this->moviePayload(['title' => 'Phim quản trị mới']))
            ->assertRedirect(route('admin.movies.index'));
        $this->post(route('admin.movies.lifecycle', $movie), ['status' => Movie::STATUS_INACTIVE])
            ->assertRedirect(route('admin.movies.show', $movie));
        $this->assertSame(Movie::STATUS_INACTIVE, $movie->fresh()->status);

        $this->post(route('admin.genres.store'), ['name' => 'Hành động'])->assertRedirect(route('admin.genres.index'));
        $genre = Genre::query()->where('name', 'Hành động')->sole();
        $this->put(route('admin.genres.update', $genre), ['name' => 'Phiêu lưu'])
            ->assertRedirect(route('admin.genres.index'));
        $this->delete(route('admin.genres.destroy', $genre))->assertRedirect(route('admin.genres.index'));
        $this->assertDatabaseMissing('genres', ['id' => $genre->id]);

        $this->post(route('admin.foods.store'), $this->foodPayload(['name' => 'Combo quản trị']))
            ->assertRedirect(route('admin.foods.index'));
        $food = FoodItem::query()->where('name', 'Combo quản trị')->sole();
        $this->put(route('admin.foods.update', $food), $this->foodPayload(['name' => 'Combo mới']))
            ->assertRedirect(route('admin.foods.index'));
        $this->delete(route('admin.foods.destroy', $food))->assertRedirect(route('admin.foods.index'));
        $this->assertDatabaseMissing('food_items', ['id' => $food->id]);
    }

    public function test_manager_keeps_read_access_and_global_admin_keeps_mutation_controls(): void
    {
        $movie = $this->movie();
        $genre = Genre::query()->create(['name' => 'Tâm lý', 'slug' => 'tam-ly']);
        $food = FoodItem::query()->create(['name' => 'Khoai tây', 'price' => 35_000, 'active' => true]);
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->get(route('admin.movies.index'))->assertOk()->assertSee($movie->title);
        $this->get(route('admin.movies.show', $movie))->assertOk();
        $this->get(route('admin.genres.index'))->assertOk()->assertSee($genre->name);
        $this->get(route('admin.foods.index'))->assertOk()->assertSee($food->name);

        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->get(route('admin.movies.index'))->assertOk()
            ->assertSee(route('admin.movies.create'), false)->assertSee(route('admin.movies.edit', $movie), false);
        $this->get(route('admin.genres.index'))->assertOk()
            ->assertSee(route('admin.genres.create'), false)->assertSee(route('admin.genres.edit', $genre), false);
        $this->get(route('admin.foods.index'))->assertOk()
            ->assertSee(route('admin.foods.create'), false)->assertSee(route('admin.foods.edit', $food), false);
    }

    public function test_catalog_indexes_remain_bounded_without_per_row_authorization_queries(): void
    {
        foreach (range(1, 25) as $number) {
            $movie = $this->movie(['title' => "Phim {$number}", 'slug' => "phim-{$number}"]);
            $genre = Genre::query()->create(['name' => "Thể loại {$number}", 'slug' => "the-loai-{$number}"]);
            $movie->genres()->attach($genre);
            FoodItem::query()->create(['name' => "Món {$number}", 'price' => 20_000, 'active' => true]);
        }
        $manager = $this->userWithRole('manager');

        $counts = [
            'movies' => $this->countQueries(fn () => $this->actingAs($manager)->get(route('admin.movies.index'))->assertOk()),
            'genres' => $this->countQueries(fn () => $this->actingAs($manager)->get(route('admin.genres.index'))->assertOk()),
            'foods' => $this->countQueries(fn () => $this->actingAs($manager)->get(route('admin.foods.index'))->assertOk()),
        ];

        foreach ($counts as $surface => $count) {
            $this->assertLessThanOrEqual(20, $count, "{$surface} catalog query budget exceeded: ".json_encode($counts));
        }
        if (getenv('REPORT_QUERY_COUNTS') === '1') {
            fwrite(STDOUT, 'GLOBAL_CATALOG_QUERY_COUNTS='.json_encode($counts, JSON_THROW_ON_ERROR).PHP_EOL);
        }
    }

    private function assertManagerMutationMatrixIsForbidden($manager, Movie $movie, Genre $genre, FoodItem $food): void
    {
        $movieBefore = $movie->fresh()->getRawOriginal();
        $genreBefore = $genre->fresh()->getRawOriginal();
        $foodBefore = $food->fresh()->getRawOriginal();
        $movieGenreBefore = $movie->genres()->pluck('genres.id')->all();
        $movieFormatBefore = $movie->supportedPresentationFormats()->pluck('presentation_formats.id')->all();

        $this->actingAs($manager)->get(route('admin.movies.create'))->assertForbidden();
        $this->post(route('admin.movies.store'), $this->moviePayload([
            'title' => 'Phim trái phép',
            'poster' => UploadedFile::fake()->image('unauthorized-movie.jpg'),
        ]))->assertForbidden();
        $this->get(route('admin.movies.edit', $movie))->assertForbidden();
        $this->put(route('admin.movies.update', $movie), $this->moviePayload([
            'title' => 'Phim bị đổi trái phép',
            'poster' => UploadedFile::fake()->image('unauthorized-update.jpg'),
        ]))->assertForbidden();
        $this->post(route('admin.movies.lifecycle', $movie), ['status' => Movie::STATUS_INACTIVE])->assertForbidden();

        $this->get(route('admin.genres.create'))->assertForbidden();
        $this->post(route('admin.genres.store'), ['name' => 'Thể loại trái phép'])->assertForbidden();
        $this->get(route('admin.genres.edit', $genre))->assertForbidden();
        $this->put(route('admin.genres.update', $genre), ['name' => 'Tên bị đổi'])->assertForbidden();
        $this->delete(route('admin.genres.destroy', $genre))->assertForbidden();

        $this->get(route('admin.foods.create'))->assertForbidden();
        $this->post(route('admin.foods.store'), $this->foodPayload([
            'name' => 'Món trái phép',
            'image' => UploadedFile::fake()->image('unauthorized-food.jpg'),
        ]))->assertForbidden();
        $this->get(route('admin.foods.edit', $food))->assertForbidden();
        $this->put(route('admin.foods.update', $food), $this->foodPayload([
            'name' => 'Món bị đổi trái phép',
            'image' => UploadedFile::fake()->image('unauthorized-food-update.jpg'),
        ]))->assertForbidden();
        $this->delete(route('admin.foods.destroy', $food))->assertForbidden();

        $this->assertDatabaseMissing('movies', ['title' => 'Phim trái phép']);
        $this->assertDatabaseMissing('genres', ['name' => 'Thể loại trái phép']);
        $this->assertDatabaseMissing('food_items', ['name' => 'Món trái phép']);
        $this->assertSame($movieBefore, $movie->fresh()->getRawOriginal());
        $this->assertSame($genreBefore, $genre->fresh()->getRawOriginal());
        $this->assertSame($foodBefore, $food->fresh()->getRawOriginal());
        $this->assertSame($movieGenreBefore, $movie->genres()->pluck('genres.id')->all());
        $this->assertSame($movieFormatBefore, $movie->supportedPresentationFormats()->pluck('presentation_formats.id')->all());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    /** @param array<string, mixed> $overrides */
    private function movie(array $overrides = []): Movie
    {
        return Movie::query()->create([
            'title' => 'Phim gốc',
            'slug' => 'phim-goc',
            'duration' => 100,
            'status' => Movie::STATUS_DRAFT,
            ...$overrides,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function moviePayload(array $overrides = []): array
    {
        return [
            'title' => 'Phim hợp lệ',
            'duration' => 100,
            'presentation_format_ids' => [],
            ...$overrides,
        ];
    }

    /** @param array<string, mixed> $overrides */
    private function foodPayload(array $overrides = []): array
    {
        return [
            'name' => 'Món hợp lệ',
            'description' => 'Mô tả',
            'price' => 50_000,
            'active' => true,
            ...$overrides,
        ];
    }

    /** @return list<string> */
    private function mutationPermissions(): array
    {
        return [
            'movies.create', 'movies.update', 'movies.lifecycle',
            'genres.create', 'genres.update', 'genres.delete',
            'foods.create', 'foods.update', 'foods.delete',
        ];
    }

    private function countQueries(callable $operation): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $operation();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }
}
