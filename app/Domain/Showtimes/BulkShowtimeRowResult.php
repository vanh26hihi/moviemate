<?php

namespace App\Domain\Showtimes;

use App\Exceptions\ShowtimeConflictException;
use App\Models\Movie;
use App\Models\PresentationFormat;
use App\Models\Room;

final readonly class BulkShowtimeRowResult
{
    /**
     * @param  array<int, array<string, mixed>>  $internalConflicts
     */
    public function __construct(
        public string $rowKey,
        public ?Movie $movie,
        public ?PresentationFormat $presentationFormat,
        public ?Room $room,
        public ShowtimeScheduleValidationResult $candidate,
        public array $internalConflicts = [],
    ) {}

    public function isValid(): bool
    {
        return $this->candidate->isValid() && $this->internalConflicts === [];
    }

    public function code(): ?string
    {
        return $this->candidate->failureCode()
            ?? ($this->internalConflicts === [] ? null : 'BATCH_ROOM_CONFLICT');
    }

    public function message(): ?string
    {
        if (! $this->candidate->isValid()) {
            return $this->candidate->message();
        }

        return $this->internalConflicts === []
            ? null
            : 'Suất chiếu xung đột với một suất khác trong cùng lô. Hãy đổi phòng hoặc khung giờ.';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $window = $this->candidate->window;
        $failure = $this->candidate->failure;
        $persistedConflict = $failure instanceof ShowtimeConflictException
            ? [
                'source' => 'persisted',
                'movie' => $failure->conflictingShowtime->movie?->title,
                'room' => $failure->conflictingShowtime->room?->name,
                'room_code' => $failure->conflictingShowtime->room?->code,
                'start_display' => $failure->conflictingWindow->start->format('d/m/Y H:i'),
                'end_display' => $failure->conflictingWindow->movieEnd->format('d/m/Y H:i'),
                'room_ready_display' => $failure->conflictingWindow->operationalEnd->format('d/m/Y H:i'),
            ]
            : null;

        return [
            'row_key' => $this->rowKey,
            'valid' => $this->isValid(),
            'code' => $this->code(),
            'message' => $this->message(),
            'movie' => $this->movie ? ['id' => $this->movie->id, 'title' => $this->movie->title] : null,
            'presentation_format' => $this->presentationFormat ? [
                'id' => $this->presentationFormat->id,
                'code' => $this->presentationFormat->code,
                'name' => $this->presentationFormat->name,
            ] : null,
            'room' => $this->room ? [
                'id' => $this->room->id,
                'code' => $this->room->code,
                'name' => $this->room->name,
            ] : null,
            'timezone' => $this->candidate->timezone,
            'window' => $window ? [
                'start_at' => $window->start->toIso8601String(),
                'end_at' => $window->movieEnd->toIso8601String(),
                'room_ready_at' => $window->operationalEnd->toIso8601String(),
                'start_display' => $window->start->format('d/m/Y H:i'),
                'end_display' => $window->movieEnd->format('d/m/Y H:i'),
                'cleaning_display' => $window->movieEnd->format('d/m/Y H:i').' – '.$window->operationalEnd->format('d/m/Y H:i'),
                'room_ready_display' => $window->operationalEnd->format('d/m/Y H:i'),
                'runtime_minutes' => $window->runtimeMinutes,
                'cleaning_buffer_minutes' => $window->cleaningBufferMinutes,
            ] : null,
            'conflict' => $persistedConflict,
            'internal_conflicts' => $this->internalConflicts,
        ];
    }
}
