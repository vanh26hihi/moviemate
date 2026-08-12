<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BulkShowtimeValidationException;
use App\Exceptions\PricingConfigurationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkShowtimeRequest;
use App\Models\Movie;
use App\Models\Room;
use App\Models\Showtime;
use App\Services\ActivityLogger;
use App\Services\BulkShowtimeScheduleService;
use App\Services\CinemaAccessService;
use App\Services\ShowtimeScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class BulkShowtimeController extends Controller
{
    public function __construct(
        private readonly BulkShowtimeScheduleService $bulkSchedule,
        private readonly ShowtimeScheduleService $schedule,
        private readonly CinemaAccessService $cinemaAccess,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function index(Request $request): View
    {
        $roomQuery = Room::query()
            ->operational()
            ->whereHas('latestPublishedLayout')
            ->with(['cinema', 'latestPublishedLayout']);
        $this->cinemaAccess->scope($roomQuery, $request->user(), 'rooms.cinema_id');

        return view('admin.showtimes.bulk', [
            'movies' => Movie::query()->whereIn('status', Movie::SCHEDULABLE_STATUSES)->orderBy('title')->get(),
            'rooms' => $roomQuery->orderBy('cinema_id')->orderBy('code')->get(),
            'cinema' => $this->cinemaAccess->currentCinema($request->user()),
        ]);
    }

    public function preview(BulkShowtimeRequest $request): JsonResponse
    {
        return response()->json($this->bulkSchedule->preview($request->rows(), $request->user())->toArray());
    }

    public function store(BulkShowtimeRequest $request): JsonResponse
    {
        try {
            $created = $this->bulkSchedule->publish(
                $request->rows(),
                $request->user(),
                function (Showtime $showtime): void {
                    $this->activityLogger->log(
                        'showtime.created',
                        $showtime,
                        after: $this->auditData($showtime),
                        context: ['source' => 'bulk', 'showtime_id' => $showtime->id],
                    );
                },
            );
        } catch (BulkShowtimeValidationException $exception) {
            return response()->json([
                ...$exception->result->toArray(),
                'message' => $exception->getMessage(),
            ], 422);
        } catch (PricingConfigurationException $exception) {
            return response()->json([
                'valid' => false,
                'message' => $exception->getMessage().' Không có suất chiếu nào được tạo.',
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'created_count' => count($created),
            'message' => 'Đã đăng toàn bộ '.count($created).' suất chiếu.',
            'redirect' => route('admin.showtimes.index'),
        ], 201);
    }

    /** @return array<string, mixed> */
    private function auditData(Showtime $showtime): array
    {
        $showtime->loadMissing(['movie', 'room.cinema']);
        $window = $this->schedule->windowFor($showtime);

        return [
            'showtime_id' => $showtime->id,
            'movie_id' => $showtime->movie_id,
            'room_id' => $showtime->room_id,
            'room_layout_id' => $showtime->room_layout_id,
            'show_date' => $showtime->show_date?->format('Y-m-d'),
            'show_time' => (string) $showtime->show_time,
            'movie_end_at' => $window->movieEnd->toIso8601String(),
            'room_available_at' => $window->operationalEnd->toIso8601String(),
            'cleaning_buffer' => $window->cleaningBufferMinutes,
            'price' => (int) $showtime->price,
            'vip_price' => $showtime->vip_price === null ? null : (int) $showtime->vip_price,
            'pricing_version' => $showtime->pricing_version,
            'status' => $showtime->status,
        ];
    }
}
