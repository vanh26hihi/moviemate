<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PricingConfigurationException;
use App\Exceptions\ShowtimeScheduleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreShowtimeRequest;
use App\Http\Requests\Admin\UpdateShowtimeRequest;
use App\Models\Movie;
use App\Models\Room;
use App\Models\Showtime;
use App\Services\ActivityLogger;
use App\Services\CinemaAccessService;
use App\Services\ShowtimeScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShowtimeController extends Controller
{
    public function __construct(
        private readonly CinemaAccessService $cinemaAccess,
        private readonly ShowtimeScheduleService $schedule,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function index(Request $request)
    {
        $query = Showtime::query()->with(['movie', 'cinema', 'room', 'roomLayout']);
        $this->cinemaAccess->scope($query, $request->user(), 'showtimes.cinema_id');

        foreach (['movie_id', 'status'] as $filter) {
            if ($value = $request->query($filter)) {
                $query->where($filter, $value);
            }
        }
        if ($date = $request->query('show_date')) {
            // This is only an administrator display filter. Conflict detection uses complete intervals in the service.
            $query->whereDate('show_date', $date);
        }

        $showtimes = $query->orderByDesc('show_date')->orderBy('show_time')->paginate(15)->withQueryString();
        $scheduleWindows = $showtimes->getCollection()->mapWithKeys(function (Showtime $showtime): array {
            try {
                return [$showtime->id => $this->schedule->windowFor($showtime)];
            } catch (ShowtimeScheduleException) {
                return [$showtime->id => null];
            }
        });

        return view('admin.showtimes.index', [
            'showtimes' => $showtimes,
            'movies' => Movie::query()->orderBy('title')->get(),
            'scheduleWindows' => $scheduleWindows,
            'cleaningBufferMinutes' => $this->schedule->cleaningBufferMinutes(),
            'cinemaTimezone' => $this->schedule->timezone(),
        ]);
    }

    public function create()
    {
        return view('admin.showtimes.create', $this->formData());
    }

    public function store(StoreShowtimeRequest $request)
    {
        $room = Room::query()->findOrFail($request->validated('room_id'));
        $this->cinemaAccess->authorizeCinema($request->user(), (int) $room->cinema_id);
        try {
            DB::transaction(function () use ($request): void {
                $showtime = $this->schedule->schedule($request->validated());
                $this->activityLogger->log(
                    'showtime.created',
                    $showtime,
                    after: $this->auditData($showtime),
                );
            });
        } catch (ShowtimeScheduleException|PricingConfigurationException $exception) {
            $field = $exception instanceof ShowtimeScheduleException ? $exception->field : 'room_id';

            return back()->withErrors([$field => $exception->getMessage()])->withInput();
        }

        return redirect()->route('admin.showtimes.index')->with('success', 'Suất chiếu đã được tạo theo bảng giá hiện hành.');
    }

    public function edit(Showtime $showtime)
    {
        $this->assertOperationalShowtime($showtime);
        $showtime->loadMissing(['movie', 'roomLayout', 'cinema']);

        return view('admin.showtimes.edit', [
            ...$this->formData(),
            'showtime' => $showtime,
            'showtimeWindow' => $this->schedule->windowFor($showtime),
        ]);
    }

    public function update(UpdateShowtimeRequest $request, Showtime $showtime)
    {
        $this->assertOperationalShowtime($showtime);
        $targetRoom = Room::query()->findOrFail($request->validated('room_id'));
        $this->cinemaAccess->authorizeCinema($request->user(), (int) $targetRoom->cinema_id);

        $before = $this->auditData($showtime);
        try {
            DB::transaction(function () use ($request, $showtime, $before): void {
                $updated = $this->schedule->reschedule($showtime, $request->validated());
                $this->activityLogger->log(
                    'showtime.updated',
                    $updated,
                    $before,
                    $this->auditData($updated),
                );
            });
        } catch (ShowtimeScheduleException|PricingConfigurationException $exception) {
            $field = $exception instanceof ShowtimeScheduleException ? $exception->field : 'room_id';

            return back()->withErrors([$field => $exception->getMessage()])->withInput();
        }

        return redirect()->route('admin.showtimes.index')->with('success', 'Suất chiếu đã được cập nhật.');
    }

    public function destroy(Showtime $showtime)
    {
        $this->assertOperationalShowtime($showtime);
        DB::transaction(function () use ($showtime): void {
            $before = $this->auditData($showtime);
            $showtime->delete();
            $this->activityLogger->log('showtime.cancelled', $showtime, $before, ['status' => 'cancelled']);
        });

        return redirect()->route('admin.showtimes.index')->with('success', 'Suất chiếu đã được xóa.');
    }

    private function formData(): array
    {
        return [
            'movies' => Movie::query()->where('status', '!=', 'stopped')->orderBy('title')->get(),
            'rooms' => $this->operationalRooms(),
            'cinema' => $this->cinemaAccess->currentCinema(auth()->user()),
            'cleaningBufferMinutes' => $this->schedule->cleaningBufferMinutes(),
            'cinemaTimezone' => $this->schedule->timezone(),
        ];
    }

    private function operationalRooms()
    {
        $query = Room::query()->operational()->whereHas('latestPublishedLayout')
            ->with(['latestPublishedLayout', 'cinema']);
        $this->cinemaAccess->scope($query, auth()->user(), 'rooms.cinema_id');

        return $query
            ->orderBy('code')->get();
    }

    private function assertOperationalShowtime(Showtime $showtime): void
    {
        $showtime->loadMissing('room');
        $this->cinemaAccess->authorizeCinema(auth()->user(), (int) $showtime->cinema_id);
        abort_unless($showtime->room?->cinema_id === $showtime->cinema_id && $showtime->room?->status === 'active', 404);
    }

    /** @return array<string, mixed> */
    private function auditData(Showtime $showtime): array
    {
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
            'status' => $showtime->status,
        ];
    }
}
