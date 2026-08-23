<?php

namespace App\Services;

use App\Domain\Showtimes\ShowtimeScheduleValidationResult;
use App\Domain\Showtimes\ShowtimeWindow;
use App\Exceptions\InvalidMovieRuntimeException;
use App\Exceptions\PriceBookException;
use App\Exceptions\ShowtimeConflictException;
use App\Exceptions\ShowtimeScheduleConfigurationException;
use App\Exceptions\ShowtimeScheduleException;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Movie;
use App\Models\PresentationFormat;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\Showtime;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class ShowtimeScheduleService
{
    public function validateRequestedSchedule(
        int $movieId,
        int $roomId,
        string $showDate,
        string $showTime,
        ?int $ignoreShowtimeId = null
    ): void {
        $movie = Movie::query()->findOrFail($movieId);
    
        $room = Room::query()
            ->with('cinema')
            ->findOrFail($roomId);
    
        if ($room->status !== 'active') {
            throw new ShowtimeScheduleException(
                'room_id',
                'Phòng chiếu hiện không hoạt động.'
            );
        }
    
        $start = Carbon::createFromFormat(
            'Y-m-d H:i',
            $showDate.' '.$showTime,
            $room->cinema?->timezone ?? config('app.timezone')
        );
    
        if ($start->isPast()) {
            throw new ShowtimeScheduleException(
                'show_time',
                'Thời gian chiếu không được nằm trong quá khứ.'
            );
        }
    
        if (! $movie->duration || (int) $movie->duration <= 0) {
            throw new ShowtimeScheduleException(
                'movie_id',
                'Phim chưa có thời lượng hợp lệ.'
            );
        }
    
        $movieEnd = $start
            ->copy()
            ->addMinutes((int) $movie->duration);
    
        $cleaningBuffer = $this->cleaningBufferMinutes();
    
        $operationalEnd = $movieEnd
            ->copy()
            ->addMinutes($cleaningBuffer);
    
        $query = Showtime::query()
            ->where('room_id', $roomId)
            ->where('status', '!=', 'cancelled');
    
        if ($ignoreShowtimeId !== null) {
            $query->whereKeyNot($ignoreShowtimeId);
        }
    
        $existingShowtimes = $query
            ->whereDate('show_date', $showDate)
            ->with('movie')
            ->get();
    
        foreach ($existingShowtimes as $existing) {
            if (
                ! $existing->show_date
                || ! $existing->show_time
                || ! $existing->movie?->duration
            ) {
                continue;
            }
    
            $existingStart = Carbon::createFromFormat(
                'Y-m-d H:i',
                $existing->show_date->format('Y-m-d')
                    .' '
                    .Carbon::parse($existing->show_time)->format('H:i'),
                $room->cinema?->timezone ?? config('app.timezone')
            );
    
            $existingEnd = $existingStart
                ->copy()
                ->addMinutes((int) $existing->movie->duration)
                ->addMinutes($cleaningBuffer);
    
            $hasConflict =
                $start->lt($existingEnd)
                && $operationalEnd->gt($existingStart);
    
            if ($hasConflict) {
                throw new ShowtimeScheduleException(
                    'show_time',
                    'Khung giờ này bị trùng với suất chiếu khác trong cùng phòng.'
                );
            }
        }
    }
    public const MAX_RUNTIME_MINUTES = 600;

    public const MAX_CLEANING_BUFFER_MINUTES = 180;

    /** @var list<string> */
    public const NON_OCCUPYING_STATUSES = ['cancelled'];

    /** @var list<string> */
    public const PROTECTED_STRUCTURAL_FIELDS = [
        'movie_id',
        'show_date',
        'show_time',
        'room_id',
        'room_layout_id',
        'presentation_format_id',
    ];

    private readonly ShowtimeTicketPriceService $snapshotPrices;

    public function __construct(?ShowtimeTicketPriceService $snapshotPrices = null)
    {
        $this->snapshotPrices = $snapshotPrices ?? app(ShowtimeTicketPriceService::class);
    }

    public function timezone(?Room $room = null): string
    {
        $room?->loadMissing('cinema');
        $timezone = $room?->cinema?->timezone ?? config('cinema.timezone');
        if (! is_string($timezone) || trim($timezone) === '' || ! in_array($timezone, timezone_identifiers_list(), true)) {
            throw new ShowtimeScheduleConfigurationException('Timezone nghiệp vụ của rạp không hợp lệ.');
        }

        return $timezone;
    }

    public function cleaningBufferMinutes(?Room $room = null): int
    {
        $room?->loadMissing('cinema');
        $buffer = $room?->cleaning_buffer_minutes
            ?? $room?->cinema?->default_cleaning_buffer_minutes
            ?? config('cinema.showtime_cleaning_buffer_minutes');
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

    public function calculateStart(string $showDate, string $showTime, ?Room $room = null): CarbonImmutable
    {
        $time = strlen($showTime) === 5 ? $showTime : substr($showTime, 0, 5);
        try {
            $start = CarbonImmutable::createFromFormat('!Y-m-d H:i', "{$showDate} {$time}", $this->timezone($room));
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

    public function window(Movie $movie, string $showDate, string $showTime, ?Room $room = null): ShowtimeWindow
    {
        $start = $this->calculateStart($showDate, $showTime, $room);
        $runtime = $this->validateRuntime($movie);
        $buffer = $this->cleaningBufferMinutes($room);
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
        $showtime->loadMissing(['movie', 'room.cinema']);

        return $this->window(
            $showtime->movie,
            $showtime->show_date->format('Y-m-d'),
            (string) $showtime->show_time,
            $showtime->room,
        );
    }

    public function validateCandidate(
        Movie $movie,
        Room $room,
        string $showDate,
        string $showTime,
        ?Showtime $existingShowtime = null,
        ?CarbonImmutable $authoritativeNow = null,
        ?RoomLayout $authoritativeLayout = null,
        ?int $presentationFormatId = null,
        ?PresentationFormat $authoritativePresentationFormat = null,
    ): ShowtimeScheduleValidationResult {
        $timezone = null;
        $window = null;
        $layout = null;
        $presentationFormat = null;
        $isFuture = null;
        $isWithinOperatingHours = null;
        $isConflictFree = null;

        try {
            $presentationFormat = $this->resolvePresentationFormat(
                $presentationFormatId,
                $authoritativePresentationFormat,
            );
            $this->assertRoomIsOperational($room);
            $this->assertMovieIsSchedulable($movie);
            if ($presentationFormat) {
                $this->assertMovieSupportsFormat($movie, $presentationFormat);
                $this->assertRoomSupportsFormat($room, $presentationFormat);
            }
            $timezone = $this->timezone($room);
            $window = $this->window($movie, $showDate, $showTime, $room);
            $this->assertFutureStart($window, $authoritativeNow);
            $isFuture = true;
            $layout = $this->candidateLayout($room, $existingShowtime, $authoritativeLayout);
            $this->assertWithinOperatingHours($room, $window);
            $isWithinOperatingHours = true;
            $this->assertNoConflict($room, $window, 'active', $existingShowtime?->id);
            $isConflictFree = true;
            try {
                $ticketPriceSnapshots = $this->snapshotPrices->preview($room, $layout, $window);
            } catch (PriceBookException|LogicException $exception) {
                throw new ShowtimeScheduleException(
                    $exception->getMessage(),
                    'pricing',
                    'SHOWTIME_PRICE_UNRESOLVABLE',
                );
            }

            return ShowtimeScheduleValidationResult::valid(
                $timezone,
                $window,
                $layout,
                $presentationFormat,
                $ticketPriceSnapshots,
            );
        } catch (ShowtimeScheduleException $exception) {
            if ($exception->failureCode === 'PAST_START') {
                $isFuture = false;
            } elseif (in_array($exception->failureCode, ['CINEMA_CLOSED', 'OUTSIDE_START_WINDOW'], true)) {
                $isWithinOperatingHours = false;
            } elseif ($exception->failureCode === 'ROOM_CONFLICT') {
                $isConflictFree = false;
            }

            return ShowtimeScheduleValidationResult::invalid(
                $exception,
                $timezone,
                $window,
                $layout,
                $presentationFormat,
                $isFuture,
                $isWithinOperatingHours,
                $isConflictFree,
            );
        }
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

    public function assertMovieDurationChangeSafe(Movie $movie, int $newDuration): void
    {
        if ($newDuration === (int) $movie->duration) {
            return;
        }
        $candidateMovie = $movie->replicate();
        $candidateMovie->duration = $newDuration;
        $showtimes = $movie->showtimes()->with(['room.cinema', 'movie'])
            ->whereNotIn('status', self::NON_OCCUPYING_STATUSES)->get();
        foreach ($showtimes as $showtime) {
            $current = $this->windowFor($showtime);
            if (! $current->start->isFuture()) {
                continue;
            }
            $changed = $this->window($candidateMovie, $showtime->show_date->format('Y-m-d'), (string) $showtime->show_time, $showtime->room);
            if ($this->findConflicts((int) $showtime->room_id, $changed, (int) $showtime->id)->isNotEmpty()) {
                throw new ShowtimeScheduleException('Thời lượng mới làm trùng lịch phòng. Hãy sắp xếp lại suất chiếu trước.', 'duration');
            }
        }
    }

    public function schedule(array $data, ?Closure $afterPersist = null): Showtime
    {
        return DB::transaction(function () use ($data, $afterPersist): Showtime {
            $this->assertNormalMutationStatus($data);
            $room = $this->lockAndValidateRoom((int) $data['room_id']);
            $movie = $this->resolveSchedulableMovie((int) $data['movie_id'], true);
            $presentationFormat = $this->lockPresentationFormat($data['presentation_format_id'] ?? null);
            $candidate = $this->validateCandidate(
                $movie,
                $room,
                $data['show_date'],
                $data['show_time'],
                presentationFormatId: isset($data['presentation_format_id']) ? (int) $data['presentation_format_id'] : null,
                authoritativePresentationFormat: $presentationFormat,
            )->requireValid();

            $priceSnapshots = $candidate->ticketPriceSnapshots;

            $showtime = Showtime::query()->create($this->persistenceData(
                $movie,
                $room,
                $candidate->layout,
                $candidate->window,
                $candidate->presentationFormat,
            ));
            $this->persistPriceSnapshots($showtime, $priceSnapshots);
            $afterPersist?->__invoke($showtime);

            return $showtime;
        }, 3);
    }

    public function reschedule(Showtime $showtime, array $data, ?Closure $afterPersist = null): Showtime
    {
        return DB::transaction(function () use ($showtime, $data, $afterPersist): Showtime {
            $this->assertNormalMutationStatus($data);
            $roomIds = collect([(int) $showtime->room_id, (int) $data['room_id']])->unique()->sort()->values();
            $lockedRooms = Room::query()->whereIn('id', $roomIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($lockedRooms->count() !== $roomIds->count()) {
                throw new ShowtimeScheduleException('Phòng chiếu không tồn tại.', 'room_id');
            }

            $movieIds = collect([(int) $showtime->movie_id, (int) $data['movie_id']])->unique()->sort()->values();
            $lockedMovies = Movie::query()->whereIn('id', $movieIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($lockedMovies->count() !== $movieIds->count()) {
                throw new ShowtimeScheduleException('Phim đã chọn không tồn tại.', 'movie_id', 'MOVIE_UNAVAILABLE');
            }

            $targetFormatId = isset($data['presentation_format_id']) ? (int) $data['presentation_format_id'] : null;
            $formatIds = collect([$showtime->presentation_format_id, $targetFormatId])
                ->filter(fn ($id): bool => $id !== null)
                ->map(fn ($id): int => (int) $id)
                ->unique()->sort()->values();
            $lockedFormats = PresentationFormat::query()->whereIn('id', $formatIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($lockedFormats->count() !== $formatIds->count()) {
                throw new ShowtimeScheduleException(
                    'Định dạng trình chiếu đã chọn hiện không còn hoạt động.',
                    'presentation_format_id',
                    'PRESENTATION_FORMAT_INACTIVE',
                );
            }

            $lockedShowtime = Showtime::query()->whereKey($showtime->id)->lockForUpdate()->firstOrFail();
            $sourceFormatWasLocked = $lockedShowtime->presentation_format_id === null
                ? $showtime->presentation_format_id === null
                : $lockedFormats->has((int) $lockedShowtime->presentation_format_id);
            if (! $lockedRooms->has((int) $lockedShowtime->room_id)
                || ! $lockedMovies->has((int) $lockedShowtime->movie_id)
                || ! $sourceFormatWasLocked) {
                throw new ShowtimeScheduleException(
                    'Suất chiếu vừa được thay đổi. Vui lòng tải lại trước khi cập nhật.',
                    'showtime',
                    'CONCURRENT_SHOWTIME_CHANGE',
                );
            }

            $this->assertSourceCanBeRescheduled($lockedShowtime);
            $targetRoom = $lockedRooms->get((int) $data['room_id']);
            $movie = $lockedMovies->get((int) $data['movie_id']);
            $presentationFormat = $targetFormatId === null ? null : $lockedFormats->get($targetFormatId);
            $candidate = $this->validateCandidate(
                $movie,
                $targetRoom,
                $data['show_date'],
                $data['show_time'],
                $lockedShowtime,
                presentationFormatId: $targetFormatId,
                authoritativePresentationFormat: $presentationFormat,
            )->requireValid();

            $structuralChanges = $this->hasStructuralChanges(
                $lockedShowtime,
                $movie,
                $targetRoom,
                $candidate->layout,
                $candidate->window,
                $candidate->presentationFormat,
            );
            if ($structuralChanges
                && $this->hasBookingHistory($lockedShowtime)) {
                throw new ShowtimeScheduleException(
                    'Suất chiếu đã phát sinh đơn đặt vé nên không thể thay đổi phim, phòng, ngày hoặc giờ chiếu.',
                    'showtime',
                    'SHOWTIME_HAS_BOOKING_HISTORY',
                );
            }

            $before = clone $lockedShowtime;
            $priceSnapshots = $structuralChanges
                ? $candidate->ticketPriceSnapshots
                : null;
            $lockedShowtime->update($this->persistenceData(
                $movie,
                $targetRoom,
                $candidate->layout,
                $candidate->window,
                $candidate->presentationFormat,
            ));
            if ($priceSnapshots !== null) {
                $this->persistPriceSnapshots($lockedShowtime, $priceSnapshots, true);
            }
            $updated = $lockedShowtime->fresh([
                'movie', 'room.cinema', 'cinema', 'roomLayout', 'presentationFormat', 'ticketPrices.seatType',
            ]);
            $afterPersist?->__invoke($updated, $before);

            return $updated;
        }, 3);
    }

    private function resolveSchedulableMovie(int $movieId, bool $lockForUpdate = false): Movie
    {
        $query = Movie::query()->whereKey($movieId);
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }
        $movie = $query->first();
        if (! $movie) {
            throw new ShowtimeScheduleException('Phim đã chọn không tồn tại.', 'movie_id', 'MOVIE_UNAVAILABLE');
        }
        $this->assertMovieIsSchedulable($movie);

        return $movie;
    }

    private function lockPresentationFormat(mixed $presentationFormatId): ?PresentationFormat
    {
        if ($presentationFormatId === null || $presentationFormatId === '') {
            return null;
        }

        return PresentationFormat::query()
            ->whereKey((int) $presentationFormatId)
            ->lockForUpdate()
            ->first();
    }

    private function resolvePresentationFormat(
        ?int $presentationFormatId,
        ?PresentationFormat $authoritativePresentationFormat = null,
    ): ?PresentationFormat {
        if ($presentationFormatId === null) {
            throw new ShowtimeScheduleException(
                'Vui lòng chọn định dạng trình chiếu.',
                'presentation_format_id',
                'PRESENTATION_FORMAT_REQUIRED',
            );
        }

        $format = $authoritativePresentationFormat;
        if (! $format || (int) $format->id !== $presentationFormatId) {
            $format = PresentationFormat::query()->find($presentationFormatId);
        }
        if (! $format || ! $format->is_active) {
            throw new ShowtimeScheduleException(
                'Định dạng trình chiếu đã chọn hiện không còn hoạt động.',
                'presentation_format_id',
                'PRESENTATION_FORMAT_INACTIVE',
            );
        }

        return $format;
    }

    private function assertMovieSupportsFormat(Movie $movie, PresentationFormat $format): void
    {
        $supportsFormat = $movie->relationLoaded('supportedPresentationFormats')
            ? $movie->supportedPresentationFormats->contains('id', $format->id)
            : $movie->supportedPresentationFormats()->whereKey($format->id)->exists();
        if (! $supportsFormat) {
            throw new ShowtimeScheduleException(
                'Phim không hỗ trợ định dạng trình chiếu đã chọn.',
                'presentation_format_id',
                'MOVIE_FORMAT_UNSUPPORTED',
            );
        }
    }

    private function assertRoomSupportsFormat(Room $room, PresentationFormat $format): void
    {
        $supportsFormat = $room->relationLoaded('presentationCapabilities')
            ? $room->presentationCapabilities->contains('id', $format->id)
            : $room->presentationCapabilities()->whereKey($format->id)->exists();
        if (! $supportsFormat) {
            throw new ShowtimeScheduleException(
                'Phòng chiếu không hỗ trợ định dạng trình chiếu đã chọn.',
                'presentation_format_id',
                'ROOM_FORMAT_UNSUPPORTED',
            );
        }
    }

    private function assertMovieIsSchedulable(Movie $movie): void
    {
        $this->validateRuntime($movie);
        if (! in_array($movie->status, Movie::SCHEDULABLE_STATUSES, true)) {
            throw new ShowtimeScheduleException('Phim đã ngừng chiếu không thể xếp lịch.', 'movie_id', 'MOVIE_UNAVAILABLE');
        }
    }

    private function lockAndValidateRoom(int $roomId): Room
    {
        $room = Room::query()->whereKey($roomId)->lockForUpdate()->first();
        if (! $room) {
            throw new ShowtimeScheduleException('Phòng chiếu không tồn tại.', 'room_id', 'ROOM_UNAVAILABLE');
        }
        $this->assertRoomIsOperational($room);

        return $room;
    }

    private function assertRoomIsOperational(Room $room): void
    {
        $room->loadMissing('cinema');
        if ($room->status !== 'active' || ! $room->cinema || $room->cinema->status !== 'active' || $room->cinema->archived_at !== null) {
            throw new ShowtimeScheduleException('Phòng chiếu và chi nhánh phải đang hoạt động.', 'room_id', 'ROOM_UNAVAILABLE');
        }
    }

    private function latestPublishedLayout(Room $room): RoomLayout
    {
        $layout = RoomLayout::query()->published()->where('room_id', $room->id)->orderByDesc('version')->first();
        if (! $layout) {
            throw new ShowtimeScheduleException('Phòng phải có sơ đồ đã phát hành trước khi xếp lịch. Hãy phát hành sơ đồ hợp lệ rồi thử lại.', 'room_id', 'LAYOUT_UNAVAILABLE');
        }

        return $layout;
    }

    public function assertWithinOperatingHours(Room $room, ShowtimeWindow $window): void
    {
        $room->loadMissing('cinema.operatingHours');
        $hours = $room->cinema?->operatingHours->firstWhere('day_of_week', $window->start->dayOfWeekIso);
        if (! $hours) {
            return;
        }
        if ($hours->is_closed) {
            throw new ShowtimeScheduleException('Chi nhánh đóng cửa cả ngày này.', 'show_date', 'CINEMA_CLOSED');
        }

        $time = $window->start->format('H:i:s');
        $opensAt = $this->normalizeTime($hours->opens_at);
        $latestStart = $this->normalizeTime($hours->latest_show_start_at);
        if (! $opensAt || ! $latestStart || $time < $opensAt || $time > $latestStart) {
            throw new ShowtimeScheduleException('Chi nhánh không nhận suất chiếu mới vào khung giờ này.', 'show_time', 'OUTSIDE_START_WINDOW');
        }
    }

    public function hasBookingHistory(Showtime $showtime): bool
    {
        return Booking::query()->where('showtime_id', $showtime->id)->exists()
            || BookingSeat::query()->where('showtime_id', $showtime->id)->exists();
    }

    private function assertFutureStart(ShowtimeWindow $window, ?CarbonImmutable $authoritativeNow = null): void
    {
        $now = ($authoritativeNow ?? CarbonImmutable::now($window->start->getTimezone()))
            ->setTimezone($window->start->getTimezone());
        if ($window->start->lte($now)) {
            throw new ShowtimeScheduleException(
                sprintf(
                    'Giờ bắt đầu phải nằm trong tương lai theo múi giờ của chi nhánh (hiện tại %s).',
                    $now->format('d/m/Y H:i:s'),
                ),
                'show_time',
                'PAST_START',
            );
        }
    }

    private function assertNormalMutationStatus(array $data): void
    {
        if (array_key_exists('status', $data) && $data['status'] !== 'active') {
            throw new ShowtimeScheduleException(
                'Tạo và cập nhật lịch thông thường chỉ chấp nhận trạng thái đang hoạt động.',
                'status',
                'INVALID_STATUS',
            );
        }
    }

    private function assertSourceCanBeRescheduled(Showtime $showtime): void
    {
        if ($showtime->status !== 'active') {
            throw new ShowtimeScheduleException(
                'Chỉ suất chiếu đang hoạt động và sắp diễn ra mới có thể chỉnh sửa.',
                'showtime',
                'SHOWTIME_NOT_MUTABLE',
            );
        }

        $window = $this->windowFor($showtime);
        if (! CarbonImmutable::now($window->start->getTimezone())->lt($window->start)) {
            throw new ShowtimeScheduleException(
                'Chỉ suất chiếu đang hoạt động và sắp diễn ra mới có thể chỉnh sửa.',
                'showtime',
                'SHOWTIME_NOT_MUTABLE',
            );
        }
    }

    private function candidateLayout(
        Room $room,
        ?Showtime $existingShowtime,
        ?RoomLayout $authoritativeLayout = null,
    ): RoomLayout {
        if ($existingShowtime && (int) $room->id === (int) $existingShowtime->room_id) {
            $layout = RoomLayout::query()->published()
                ->whereKey($existingShowtime->room_layout_id)
                ->where('room_id', $room->id)
                ->first();
            if (! $layout) {
                throw new ShowtimeScheduleException(
                    'Suất chiếu hiện tại không còn sơ đồ đã phát hành hợp lệ. Hãy kiểm tra lại cấu hình phòng.',
                    'room_id',
                    'LAYOUT_UNAVAILABLE',
                );
            }

            return $layout;
        }

        if ($authoritativeLayout
            && (int) $authoritativeLayout->room_id === (int) $room->id
            && $authoritativeLayout->status === 'published') {
            return $authoritativeLayout;
        }

        return $this->latestPublishedLayout($room);
    }

    private function hasStructuralChanges(
        Showtime $showtime,
        Movie $movie,
        Room $room,
        RoomLayout $layout,
        ShowtimeWindow $window,
        ?PresentationFormat $presentationFormat,
    ): bool {
        $current = [
            'movie_id' => (int) $showtime->movie_id,
            'show_date' => $showtime->show_date->format('Y-m-d'),
            'show_time' => substr((string) $showtime->show_time, 0, 8),
            'room_id' => (int) $showtime->room_id,
            'room_layout_id' => (int) $showtime->room_layout_id,
            'presentation_format_id' => $showtime->presentation_format_id === null ? null : (int) $showtime->presentation_format_id,
        ];
        $candidate = [
            'movie_id' => (int) $movie->id,
            'show_date' => $window->start->toDateString(),
            'show_time' => $window->start->format('H:i:s'),
            'room_id' => (int) $room->id,
            'room_layout_id' => (int) $layout->id,
            'presentation_format_id' => $presentationFormat?->id,
        ];

        return $current !== $candidate;
    }

    private function normalizeTime(?string $time): ?string
    {
        if (! $time) {
            return null;
        }

        return strlen($time) === 5 ? $time.':00' : substr($time, 0, 8);
    }

    public function persistenceData(
        Movie $movie,
        Room $room,
        RoomLayout $layout,
        ShowtimeWindow $window,
        PresentationFormat $presentationFormat,
    ): array {
        return [
            'movie_id' => $movie->id,
            'cinema_id' => $room->cinema_id,
            'room_id' => $room->id,
            'room_layout_id' => $layout->id,
            'presentation_format_id' => $presentationFormat->id,
            'show_date' => $window->start->toDateString(),
            'show_time' => $window->start->format('H:i:s'),
            'status' => 'active',
        ];
    }

    public function priceSnapshots(Room $room, RoomLayout $layout, ShowtimeWindow $window): Collection
    {
        return $this->snapshotPrices->preview($room, $layout, $window);
    }

    public function persistPriceSnapshots(Showtime $showtime, Collection $snapshots, bool $replace = false): void
    {
        $this->snapshotPrices->persist($showtime, $snapshots, $replace);
    }

    public function persistPriceSnapshotBatch(array $items): void
    {
        $this->snapshotPrices->persistBatch($items);
    }
}
