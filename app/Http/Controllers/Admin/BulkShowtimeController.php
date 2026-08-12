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
        $initialRows = collect($request->session()->get('bulk_showtime_rows', []))
            ->filter(fn ($row): bool => is_array($row))
            ->values();
        $initialMovieIds = $initialRows->pluck('movie_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values();
        $initialRoomIds = $initialRows->pluck('room_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values();

        $roomQuery = Room::query()
            ->with(['cinema', 'latestPublishedLayout']);
        $this->cinemaAccess->scope($roomQuery, $request->user(), 'rooms.cinema_id');
        $roomQuery->where(function ($query) use ($initialRoomIds): void {
            $query->where(function ($available): void {
                $available->operational()->whereHas('latestPublishedLayout');
            });
            if ($initialRoomIds->isNotEmpty()) {
                $query->orWhereIn('rooms.id', $initialRoomIds);
            }
        });

        $movieQuery = Movie::query()->where(function ($query) use ($initialMovieIds): void {
            $query->whereIn('status', Movie::SCHEDULABLE_STATUSES);
            if ($initialMovieIds->isNotEmpty()) {
                $query->orWhereIn('movies.id', $initialMovieIds);
            }
        });

        return view('admin.showtimes.bulk', [
            'movies' => $movieQuery->orderBy('title')->get(),
            'rooms' => $roomQuery->orderBy('cinema_id')->orderBy('code')->get(),
            'cinema' => $this->cinemaAccess->currentCinema($request->user()),
            'initialRows' => $initialRows->all(),
            'copyMessage' => $request->session()->get('bulk_showtime_copy_message'),
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
