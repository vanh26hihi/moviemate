<?php

namespace Tests\Feature\Formats;

use App\Models\PresentationFormat;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Showtimes\ShowtimeTestCase;

final class PresentationFormatFoundationTest extends ShowtimeTestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_normalized_format_schema_and_model_relationships_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('presentation_formats', [
            'id', 'code', 'name', 'description', 'is_active', 'sort_order',
            'created_by_user_id', 'updated_by_user_id', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('movie_presentation_formats', [
            'movie_id', 'presentation_format_id', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('room_presentation_capabilities', [
            'room_id', 'presentation_format_id', 'created_at', 'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumn('showtimes', 'presentation_format_id'));
        $formatIndexes = collect(Schema::getIndexes('presentation_formats'));
        $this->assertTrue($formatIndexes->contains(fn (array $index): bool => $index['unique'] && $index['columns'] === ['code']));
        $this->assertTrue($formatIndexes->contains(fn (array $index): bool => $index['unique'] && $index['columns'] === ['name']));

        $format = $this->format('2D');
        $movie = $this->movie();
        $room = $this->rooms->get('P01');
        $movie->supportedPresentationFormats()->attach($format);
        $room->presentationCapabilities()->attach($format);
        $showtime = $this->existing($movie, $room, ['presentation_format_id' => $format->id]);

        $this->assertTrue($movie->fresh()->supportedPresentationFormats->contains($format));
        $this->assertTrue($room->fresh()->presentationCapabilities->contains($format));
        $this->assertTrue($format->fresh()->movies->contains($movie));
        $this->assertTrue($format->fresh()->rooms->contains($room));
        $this->assertTrue($format->fresh()->showtimes->contains($showtime));
        $this->assertTrue($showtime->fresh()->presentationFormat->is($format));
    }

    public function test_format_code_is_database_unique(): void
    {
        $this->format('2D');

        $this->expectException(QueryException::class);
        PresentationFormat::query()->create([
            'code' => '2D',
            'name' => 'Duplicate format',
            'is_active' => true,
        ]);
    }

    public function test_movie_format_pair_is_database_unique(): void
    {
        $format = $this->format('2D');
        $movie = $this->movie();
        $movie->supportedPresentationFormats()->attach($format);

        $this->expectException(QueryException::class);
        $movie->supportedPresentationFormats()->attach($format);
    }

    public function test_room_capability_pair_is_database_unique(): void
    {
        $format = $this->format('2D');
        $room = $this->rooms->get('P01');
        $room->presentationCapabilities()->attach($format);

        $this->expectException(QueryException::class);
        $room->presentationCapabilities()->attach($format);
    }

    public function test_showtime_format_is_not_nullable(): void
    {
        $movie = $this->movie();

        $column = collect(Schema::getColumns('showtimes'))->firstWhere('name', 'presentation_format_id');
        $this->assertIsArray($column);
        $this->assertFalse($column['nullable']);

        $this->expectException(QueryException::class);
        $this->existing($movie, $this->rooms->get('P01'), ['presentation_format_id' => null]);
    }

    public function test_showtime_format_fk_rejects_an_invalid_reference(): void
    {
        $format = $this->format('2D');
        $movie = $this->movie();
        $showtime = $this->existing($movie, $this->rooms->get('P01'), ['presentation_format_id' => $format->id]);

        $this->expectException(QueryException::class);
        DB::table('showtimes')->where('id', $showtime->id)->update([
            'presentation_format_id' => 999_999,
        ]);
    }

    public function test_referenced_showtime_format_cannot_be_hard_deleted(): void
    {
        $format = $this->format('2D');
        $showtime = $this->existing($this->movie(), $this->rooms->get('P01'), ['presentation_format_id' => $format->id]);

        $this->expectException(QueryException::class);
        $format->delete();
    }

    public function test_single_showtime_create_requires_format(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2030-06-10 12:00:00', 'Asia/Ho_Chi_Minh'));
        $movie = $this->movie();

        $this->actingAs($this->userWithRole('admin'))
            ->post(route('admin.showtimes.store'), $this->payload($movie, $this->rooms->get('P01')))
            ->assertSessionHasErrors('presentation_format_id');

        $this->assertDatabaseCount('showtimes', 0);
    }

    private function format(string $code): PresentationFormat
    {
        return PresentationFormat::query()->create([
            'code' => $code,
            'name' => $code,
            'is_active' => true,
            'sort_order' => $code === '2D' ? 10 : 20,
        ]);
    }
}
