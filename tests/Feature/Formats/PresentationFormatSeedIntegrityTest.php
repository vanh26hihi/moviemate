<?php

namespace Tests\Feature\Formats;

use App\Models\CinemaPricingRule;
use App\Models\Movie;
use App\Models\PresentationFormat;
use App\Models\Room;
use App\Models\RoomLayoutTemplate;
use App\Models\RoomType;
use App\Models\Showtime;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Support\DemoPresentationFormatConfiguration;
use Database\Seeders\Support\RealMovieCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PresentationFormatSeedIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_demo_seed_is_normalized_and_chain_consistent(): void
    {
        $this->seed(DatabaseSeeder::class);

        $formats = PresentationFormat::query()->orderBy('sort_order')->orderBy('id')->get();
        $this->assertSame(['2D', '3D'], $formats->pluck('code')->all());
        $this->assertSame([10, 20], $formats->pluck('sort_order')->all());
        $this->assertTrue($formats->every->is_active);
        $this->assertFalse($formats->contains('code', 'IMAX'));

        $this->assertSame(['STANDARD', 'IMAX'], RoomType::query()->orderBy('sort_order')->pluck('code')->all());
        $this->assertFalse(RoomType::query()->whereIn('code', ['2D', '3D'])->exists());

        $standard2d = Room::query()->where('room_type', 'STANDARD')
            ->whereHas('presentationCapabilities', fn ($query) => $query->where('code', '2D'))
            ->whereDoesntHave('presentationCapabilities', fn ($query) => $query->where('code', '3D'))
            ->firstOrFail();
        $former3d = Room::query()->where('code', 'P02')->with('presentationCapabilities')->firstOrFail();
        $imax = Room::query()->where('room_type', 'IMAX')->with('presentationCapabilities')->firstOrFail();
        $this->assertSame(['2D'], $standard2d->presentationCapabilities()->orderBy('sort_order')->pluck('code')->all());
        $this->assertSame(['2D', '3D'], $former3d->presentationCapabilities->sortBy('sort_order')->pluck('code')->all());
        $this->assertSame('STANDARD', $former3d->roomType?->code);
        $this->assertSame(['2D', '3D'], $imax->presentationCapabilities->sortBy('sort_order')->pluck('code')->all());
        $this->assertSame('IMAX', $imax->roomType?->code);

        Room::query()->with('roomType')->each(function (Room $room): void {
            $this->assertSame($room->room_type, $room->roomType?->code);
        });

        $twoDimensionalId = $formats->firstWhere('code', '2D')->id;
        $threeDimensionalId = $formats->firstWhere('code', '3D')->id;
        Movie::query()->each(function (Movie $movie) use ($twoDimensionalId): void {
            $this->assertTrue($movie->supportedPresentationFormats()->whereKey($twoDimensionalId)->exists(), "Movie {$movie->slug} lacks 2D support.");
        });
        $this->assertSame(
            DemoPresentationFormatConfiguration::THREE_D_MOVIE_SLUGS,
            Movie::query()->whereHas('supportedPresentationFormats', fn ($query) => $query->whereKey($threeDimensionalId))
                ->orderBy('id')->pluck('slug')->all(),
        );
        $this->assertTrue(Movie::query()->where('status', Movie::STATUS_NOW_SHOWING)
            ->whereDoesntHave('supportedPresentationFormats', fn ($query) => $query->whereKey($threeDimensionalId))->exists());

        $this->assertGreaterThan(0, Showtime::query()->where('presentation_format_id', $twoDimensionalId)->count());
        $this->assertGreaterThan(0, Showtime::query()->where('presentation_format_id', $threeDimensionalId)->count());
        $this->assertGreaterThan(0, Showtime::query()->where('room_id', $imax->id)
            ->where('presentation_format_id', $twoDimensionalId)->count());
        $this->assertGreaterThan(0, Showtime::query()->where('room_id', $imax->id)
            ->where('presentation_format_id', $threeDimensionalId)->count());

        $this->assertSame(0, Showtime::query()->whereNull('presentation_format_id')->count());
        $this->assertSame(0, $this->invalidMovieFormatShowtimes());
        $this->assertSame(0, $this->invalidRoomCapabilityShowtimes());
        $this->assertSame(0, CinemaPricingRule::query()->where('rule_type', 'room_type')->where('room_type', '3D')->count());
        $this->assertFalse(Schema::hasColumn('cinema_pricing_rules', 'presentation_format_id'));

        $template = RoomLayoutTemplate::query()->where('code', 'STANDARD_100')->firstOrFail();
        $this->assertSame('STANDARD', $template->room_type);
        $this->assertSame(10, $template->rows);
        $this->assertSame(12, $template->columns);
        $this->assertGreaterThan(0, $template->cells()->count());

        $this->assertSame(23, count(RealMovieCatalog::movies()));
        $this->assertArrayNotHasKey('presentation_format', RealMovieCatalog::movies()[0]);
        $this->assertArrayNotHasKey('language', RealMovieCatalog::movies()[0]);
    }

    private function invalidMovieFormatShowtimes(): int
    {
        return Showtime::query()->whereNotExists(fn ($query) => $query->selectRaw('1')->from('movie_presentation_formats')
            ->whereColumn('movie_presentation_formats.movie_id', 'showtimes.movie_id')
            ->whereColumn('movie_presentation_formats.presentation_format_id', 'showtimes.presentation_format_id'))->count();
    }

    private function invalidRoomCapabilityShowtimes(): int
    {
        return Showtime::query()->whereNotExists(fn ($query) => $query->selectRaw('1')->from('room_presentation_capabilities')
            ->whereColumn('room_presentation_capabilities.room_id', 'showtimes.room_id')
            ->whereColumn('room_presentation_capabilities.presentation_format_id', 'showtimes.presentation_format_id'))->count();
    }
}
