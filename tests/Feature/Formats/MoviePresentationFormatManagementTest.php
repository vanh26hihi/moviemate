<?php

namespace Tests\Feature\Formats;

use App\Models\Genre;
use App\Models\Movie;
use App\Models\PresentationFormat;
use App\Services\MovieLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Showtimes\ShowtimeTestCase;

final class MoviePresentationFormatManagementTest extends ShowtimeTestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_movie_create_form_and_transaction_save_multiple_normalized_formats(): void
    {
        $manager = $this->userWithRole('manager');
        $twoD = $this->format('2D');
        $threeD = $this->format('3D');

        $this->actingAs($manager)->get(route('admin.movies.create'))
            ->assertOk()->assertSee('Định dạng hỗ trợ')->assertSee('2D')->assertSee('3D');
        $this->post(route('admin.movies.store'), [
            'title' => 'Phim đa định dạng',
            'duration' => 100,
            'presentation_format_ids' => [$twoD->id, $threeD->id],
        ])->assertRedirect(route('admin.movies.index'));

        $movie = Movie::query()->where('title', 'Phim đa định dạng')->sole();
        $this->assertSame(Movie::STATUS_DRAFT, $movie->status);
        $this->assertSame([$twoD->id, $threeD->id], $movie->supportedPresentationFormats()->orderBy('presentation_formats.id')->pluck('presentation_formats.id')->all());
    }

    public function test_unknown_archived_and_duplicate_format_ids_fail_cleanly_without_creating_movie(): void
    {
        $manager = $this->userWithRole('manager');
        $active = $this->format('2D');
        $archived = $this->format('3D', false);
        $this->actingAs($manager);

        $this->post(route('admin.movies.store'), [
            'title' => 'Unknown format', 'presentation_format_ids' => [999999],
        ])->assertSessionHasErrors('presentation_format_ids.0');
        $this->post(route('admin.movies.store'), [
            'title' => 'Archived format', 'presentation_format_ids' => [$archived->id],
        ])->assertSessionHasErrors('presentation_format_ids');
        $this->post(route('admin.movies.store'), [
            'title' => 'Duplicate format', 'presentation_format_ids' => [$active->id, $active->id],
        ])->assertSessionHasErrors('presentation_format_ids.1');

        $movie = $this->movie();
        $movie->supportedPresentationFormats()->attach($active);
        $this->put(route('admin.movies.update', $movie), [
            'title' => $movie->title,
            'duration' => $movie->duration,
            'presentation_format_ids' => [$active->id, $archived->id],
        ])->assertSessionHasErrors('presentation_format_ids');

        $this->assertDatabaseMissing('movies', ['title' => 'Unknown format']);
        $this->assertDatabaseMissing('movies', ['title' => 'Archived format']);
        $this->assertDatabaseMissing('movies', ['title' => 'Duplicate format']);
        $this->assertFalse($movie->supportedPresentationFormats()->whereKey($archived->id)->exists());
    }

    public function test_future_active_showtime_blocks_detach_and_rolls_back_metadata_and_genres(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 12:00:00', 'Asia/Ho_Chi_Minh'));
        $manager = $this->userWithRole('manager');
        $twoD = $this->format('2D');
        $threeD = $this->format('3D');
        $movie = $this->movie(attributes: ['title' => 'Tên ban đầu']);
        $movie->supportedPresentationFormats()->attach([$twoD->id, $threeD->id]);
        $oldGenre = Genre::query()->create(['name' => 'Cũ', 'slug' => 'cu']);
        $newGenre = Genre::query()->create(['name' => 'Mới', 'slug' => 'moi']);
        $movie->genres()->attach($oldGenre);
        $showtime = $this->existing($movie, $this->rooms->get('P01'), ['presentation_format_id' => $threeD->id]);

        $this->actingAs($manager)->put(route('admin.movies.update', $movie), [
            'title' => 'Tên không được lưu',
            'duration' => $movie->duration,
            'genres' => [$newGenre->id],
            'presentation_format_ids' => [$twoD->id],
        ])->assertSessionHasErrors([
            'presentation_format_ids' => 'Không thể bỏ định dạng 3D vì phim còn suất chiếu tương lai đang sử dụng định dạng này.',
        ]);

        $this->assertSame('Tên ban đầu', $movie->fresh()->title);
        $this->assertSame([$oldGenre->id], $movie->genres()->pluck('genres.id')->all());
        $this->assertSame([$twoD->id, $threeD->id], $movie->supportedPresentationFormats()->orderBy('presentation_formats.id')->pluck('presentation_formats.id')->all());
        $this->assertSame($threeD->id, $showtime->fresh()->presentation_format_id);
    }

    public function test_completed_history_allows_detach_and_retains_showtime_format(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 12:00:00', 'Asia/Ho_Chi_Minh'));
        $manager = $this->userWithRole('manager');
        $twoD = $this->format('2D');
        $threeD = $this->format('3D');
        $movie = $this->movie();
        $movie->supportedPresentationFormats()->attach([$twoD->id, $threeD->id]);
        $showtime = $this->existing($movie, $this->rooms->get('P01'), [
            'show_date' => '2030-06-09',
            'presentation_format_id' => $threeD->id,
        ]);

        $this->actingAs($manager)->put(route('admin.movies.update', $movie), [
            'title' => $movie->title,
            'duration' => $movie->duration,
            'presentation_format_ids' => [$twoD->id],
        ])->assertRedirect(route('admin.movies.index'));

        $this->assertFalse($movie->supportedPresentationFormats()->whereKey($threeD->id)->exists());
        $this->assertSame($threeD->id, $showtime->fresh()->presentation_format_id);
    }

    public function test_cancelled_future_showtime_allows_detach_and_retains_showtime_format(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 12:00:00', 'Asia/Ho_Chi_Minh'));
        $manager = $this->userWithRole('manager');
        $twoD = $this->format('2D');
        $threeD = $this->format('3D');
        $movie = $this->movie();
        $movie->supportedPresentationFormats()->attach([$twoD->id, $threeD->id]);
        $showtime = $this->existing($movie, $this->rooms->get('P01'), [
            'status' => 'cancelled',
            'presentation_format_id' => $threeD->id,
        ]);

        $this->actingAs($manager)->put(route('admin.movies.update', $movie), [
            'title' => $movie->title,
            'duration' => $movie->duration,
            'presentation_format_ids' => [$twoD->id],
        ])->assertRedirect(route('admin.movies.index'));

        $this->assertFalse($movie->supportedPresentationFormats()->whereKey($threeD->id)->exists());
        $this->assertSame($threeD->id, $showtime->fresh()->presentation_format_id);
    }

    public function test_archived_current_attachment_is_visible_and_preserved_on_unrelated_edit(): void
    {
        $manager = $this->userWithRole('manager');
        $active = $this->format('2D');
        $archived = $this->format('3D', false);
        $movie = $this->movie();
        $movie->supportedPresentationFormats()->attach([$active->id, $archived->id]);

        $this->actingAs($manager)->get(route('admin.movies.edit', $movie))
            ->assertOk()->assertSee('Đã lưu trữ · bỏ chọn để gỡ liên kết')->assertSee('value="'.$archived->id.'"', false);
        $this->put(route('admin.movies.update', $movie), [
            'title' => 'Tên cập nhật',
            'duration' => $movie->duration,
            'presentation_format_ids' => [$active->id, $archived->id],
        ])->assertRedirect(route('admin.movies.index'));

        $this->assertSame([$active->id, $archived->id], $movie->supportedPresentationFormats()->orderBy('presentation_formats.id')->pluck('presentation_formats.id')->all());
    }

    public function test_draft_may_be_incomplete_but_schedulable_transition_requires_active_format(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->post(route('admin.movies.store'), ['title' => 'Bản nháp thiếu định dạng'])
            ->assertRedirect(route('admin.movies.index'));
        $movie = Movie::query()->where('title', 'Bản nháp thiếu định dạng')->sole();
        $this->assertSame(0, $movie->supportedPresentationFormats()->count());

        try {
            app(MovieLifecycleService::class)->transition($movie, Movie::STATUS_COMING_SOON, $admin);
            $this->fail('A schedulable movie must have an active supported format.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $this->assertSame(Movie::STATUS_DRAFT, $movie->fresh()->status);

        $movie->supportedPresentationFormats()->attach($this->format('2D'));
        app(MovieLifecycleService::class)->transition($movie->fresh(), Movie::STATUS_COMING_SOON, $admin);
        $this->assertSame(Movie::STATUS_COMING_SOON, $movie->fresh()->status);
    }

    private function format(string $code, bool $active = true): PresentationFormat
    {
        return PresentationFormat::query()->create([
            'code' => $code,
            'name' => $code,
            'is_active' => $active,
            'sort_order' => $code === '2D' ? 10 : 20,
        ]);
    }
}
