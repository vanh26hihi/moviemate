<?php

namespace App\Services;

use App\Domain\Money\VndAmount;
use App\Domain\Showtimes\ShowtimeWindow;
use App\Exceptions\InvalidMovieRuntimeException;
use App\Exceptions\ShowtimeConflictException;
use App\Exceptions\ShowtimeScheduleConfigurationException;
use App\Exceptions\ShowtimeScheduleException;
use App\Models\Movie;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\Showtime;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShowtimeScheduleService
{
    public const MAX_RUNTIME_MINUTES = 600;

    public const MAX_CLEANING_BUFFER_MINUTES = 180;

    /** @var list<string> */
    public const NON_OCCUPYING_STATUSES = ['cancelled'];

    public function timezone(): string
    {
        $timezone = config('cinema.timezone');
        if (! is_string($timezone) || trim($timezone) === '' || ! in_array($timezone, timezone_identifiers_list(), true)) {
            throw new ShowtimeScheduleConfigurationException('Timezone nghiệp vụ của rạp không hợp lệ.');
        }

        return $timezone;
    }

    public function cleaningBufferMinutes(): int
    {
        $buffer = config('cinema.showtime_cleaning_buffer_minutes');
        if (! is_int($buffer) || $buffer < 0 || $buffer > self::MAX_CLEANING_BUFFER_MINUTES) {
            throw new ShowtimeScheduleConfigurationException('Thời gian vệ sinh phòng phải là số nguyên từ 0 đến 180 phút.');
        }

        return $buffer;
    }

    public function validateRuntime(Movie $movie): int
    {
        $runtime = $movie->getRawOriginal('duration');
        if (! is_int($runtime) && ! (is_string($runtime) && preg_match('/^\d+$/', $runtime))) {
            throw new InvalidMovieRuntimeException;
        }

        $runtime = (int) $runtime;
        if ($runtime < 1 || $runtime > self::MAX_RUNTIME_MINUTES) {
            throw new InvalidMovieRuntimeException;
        }

        return $runtime;
    }

    public function calculateStart(string $showDate, string $showTime): CarbonImmutable
    {
        $time = strlen($showTime) === 5 ? $showTime : substr($showTime, 0, 5);
        try {
            $start = CarbonImmutable::createFromFormat('!Y-m-d H:i', "{$showDate} {$time}", $this->timezone());
        } catch (InvalidFormatException) {
            throw new ShowtimeScheduleException('Ngày hoặc giờ bắt đầu không hợp lệ.', 'show_time');
        }
        if (! $start || $start->format('Y-m-d H:i') !== "{$showDate} {$time}") {
            throw new ShowtimeScheduleException('Ngày hoặc giờ bắt đầu không hợp lệ.', 'show_time');
        }

        return $start;
    }

    public function calculateMovieEnd(CarbonImmutable $start, int $runtimeMinutes): CarbonImmutable
    {
        return $start->addMinutes($runtimeMinutes);
    }

    public function calculateOperationalEnd(CarbonImmutable $movieEnd, ?int $bufferMinutes = null): CarbonImmutable
    {
        return $movieEnd->addMinutes($bufferMinutes ?? $this->cleaningBufferMinutes());
    }

    public function window(Movie $movie, string $showDate, string $showTime): ShowtimeWindow
    {
        $start = $this->calculateStart($showDate, $showTime);
        $runtime = $this->validateRuntime($movie);
        $buffer = $this->cleaningBufferMinutes();
        $movieEnd = $this->calculateMovieEnd($start, $runtime);

        return new ShowtimeWindow(
            $start,
            $movieEnd,
            $this->calculateOperationalEnd($movieEnd, $buffer),
            $runtime,
            $buffer,
        );
    }

    public function windowFor(Showtime $showtime): ShowtimeWindow
    {
        $showtime->loadMissing('movie');

        return $this->window(
            $showtime->movie,
            $showtime->show_date->format('Y-m-d'),
            (string) $showtime->show_time,
        );
    }

    /** @return Collection<int, array{showtime: Showtime, window: ShowtimeWindow}> */
    public function findConflicts(
        int $roomId,
        ShowtimeWindow $newWindow,
        ?int $exceptShowtimeId = null,
    ): Collection {
        $lookback = self::MAX_RUNTIME_MINUTES + self::MAX_CLEANING_BUFFER_MINUTES;
        $fromDate = $newWindow->start->subMinutes($lookback)->toDateString();
        $untilDate = $newWindow->operationalEnd->addDay()->toDateString();

        return Showtime::query()
            ->with(['movie', 'room'])
            ->where('room_id', $roomId)
            ->whereNotIn('status', self::NON_OCCUPYING_STATUSES)
            ->where('show_date', '>=', $fromDate)
            ->where('show_date', '<', $untilDate)
            ->when($exceptShowtimeId, fn ($query) => $query->whereKeyNot($exceptShowtimeId))
            ->orderBy('show_date')->orderBy('show_time')
            ->get()
            ->map(fn (Showtime $showtime) => [
                'showtime' => $showtime,
                'window' => $this->windowFor($showtime),
            ])
            ->filter(fn (array $candidate) => $newWindow->overlaps($candidate['window']))
            ->values();
    }

    public function assertNoConflict(
        Room $room,
        ShowtimeWindow $window,
        string $status,
        ?int $exceptShowtimeId = null,
    ): void {
        if (in_array($status, self::NON_OCCUPYING_STATUSES, true)) {
            return;
        }

        $conflict = $this->findConflicts($room->id, $window, $exceptShowtimeId)->first();
        if ($conflict) {
            throw new ShowtimeConflictException($conflict['showtime'], $conflict['window']);
        }
    }

    public function schedule(array $data): Showtime
    {
        $movie = $this->resolveSchedulableMovie((int) $data['movie_id']);
        $window = $this->window($movie, $data['show_date'], $data['show_time']);

        return DB::transaction(function () use ($data, $movie, $window): Showtime {
            $room = $this->lockAndValidateRoom((int) $data['room_id']);
            $layout = $this->latestPublishedLayout($room);
            $this->assertNoConflict($room, $window, $data['status']);

            return Showtime::query()->create($this->persistenceData($data, $movie, $room, $layout, $window));
        }, 3);
    }

    public function reschedule(Showtime $showtime, array $data): Showtime
    {
        $movie = $this->resolveSchedulableMovie((int) $data['movie_id']);
        $window = $this->window($movie, $data['show_date'], $data['show_time']);

        return DB::transaction(function () use ($showtime, $data, $movie, $window): Showtime {
            $roomIds = collect([(int) $showtime->room_id, (int) $data['room_id']])->unique()->sort()->values();
            $lockedRooms = Room::query()->whereIn('id', $roomIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($lockedRooms->count() !== $roomIds->count()) {
                throw new ShowtimeScheduleException('Phòng chiếu không tồn tại.', 'room_id');
            }

            $lockedShowtime = Showtime::query()->whereKey($showtime->id)->lockForUpdate()->firstOrFail();
            $targetRoom = $lockedRooms->get((int) $data['room_id']);
            $this->assertRoomIsOperational($targetRoom);

            if ((int) $targetRoom->id === (int) $lockedShowtime->room_id) {
                $layout = RoomLayout::query()->published()
                    ->whereKey($lockedShowtime->room_layout_id)
                    ->where('room_id', $targetRoom->id)->first();
                if (! $layout) {
                    throw new ShowtimeScheduleException('Suất chiếu hiện tại không có layout published hợp lệ.', 'room_id');
                }
            } else {
                $layout = $this->latestPublishedLayout($targetRoom);
            }

            $this->assertNoConflict($targetRoom, $window, $data['status'], $lockedShowtime->id);
            $lockedShowtime->update($this->persistenceData($data, $movie, $targetRoom, $layout, $window));

            return $lockedShowtime->fresh(['movie', 'room', 'roomLayout']);
        }, 3);
    }

    private function resolveSchedulableMovie(int $movieId): Movie
    {
        $movie = Movie::query()->findOrFail($movieId);
        $this->validateRuntime($movie);
        if ($movie->status === 'stopped') {
            throw new ShowtimeScheduleException('Phim đã ngừng chiếu không thể xếp lịch.', 'movie_id');
        }

        return $movie;
    }

    private function lockAndValidateRoom(int $roomId): Room
    {
        $room = Room::query()->whereKey($roomId)->lockForUpdate()->first();
        if (! $room) {
            throw new ShowtimeScheduleException('Phòng chiếu không tồn tại.', 'room_id');
        }
        $this->assertRoomIsOperational($room);

        return $room;
    }

    private function assertRoomIsOperational(Room $room): void
    {
        $room->loadMissing('cinema');
        if ($room->status !== 'active' || ! $room->cinema || $room->cinema->status !== 'active' || $room->cinema->archived_at !== null) {
            throw new ShowtimeScheduleException('Phòng chiếu và chi nhánh phải đang hoạt động.', 'room_id');
        }
    }

    private function latestPublishedLayout(Room $room): RoomLayout
    {
        $layout = RoomLayout::query()->published()->where('room_id', $room->id)->orderByDesc('version')->first();
        if (! $layout) {
            throw new ShowtimeScheduleException('Phòng phải có layout published trước khi xếp lịch.', 'room_id');
        }

        return $layout;
    }

    private function persistenceData(
        array $data,
        Movie $movie,
        Room $room,
        RoomLayout $layout,
        ShowtimeWindow $window,
    ): array {
        return [
            'movie_id' => $movie->id,
            'cinema_id' => $room->cinema_id,
            'room_id' => $room->id,
            'room_layout_id' => $layout->id,
            'show_date' => $window->start->toDateString(),
            'show_time' => $window->start->format('H:i:s'),
            'price' => VndAmount::fromInput($data['price'], Showtime::MAX_PRICE)->value(),
            'vip_price' => isset($data['vip_price'])
                ? VndAmount::fromInput($data['vip_price'], Showtime::MAX_PRICE)->value()
                : null,
            'status' => $data['status'],
        ];
    }
}
