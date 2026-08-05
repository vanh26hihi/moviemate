<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ShowtimeScheduleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreShowtimeRequest;
use App\Http\Requests\Admin\UpdateShowtimeRequest;
use App\Models\Movie;
use App\Models\Room;
use App\Models\Showtime;
use App\Services\CinemaContext;
use App\Services\ShowtimeScheduleService;
use Illuminate\Http\Request;

class ShowtimeController extends Controller
{
    public function __construct(
        private readonly CinemaContext $cinemaContext,
        private readonly ShowtimeScheduleService $schedule,
    ) {}

    public function index(Request $request)
    {
        $query = Showtime::query()->with(['movie', 'cinema', 'room', 'roomLayout'])
            ->where('cinema_id', $this->cinemaContext->id());

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
        try {
            $this->schedule->schedule($request->validated());
        } catch (ShowtimeScheduleException $exception) {
            return back()->withErrors([$exception->field => $exception->getMessage()])->withInput();
        }

        return redirect()->route('admin.showtimes.index')->with('success', 'Suất chiếu đã được tạo thành công.');
    }

    public function edit(Showtime $showtime)
    {
        $this->assertOperationalShowtime($showtime);
        $showtime->loadMissing(['movie', 'roomLayout']);

        return view('admin.showtimes.edit', [
            ...$this->formData(),
            'showtime' => $showtime,
            'showtimeWindow' => $this->schedule->windowFor($showtime),
        ]);
    }

    public function update(UpdateShowtimeRequest $request, Showtime $showtime)
    {
        $this->assertOperationalShowtime($showtime);

        try {
            $this->schedule->reschedule($showtime, $request->validated());
        } catch (ShowtimeScheduleException $exception) {
            return back()->withErrors([$exception->field => $exception->getMessage()])->withInput();
        }

        return redirect()->route('admin.showtimes.index')->with('success', 'Suất chiếu đã được cập nhật.');
    }

    public function destroy(Showtime $showtime)
    {
        $this->assertOperationalShowtime($showtime);
        $showtime->delete();

        return redirect()->route('admin.showtimes.index')->with('success', 'Suất chiếu đã được xóa.');
    }

    private function formData(): array
    {
        return [
            'movies' => Movie::query()->where('status', '!=', 'stopped')->orderBy('title')->get(),
            'rooms' => $this->operationalRooms(),
            'cinema' => $this->cinemaContext->current(),
            'cleaningBufferMinutes' => $this->schedule->cleaningBufferMinutes(),
            'cinemaTimezone' => $this->schedule->timezone(),
        ];
    }

    private function operationalRooms()
    {
        return Room::query()->where('cinema_id', $this->cinemaContext->id())
            ->operational()->whereHas('latestPublishedLayout')->with('latestPublishedLayout')
            ->orderBy('code')->get();
    }

    private function assertOperationalShowtime(Showtime $showtime): void
    {
        $showtime->loadMissing('room');
        abort_unless(
            $showtime->cinema_id === $this->cinemaContext->id()
            && $showtime->room?->cinema_id === $this->cinemaContext->id()
            && $showtime->room?->status === 'active',
            404
        );
    }
}
