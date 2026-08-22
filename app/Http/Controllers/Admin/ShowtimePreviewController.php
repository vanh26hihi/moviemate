<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Showtimes\ShowtimeScheduleValidationResult;
use App\Exceptions\ShowtimeConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PreviewShowtimeRequest;
use App\Models\Movie;
use App\Models\Room;
use App\Models\Showtime;
use App\Services\CinemaAccessService;
use App\Services\ShowtimeLifecycleService;
use App\Services\ShowtimeScheduleService;
use Illuminate\Http\JsonResponse;

final class ShowtimePreviewController extends Controller
{
    public function __construct(
        private readonly CinemaAccessService $cinemaAccess,
        private readonly ShowtimeScheduleService $schedule,
        private readonly ShowtimeLifecycleService $lifecycle,
    ) {}

    public function __invoke(PreviewShowtimeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $room = Room::query()->with('cinema.operatingHours')->findOrFail($data['room_id']);
        $this->cinemaAccess->authorizeCinema($request->user(), (int) $room->cinema_id);

        $showtime = $this->previewedShowtime($request, $data['showtime_id'] ?? null);
        if ($showtime && $this->schedule->hasBookingHistory($showtime)) {
            return response()->json($this->sourceFailure(
                'SHOWTIME_HAS_BOOKING_HISTORY',
                'Suất chiếu đã phát sinh đơn đặt vé nên không thể thay đổi phim, phòng, ngày hoặc giờ chiếu.',
            ));
        }
        if ($showtime && ($showtime->status !== 'active'
            || $this->lifecycle->state($showtime) !== ShowtimeLifecycleService::UPCOMING)) {
            return response()->json($this->sourceFailure(
                'SHOWTIME_NOT_MUTABLE',
                'Chỉ suất chiếu đang hoạt động và sắp diễn ra mới có thể chỉnh sửa.',
            ));
        }

        $movie = Movie::query()->findOrFail($data['movie_id']);
        $result = $this->schedule->validateCandidate(
            $movie,
            $room,
            $data['show_date'],
            $data['show_time'],
            $showtime,
            presentationFormatId: (int) $data['presentation_format_id'],
        );

        return response()->json($this->payload($result, $room));
    }

    private function previewedShowtime(PreviewShowtimeRequest $request, mixed $showtimeId): ?Showtime
    {
        if ($showtimeId === null) {
            return null;
        }

        $showtime = Showtime::query()->with(['movie', 'room.cinema'])->findOrFail((int) $showtimeId);
        $this->cinemaAccess->authorizeCinema($request->user(), (int) $showtime->cinema_id);

        return $showtime;
    }

    /** @return array<string, mixed> */
    private function payload(ShowtimeScheduleValidationResult $result, Room $room): array
    {
        $window = $result->window;
        $failure = $result->failure;
        $conflict = $failure instanceof ShowtimeConflictException
            ? [
                'movie' => $failure->conflictingShowtime->movie?->title,
                'room' => $failure->conflictingShowtime->room?->name,
                'room_code' => $failure->conflictingShowtime->room?->code,
                'start_at' => $failure->conflictingWindow->start->toIso8601String(),
                'end_at' => $failure->conflictingWindow->movieEnd->toIso8601String(),
                'room_ready_at' => $failure->conflictingWindow->operationalEnd->toIso8601String(),
                'start_display' => $failure->conflictingWindow->start->format('d/m/Y H:i'),
                'end_display' => $failure->conflictingWindow->movieEnd->format('d/m/Y H:i'),
                'room_ready_display' => $failure->conflictingWindow->operationalEnd->format('d/m/Y H:i'),
            ]
            : null;

        $hours = $window
            ? $room->cinema?->operatingHours->firstWhere('day_of_week', $window->start->dayOfWeekIso)
            : null;
        $prices = $result->isValid()
            ? $result->ticketPriceSnapshots
                ->map(fn ($snapshot): array => [
                    'seat_type_id' => (int) $snapshot->seat_type_id,
                    'seat_type' => (string) $snapshot->seatType?->code,
                    'base_price_vnd' => (int) $snapshot->base_price_vnd,
                    'adjustment_total_vnd' => (int) $snapshot->adjustment_total_vnd,
                    'final_unit_amount_vnd' => (int) $snapshot->final_unit_amount_vnd,
                    'price_book_version_id' => (int) $snapshot->price_book_version_id,
                ])->values()->all()
            : [];

        return [
            'valid' => $result->isValid(),
            'code' => $result->failureCode(),
            'message' => $result->message(),
            'timezone' => $result->timezone,
            'presentation_format' => $result->presentationFormat ? [
                'id' => $result->presentationFormat->id,
                'code' => $result->presentationFormat->code,
                'name' => $result->presentationFormat->name,
            ] : null,
            'window' => $window ? [
                'start_at' => $window->start->toIso8601String(),
                'end_at' => $window->movieEnd->toIso8601String(),
                'cleaning_start_at' => $window->movieEnd->toIso8601String(),
                'room_ready_at' => $window->operationalEnd->toIso8601String(),
                'start_display' => $window->start->format('d/m/Y H:i'),
                'end_display' => $window->movieEnd->format('d/m/Y H:i'),
                'cleaning_display' => $window->movieEnd->format('d/m/Y H:i').' – '.$window->operationalEnd->format('d/m/Y H:i'),
                'room_ready_display' => $window->operationalEnd->format('d/m/Y H:i'),
                'runtime_minutes' => $window->runtimeMinutes,
                'cleaning_buffer_minutes' => $window->cleaningBufferMinutes,
            ] : null,
            'operating_window' => $hours ? [
                'is_closed' => (bool) $hours->is_closed,
                'opens_at' => $hours->opens_at,
                'latest_show_start_at' => $hours->latest_show_start_at,
            ] : null,
            'conflict' => $conflict,
            'ticket_prices' => $prices,
        ];
    }

    /** @return array<string, mixed> */
    private function sourceFailure(string $code, string $message): array
    {
        return [
            'valid' => false,
            'code' => $code,
            'message' => $message,
            'timezone' => null,
            'presentation_format' => null,
            'window' => null,
            'operating_window' => null,
            'conflict' => null,
            'ticket_prices' => [],
        ];
    }
}
