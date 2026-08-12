<?php

namespace App\Services;

use App\Domain\Showtimes\BulkShowtimeRowResult;
use App\Domain\Showtimes\BulkShowtimeValidationResult;
use App\Domain\Showtimes\ShowtimeScheduleValidationResult;
use App\Exceptions\BulkShowtimeValidationException;
use App\Exceptions\ShowtimeScheduleException;
use App\Models\Movie;
use App\Models\Room;
use App\Models\Showtime;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BulkShowtimeScheduleService
{
    public function __construct(
        private readonly ShowtimeScheduleService $schedule,
        private readonly CinemaAccessService $cinemaAccess,
    ) {}

    /**
     * @param  list<array{row_key: string, movie_id: int, room_id: int, show_date: string, show_time: string}>  $rows
     */
    public function preview(array $rows, User $user): BulkShowtimeValidationResult
    {
        return $this->validateRows($rows, $user);
    }

    /**
     * @param  list<array{row_key: string, movie_id: int, room_id: int, show_date: string, show_time: string}>  $rows
     * @return list<Showtime>
     */
    public function publish(array $rows, User $user, ?Closure $afterPersist = null): array
    {
        $this->authorizeBatchRooms($rows, $user);

        return DB::transaction(function () use ($rows, $user, $afterPersist): array {
            $roomIds = $this->roomIds($rows);
            $lockedRooms = Room::query()
                ->whereIn('id', $roomIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $lockedRooms->load(['cinema.operatingHours', 'latestPublishedLayout']);

            $result = $this->validateRows($rows, $user, $lockedRooms);
            if (! $result->isValid()) {
                throw new BulkShowtimeValidationException($result);
            }

            $created = [];
            foreach ($result->rows as $row) {
                $candidate = $row->candidate->requireValid();
                $showtime = Showtime::query()->create($this->schedule->persistenceData(
                    $row->movie,
                    $row->room,
                    $candidate->layout,
                    $candidate->window,
                ));
                $afterPersist?->__invoke($showtime, $row->rowKey);
                $created[] = $showtime;
            }

            return $created;
        }, 3);
    }

    /**
     * @param  list<array{row_key: string, movie_id: int, room_id: int, show_date: string, show_time: string}>  $rows
     * @param  Collection<int, Room>|null  $resolvedRooms
     */
    private function validateRows(array $rows, User $user, ?Collection $resolvedRooms = null): BulkShowtimeValidationResult
    {
        $rooms = $resolvedRooms ?? Room::query()
            ->whereIn('id', $this->roomIds($rows))
            ->with(['cinema.operatingHours', 'latestPublishedLayout'])
            ->get()
            ->keyBy('id');
        $this->assertAuthorizedSingleCinema($rooms, $user);

        $movies = Movie::query()
            ->whereIn('id', collect($rows)->pluck('movie_id')->unique()->all())
            ->get()
            ->keyBy('id');
        $cinemaId = $rooms->pluck('cinema_id')->unique()->map(fn ($id): int => (int) $id)->first();
        $timezone = $rooms->isEmpty()
            ? $this->schedule->timezone()
            : $this->schedule->timezone($rooms->first());
        $authoritativeNow = CarbonImmutable::now($timezone);

        $baseRows = [];
        foreach ($rows as $row) {
            $movie = $movies->get((int) $row['movie_id']);
            $room = $rooms->get((int) $row['room_id']);
            $candidate = match (true) {
                ! $room => ShowtimeScheduleValidationResult::invalid(new ShowtimeScheduleException(
                    'Phòng chiếu không tồn tại.',
                    'room_id',
                    'ROOM_UNAVAILABLE',
                )),
                ! $movie => ShowtimeScheduleValidationResult::invalid(new ShowtimeScheduleException(
                    'Phim không tồn tại hoặc không thể xếp lịch.',
                    'movie_id',
                    'MOVIE_UNAVAILABLE',
                ), $timezone),
                default => $this->schedule->validateCandidate(
                    $movie,
                    $room,
                    $row['show_date'],
                    $row['show_time'],
                    authoritativeNow: $authoritativeNow,
                    authoritativeLayout: $room->latestPublishedLayout,
                ),
            };
            $baseRows[] = [
                'intent' => $row,
                'movie' => $movie,
                'room' => $room,
                'candidate' => $candidate,
            ];
        }

        $internalConflicts = $this->internalConflicts($baseRows);
        $results = array_map(
            fn (array $row): BulkShowtimeRowResult => new BulkShowtimeRowResult(
                $row['intent']['row_key'],
                $row['movie'],
                $row['room'],
                $row['candidate'],
                $internalConflicts[$row['intent']['row_key']] ?? [],
            ),
            $baseRows,
        );

        return new BulkShowtimeValidationResult($cinemaId, $timezone, $results);
    }

    /**
     * @param  list<array{intent: array<string, mixed>, movie: ?Movie, room: ?Room, candidate: ShowtimeScheduleValidationResult}>  $rows
     * @return array<string, list<array<string, mixed>>>
     */
    private function internalConflicts(array $rows): array
    {
        $conflicts = [];
        $groups = collect($rows)
            ->filter(fn (array $row): bool => $row['room'] !== null && $row['candidate']->window !== null)
            ->groupBy(fn (array $row): int => (int) $row['room']->id);

        foreach ($groups as $roomRows) {
            $sorted = $roomRows->sortBy(fn (array $row): int => $row['candidate']->window->start->getTimestamp())->values();
            for ($left = 0; $left < $sorted->count(); $left++) {
                for ($right = $left + 1; $right < $sorted->count(); $right++) {
                    $first = $sorted[$left];
                    $second = $sorted[$right];
                    if (! $first['candidate']->window->overlaps($second['candidate']->window)) {
                        continue;
                    }

                    $this->recordInternalConflict($conflicts, $first, $second);
                    $this->recordInternalConflict($conflicts, $second, $first);
                }
            }
        }

        return $conflicts;
    }

    /** @param array<string, list<array<string, mixed>>> $conflicts */
    private function recordInternalConflict(array &$conflicts, array $subject, array $other): void
    {
        $otherWindow = $other['candidate']->window;
        $conflicts[$subject['intent']['row_key']][] = [
            'source' => 'batch',
            'row_key' => $other['intent']['row_key'],
            'movie' => $other['movie']?->title,
            'room' => $other['room']?->name,
            'room_code' => $other['room']?->code,
            'start_display' => $otherWindow->start->format('d/m/Y H:i'),
            'end_display' => $otherWindow->movieEnd->format('d/m/Y H:i'),
            'room_ready_display' => $otherWindow->operationalEnd->format('d/m/Y H:i'),
        ];
    }

    /** @param list<array{room_id: int}> $rows */
    private function authorizeBatchRooms(array $rows, User $user): void
    {
        $rooms = Room::query()->whereIn('id', $this->roomIds($rows))->get()->keyBy('id');
        $this->assertAuthorizedSingleCinema($rooms, $user);
    }

    /** @param Collection<int, Room> $rooms */
    private function assertAuthorizedSingleCinema(Collection $rooms, User $user): void
    {
        $rooms->each(fn (Room $room) => $this->cinemaAccess->authorizeCinema($user, (int) $room->cinema_id));
        if ($rooms->pluck('cinema_id')->unique()->count() > 1) {
            throw ValidationException::withMessages([
                'rows' => 'Mỗi lô suất chiếu chỉ được chứa phòng thuộc một chi nhánh.',
            ]);
        }
    }

    /** @param list<array{room_id: int}> $rows @return list<int> */
    private function roomIds(array $rows): array
    {
        return collect($rows)->pluck('room_id')->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all();
    }
}
