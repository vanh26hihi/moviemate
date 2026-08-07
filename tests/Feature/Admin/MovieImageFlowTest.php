<?php

namespace Tests\Feature\Admin;

use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class MovieImageFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        Storage::fake('public');
    }

    public function test_manager_can_upload_poster_and_banner_to_canonical_paths(): void
    {
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->post(route('admin.movies.store'), $this->validPayload([
            'poster' => UploadedFile::fake()->image('../../unsafe poster.jpg', 600, 900),
            'cover_image' => UploadedFile::fake()->image('banner.png', 1600, 900),
        ]))->assertRedirect(route('admin.movies.index'));

        $movie = Movie::query()->sole();
        $this->assertMatchesRegularExpression('#^movies/posters/[A-Za-z0-9._-]+\.jpg$#', $movie->poster);
        $this->assertMatchesRegularExpression('#^movies/banners/[A-Za-z0-9._-]+\.png$#', $movie->cover_image);
        $this->assertStringNotContainsString('unsafe', $movie->poster);
        Storage::disk('public')->assertExists([$movie->poster, $movie->cover_image]);
    }

    public function test_invalid_or_oversized_images_are_rejected_before_storage(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->from(route('admin.movies.create'))->post(route('admin.movies.store'), $this->validPayload([
            'poster' => UploadedFile::fake()->create('poster.svg', 20, 'image/svg+xml'),
            'cover_image' => UploadedFile::fake()->image('banner.jpg')->size(8193),
        ]))->assertRedirect(route('admin.movies.create'))->assertSessionHasErrors(['poster', 'cover_image']);

        $this->assertDatabaseCount('movies', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_customer_and_staff_cannot_upload_images(): void
    {
        foreach (['user', 'staff'] as $role) {
            $this->actingAs($this->userWithRole($role))->post(route('admin.movies.store'), $this->validPayload([
                'title' => 'Forbidden '.$role,
                'poster' => UploadedFile::fake()->image($role.'.jpg'),
            ]))->assertForbidden();
        }

        $this->assertDatabaseCount('movies', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_update_without_files_preserves_existing_images(): void
    {
        $movie = $this->movie([
            'poster' => 'movies/posters/original.jpg',
            'cover_image' => 'movies/covers/legacy.jpg',
        ]);
        Storage::disk('public')->put($movie->poster, 'poster');
        Storage::disk('public')->put($movie->cover_image, 'banner');

        $this->actingAs($this->userWithRole('manager'))->put(route('admin.movies.update', $movie), $this->validPayload([
            'title' => 'Updated title',
            'slug' => $movie->slug,
        ]))->assertRedirect(route('admin.movies.index'));

        $movie->refresh();
        $this->assertSame('movies/posters/original.jpg', $movie->poster);
        $this->assertSame('movies/covers/legacy.jpg', $movie->cover_image);
        Storage::disk('public')->assertExists([$movie->poster, $movie->cover_image]);
    }

    public function test_successful_replacement_preserves_shared_file_and_movie_delete_is_unavailable(): void
    {
        $first = $this->movie(['poster' => 'public/storage/movies/posters/shared.jpg']);
        $second = $this->movie(['title' => 'Second', 'slug' => 'second', 'poster' => 'movies/posters/shared.jpg']);
        Storage::disk('public')->put('movies/posters/shared.jpg', 'shared');

        $this->actingAs($this->userWithRole('admin'))->put(route('admin.movies.update', $first), $this->validPayload([
            'slug' => $first->slug,
            'poster' => UploadedFile::fake()->image('replacement.webp'),
        ]))->assertRedirect(route('admin.movies.index'));

        $first->refresh();
        $this->assertStringStartsWith('movies/posters/', $first->poster);
        Storage::disk('public')->assertExists([$first->poster, 'movies/posters/shared.jpg']);

        $this->actingAs($this->userWithRole('admin'))->delete('/admin/movies/'.$second->id)->assertMethodNotAllowed();
        $this->assertDatabaseHas('movies', ['id' => $second->id]);
        Storage::disk('public')->assertExists('movies/posters/shared.jpg');
        Storage::disk('public')->assertExists($first->poster);
    }

    public function test_database_failure_removes_new_file_and_retains_old_file(): void
    {
        $movie = $this->movie(['poster' => 'movies/posters/original.jpg']);
        Storage::disk('public')->put($movie->poster, 'original');
        Movie::updating(fn () => throw new RuntimeException('Forced update failure'));
        $thrown = null;

        try {
            $this->withoutExceptionHandling()->actingAs($this->userWithRole('admin'))->put(
                route('admin.movies.update', $movie),
                $this->validPayload([
                    'slug' => $movie->slug,
                    'poster' => UploadedFile::fake()->image('new.jpg'),
                ]),
            );
        } catch (RuntimeException $exception) {
            $thrown = $exception;
        } finally {
            Movie::flushEventListeners();
        }

        $this->assertSame('Forced update failure', $thrown?->getMessage());
        $this->assertSame('movies/posters/original.jpg', $movie->fresh()->poster);
        $this->assertSame(['movies/posters/original.jpg'], Storage::disk('public')->allFiles());
    }

    public function test_create_database_failure_does_not_report_success_or_orphan_files(): void
    {
        Movie::creating(fn () => throw new RuntimeException('Forced create failure'));
        $thrown = null;

        try {
            $this->withoutExceptionHandling()->actingAs($this->userWithRole('admin'))->post(
                route('admin.movies.store'),
                $this->validPayload([
                    'poster' => UploadedFile::fake()->image('poster.jpg'),
                    'cover_image' => UploadedFile::fake()->image('banner.jpg'),
                ]),
            );
        } catch (RuntimeException $exception) {
            $thrown = $exception;
        } finally {
            Movie::flushEventListeners();
        }

        $this->assertSame('Forced create failure', $thrown?->getMessage());
        $this->assertDatabaseCount('movies', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_accessors_normalize_legacy_paths_and_reject_unsafe_or_missing_paths(): void
    {
        Storage::disk('public')->put('movies/posters/existing.jpg', 'poster');

        foreach ([
            'movies/posters/existing.jpg',
            '/storage/movies/posters/existing.jpg',
            'public/storage/movies/posters/existing.jpg',
            'storage/storage/movies/posters/existing.jpg',
            'https://old.example/storage/movies/posters/existing.jpg',
        ] as $path) {
            $this->assertSame('movies/posters/existing.jpg', Movie::canonicalImagePath($path));
            $this->assertSame('/storage/movies/posters/existing.jpg', Movie::imageUrl($path));
        }

        foreach ([
            '../movies/posters/existing.jpg',
            'C:/movies/posters/existing.jpg',
            'https://example.com/uploads/existing.jpg',
            'movies/posters/../secret.jpg',
            'movies/posters/file%2Ejpg',
            'data:image/png;base64,abc',
        ] as $path) {
            $this->assertNull(Movie::canonicalImagePath($path));
            $this->assertNull(Movie::imageUrl($path));
        }

        $this->assertNull(Movie::imageUrl('movies/posters/missing.jpg'));
    }

    public function test_admin_and_public_lists_use_relative_urls_and_missing_file_fallbacks(): void
    {
        Storage::disk('public')->put('movies/posters/visible.jpg', 'poster');
        Storage::disk('public')->put('movies/banners/visible.jpg', 'banner');
        $visible = $this->movie([
            'poster' => 'storage/movies/posters/visible.jpg',
            'cover_image' => 'movies/banners/visible.jpg',
        ]);
        $missing = $this->movie(['title' => 'Missing image', 'slug' => 'missing-image', 'poster' => 'movies/posters/missing.jpg']);

        $this->actingAs($this->userWithRole('manager'))->get(route('admin.movies.index'))
            ->assertOk()
            ->assertSee('src="/storage/movies/posters/visible.jpg"', false)
            ->assertSee('Missing image')
            ->assertSee('admin-media-fallback', false);

        $this->get(route('user.movies.index'))
            ->assertOk()
            ->assertSee('src="/storage/movies/posters/visible.jpg"', false)
            ->assertDontSee('http://', false);

        $this->actingAs($this->userWithRole('manager'))->get(route('admin.movies.edit', $visible))
            ->assertOk()
            ->assertSee('src="/storage/movies/posters/visible.jpg"', false)
            ->assertSee('src="/storage/movies/banners/visible.jpg"', false);

        $detail = view('user.movies.show', ['movie' => $visible, 'showtimes' => collect()])->render();
        $this->assertStringContainsString('/storage/movies/banners/visible.jpg', $detail);
        $this->assertStringNotContainsString('http://', $detail);

        $this->assertSame('/storage/movies/posters/visible.jpg', $visible->poster_url);
        $this->assertNull($missing->poster_url);
    }

    public function test_diagnostic_reports_fake_disk_state_and_invalid_public_link_without_mutation(): void
    {
        $movie = $this->movie(['poster' => 'movies/posters/diagnostic.jpg']);
        Storage::disk('public')->put($movie->poster, 'poster');

        $exitCode = Artisan::call('movies:image-diagnostics', ['--movie' => $movie->id]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Public storage target is valid', $output);
        $this->assertStringContainsString('okay', $output);

        $this->assertSame('movies/posters/diagnostic.jpg', $movie->fresh()->poster);
        Storage::disk('public')->assertExists($movie->poster);
    }

    /** @param array<string, mixed> $overrides */
    private function validPayload(array $overrides = []): array
    {
        return [
            'title' => 'Test movie',
            'slug' => '',
            'description' => 'Description',
            'country' => 'Việt Nam',
            'duration' => 100,
            'age_rating' => 'P',
            'release_date' => '2026-08-05',
            'status' => 'now_showing',
            'genres' => [],
            ...$overrides,
        ];
    }

    /** @param array<string, mixed> $overrides */
    private function movie(array $overrides = []): Movie
    {
        return Movie::query()->create([
            'title' => 'Test movie',
            'slug' => 'test-movie',
            'duration' => 100,
            'age_rating' => 'P',
            'status' => 'now_showing',
            ...$overrides,
        ]);
    }
}
