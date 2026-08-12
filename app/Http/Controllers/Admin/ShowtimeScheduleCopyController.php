<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CopyShowtimeScheduleRequest;
use App\Models\Room;
use App\Services\CinemaAccessService;
use App\Services\ShowtimeScheduleCopyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ShowtimeScheduleCopyController extends Controller
{
    public function __construct(
        private readonly ShowtimeScheduleCopyService $copy,
        private readonly CinemaAccessService $cinemaAccess,
    ) {}

    public function index(Request $request): View
    {
        $roomQuery = Room::query()->with('cinema');
        $this->cinemaAccess->scope($roomQuery, $request->user(), 'rooms.cinema_id');

        return view('admin.showtimes.copy', [
            'cinemas' => $this->cinemaAccess->accessibleCinemas($request->user()),
            'cinema' => $this->cinemaAccess->currentCinema($request->user()),
            'rooms' => $roomQuery->orderBy('cinema_id')->orderBy('code')->get(),
        ]);
    }

    public function generate(CopyShowtimeScheduleRequest $request): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $result = $this->copy->generate(
            $request->user(),
            $data['scope'],
            (int) $data['cinema_id'],
            isset($data['room_id']) ? (int) $data['room_id'] : null,
            $data['source_date'],
            $data['target_date'],
        );

        if ($request->expectsJson()) {
            return response()->json($result->toArray());
        }

        return redirect()->route('admin.showtimes.bulk.index')->with([
            'bulk_showtime_rows' => $result->rows,
            'bulk_showtime_copy_message' => $result->toArray()['message'],
        ]);
    }
}
