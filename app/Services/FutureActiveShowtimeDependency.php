<?php

namespace App\Services;

use App\Models\Showtime;
use Illuminate\Database\Eloquent\Builder;

final class FutureActiveShowtimeDependency
{
    public function __construct(private readonly ShowtimeLifecycleService $lifecycle) {}

    /** @param list<int> $formatIds */
    public function conflictingMovieFormatId(int $movieId, array $formatIds, bool $lock = false): ?int
    {
        return $this->conflictingFormatId(
            fn (Builder $query): Builder => $query->where('showtimes.movie_id', $movieId),
            $formatIds,
            $lock,
        );
    }

    /** @param list<int> $formatIds */
    public function conflictingRoomFormatId(int $roomId, array $formatIds, bool $lock = false): ?int
    {
        return $this->conflictingFormatId(
            fn (Builder $query): Builder => $query->where('showtimes.room_id', $roomId),
            $formatIds,
            $lock,
        );
    }

    /** @param list<int> $formatIds */
    public function conflictingFormatIdForArchive(array $formatIds, bool $lock = false): ?int
    {
        return $this->conflictingFormatId(fn (Builder $query): Builder => $query, $formatIds, $lock);
    }

    /**
     * @param  callable(Builder): Builder  $scope
     * @param  list<int>  $formatIds
     */
    private function conflictingFormatId(callable $scope, array $formatIds, bool $lock): ?int
    {
        if ($formatIds === []) {
            return null;
        }

        $query = Showtime::query()
            ->where('showtimes.status', 'active')
            ->whereIn('showtimes.presentation_format_id', $formatIds);
        $query = $scope($query);
        $query = $this->lifecycle->applyFilter($query, ShowtimeLifecycleService::UPCOMING)
            ->orderBy('showtimes.presentation_format_id')
            ->orderBy('showtimes.id');

        if ($lock) {
            $query->lockForUpdate();
        }

        $formatId = $query->value('showtimes.presentation_format_id');

        return $formatId === null ? null : (int) $formatId;
    }
}
