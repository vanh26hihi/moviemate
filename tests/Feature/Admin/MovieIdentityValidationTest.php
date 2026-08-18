<?php

namespace Tests\Feature\Admin;

use App\Models\Movie;
use App\Support\AdminUniqueRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MovieIdentityValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedRbac();
    }

    public function test_duplicate_titles_are_allowed_and_normalized_while_slugs_remain_unique(): void
    {
        Movie::query()->create([
            'title' => 'Dear You',
            'slug' => 'dear-you-original',
            'duration' => 90,
            'status' => Movie::STATUS_DRAFT,
        ]);

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.movies.store'), [
                'title' => '  Dear   You  ',
                'slug' => 'dear-you-remake',
                'duration' => 105,
            ])->assertRedirect(route('admin.movies.index'));

        $this->assertSame(2, Movie::query()->where('title', 'Dear You')->count());
        $this->assertDatabaseHas('movies', [
            'title' => 'Dear You',
            'slug' => 'dear-you-remake',
            'status' => Movie::STATUS_DRAFT,
        ]);

        $this->from(route('admin.movies.create'))->post(route('admin.movies.store'), [
            'title' => 'Another Movie',
            'slug' => 'dear-you-remake',
            'duration' => 95,
        ])->assertRedirect(route('admin.movies.create'))
            ->assertSessionHasErrors(['slug' => 'Đường dẫn phim này đã tồn tại.']);
    }

    public function test_edit_slug_uniqueness_ignores_only_the_current_movie(): void
    {
        $first = Movie::query()->create([
            'title' => 'First Movie', 'slug' => 'first-movie', 'duration' => 90, 'status' => Movie::STATUS_DRAFT,
        ]);
        $second = Movie::query()->create([
            'title' => 'Second Movie', 'slug' => 'second-movie', 'duration' => 90, 'status' => Movie::STATUS_DRAFT,
        ]);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->put(route('admin.movies.update', $first), [
            'title' => 'First Movie Updated',
            'slug' => 'first-movie',
            'duration' => 90,
        ])->assertRedirect(route('admin.movies.index'));

        $this->from(route('admin.movies.edit', $second))->put(route('admin.movies.update', $second), [
            'title' => 'First Movie Updated',
            'slug' => 'first-movie',
            'duration' => 90,
        ])->assertRedirect(route('admin.movies.edit', $second))
            ->assertSessionHasErrors(['slug' => 'Đường dẫn phim này đã tồn tại.']);

        $this->assertDatabaseHas('movies', ['id' => $second->id, 'title' => 'Second Movie', 'slug' => 'second-movie']);
    }

    public function test_realtime_validation_matches_slug_rule_and_has_no_title_uniqueness_rule(): void
    {
        $movie = Movie::query()->create([
            'title' => 'Shared Title', 'slug' => 'shared-title', 'duration' => 90, 'status' => Movie::STATUS_DRAFT,
        ]);
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->postJson(route('admin.validation.field'), [
            'rule' => AdminUniqueRules::MOVIE_SLUG,
            'value' => 'shared-title',
        ])->assertUnprocessable()
            ->assertExactJson(['valid' => false, 'message' => 'Đường dẫn phim này đã tồn tại.']);

        $this->postJson(route('admin.validation.field'), [
            'rule' => AdminUniqueRules::MOVIE_SLUG,
            'value' => 'shared-title',
            'record_id' => $movie->id,
        ])->assertOk()->assertExactJson(['valid' => true]);

        $this->postJson(route('admin.validation.field'), [
            'rule' => 'movie.title',
            'value' => 'Shared Title',
        ])->assertUnprocessable()->assertJsonValidationErrors('rule');
    }

    public function test_movie_forms_expose_slug_realtime_validation_without_title_uniqueness_claims(): void
    {
        $movie = Movie::query()->create([
            'title' => 'Form Movie', 'slug' => 'form-movie', 'duration' => 90, 'status' => Movie::STATUS_DRAFT,
        ]);
        $admin = $this->userWithRole('admin');

        foreach ([route('admin.movies.create'), route('admin.movies.edit', $movie)] as $url) {
            $this->actingAs($admin)->get($url)
                ->assertOk()
                ->assertSee('Tên phim có thể trùng')
                ->assertSee('data-validation-rule="movie.slug"', false)
                ->assertDontSee('data-validation-rule="movie.title"', false)
                ->assertDontSee('Tên phim là duy nhất');
        }
    }
}
