<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShowtimeBoardRequest;
use App\Services\Admin\ShowtimeOperationsBoard;
use App\Services\CinemaAccessService;
use Illuminate\View\View;

final class ShowtimeBoardController extends Controller
{
    public function __invoke(
        ShowtimeBoardRequest $request,
        ShowtimeOperationsBoard $board,
        CinemaAccessService $cinemaAccess,
    ): View {
        $filters = $request->validated();
        $currentCinema = $cinemaAccess->currentCinema($request->user());
        $timezone = $currentCinema?->timezone ?: (string) config('cinema.timezone', 'Asia/Ho_Chi_Minh');
        $period = $request->period($timezone);
        $data = $board->build($request->user(), $filters, $period['from'], $period['to']);

        return view('admin.showtimes.board', [
            ...$data,
            'filters' => $filters,
            'currentCinema' => $currentCinema,
            'timezone' => $timezone,
            'previousPeriod' => [
                'from' => $period['from']->subDays($period['from']->diffInDays($period['to']) + 1)->toDateString(),
                'to' => $period['from']->subDay()->toDateString(),
            ],
            'nextPeriod' => [
                'from' => $period['to']->addDay()->toDateString(),
                'to' => $period['to']->addDays($period['from']->diffInDays($period['to']) + 1)->toDateString(),
            ],
        ]);
    }
}
