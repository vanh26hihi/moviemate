<?php

namespace Database\Seeders;

use App\Exceptions\ShowtimeScheduleException;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Room;
use App\Models\Showtime;
use App\Services\ShowtimeScheduleService;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\RealMovieCatalog;
use Illuminate\Database\Seeder;

final class ShowtimeSeeder extends Seeder
{
    public function run(): void
    {
        $movies = Movie::query()->with('supportedPresentationFormats')
            ->whereIn('slug', RealMovieCatalog::slugs(Movie::STATUS_NOW_SHOWING))
            ->where('status', Movie::STATUS_NOW_SHOWING)->orderBy('id')->get();
        if ($movies->isEmpty()) {
            return;
        }
        $schedule = app(ShowtimeScheduleService::class);
        foreach (Cinema::query()->active()->with(['rooms' => fn ($query) => $query->with('presentationCapabilities')->operational()->whereHas('latestPublishedLayout')->orderBy('id')])->get() as $cinemaOffset => $cinema) {
            if ($cinema->rooms->isEmpty()) {
                continue;
            }
            foreach (range(1, 10) as $dayOffset) {
                $date = CarbonImmutable::now($cinema->timezone)->addDays($dayOffset)->toDateString();
                foreach ($cinema->rooms as $roomOffset => $room) {
                    foreach (['09:00', '12:45', '16:30', '20:15'] as $slotOffset => $time) {
                        if ($room->showtimes()->whereDate('show_date', $date)->whereTime('show_time', $time)->exists()) {
                            continue;
                        }

                        $formatCode = $this->formatCodeForSlot($room, $dayOffset, $slotOffset);
                        $format = $room->presentationCapabilities->firstWhere('code', $formatCode)
                            ?? throw new \LogicException("Room {$room->code} lacks seeded {$formatCode} capability.");
                        $compatibleMovies = $movies->filter(fn (Movie $movie): bool => $movie->supportedPresentationFormats->contains('code', $formatCode))->values();
                        if ($compatibleMovies->isEmpty()) {
                            throw new \LogicException("No seeded now-showing Movie supports {$formatCode}.");
                        }
                        $movie = $compatibleMovies[($cinemaOffset + $roomOffset + $dayOffset + $slotOffset) % $compatibleMovies->count()];
                        try {
                            $schedule->schedule([
                                'movie_id' => $movie->id, 'room_id' => $room->id,
                                'presentation_format_id' => $format->id,
                                'show_date' => $date, 'show_time' => $time,
                                'status' => 'active',
                            ]);
                        } catch (ShowtimeScheduleException) {
                            // Existing or longer films may occupy this window; preserve them and continue.
                        }
                    }
                }
            }
        }

        $this->assertSeededShowtimesAreCoherent();
    }

    private function formatCodeForSlot(Room $room, int $dayOffset, int $slotOffset): string
    {
        $supports3d = $room->presentationCapabilities->contains('code', '3D');

        return $supports3d && ($dayOffset + $slotOffset) % 2 === 0 ? '3D' : '2D';
    }

    private function assertSeededShowtimesAreCoherent(): void
    {
        $invalid = Showtime::query()
            ->whereNull('presentation_format_id')
            ->orWhereNotExists(fn ($query) => $query->selectRaw('1')->from('movie_presentation_formats')
                ->whereColumn('movie_presentation_formats.movie_id', 'showtimes.movie_id')
                ->whereColumn('movie_presentation_formats.presentation_format_id', 'showtimes.presentation_format_id'))
            ->orWhereNotExists(fn ($query) => $query->selectRaw('1')->from('room_presentation_capabilities')
                ->whereColumn('room_presentation_capabilities.room_id', 'showtimes.room_id')
                ->whereColumn('room_presentation_capabilities.presentation_format_id', 'showtimes.presentation_format_id'))
            ->count();

        if ($invalid !== 0) {
            throw new \LogicException("Seeded Showtime format configuration is inconsistent for {$invalid} records.");
        }
    }
}
