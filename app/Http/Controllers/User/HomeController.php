<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Models\Movie;
use App\Services\CinemaContext;
use App\Services\CustomerShowtimeCatalogService;
use App\Services\PublicShowtimeCatalog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class HomeController extends Controller
{
    public function __construct(
        private readonly CinemaContext $cinemaContext,
        private readonly PublicShowtimeCatalog $catalog,
        private readonly CustomerShowtimeCatalogService $customerCatalog,
    ) {}

    public function index(Request $request)
    {
        $cinemas = Cinema::query()->active()->orderBy('name')->get(['id', 'code', 'name', 'address', 'latitude', 'longitude', 'timezone']);
        $cinema = $request->filled('cinema')
            ? $cinemas->firstWhere('code', mb_strtoupper((string) $request->query('cinema')))
            : null;
        if ($request->filled('cinema') && ! $cinema) {
            abort(404);
        }
        $today = Carbon::today($cinema?->timezone ?: 'Asia/Ho_Chi_Minh');
        try {
            $selectedDate = $this->catalog->date($request->query('date'), $cinema);
        } catch (ValidationException) {
            $selectedDate = $today->toDateString();
        }
        $nowShowing = Movie::query()->where('status', 'now_showing')->orderByDesc('created_at')->get();
        $comingSoon = Movie::query()->where('status', 'coming_soon')->orderBy('release_date')->get();
        $scheduleDates = $this->catalog->dates($cinema)->take(7)->values();
        $scheduleShowtimes = $this->customerCatalog->between(
            $today->toDateString(), $today->copy()->addDays(6)->toDateString(), $cinema,
        );

        return view('user.home', compact(
            'nowShowing', 'comingSoon', 'cinema', 'scheduleDates',
            'selectedDate', 'scheduleShowtimes', 'cinemas'
        ));
    }
}
