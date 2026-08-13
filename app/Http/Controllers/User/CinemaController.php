<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Services\CinemaContext;
use App\Services\CustomerShowtimeCatalogService;
use App\Services\PublicShowtimeCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class CinemaController extends Controller
{
    public function __construct(
        private readonly PublicShowtimeCatalog $catalog,
        private readonly CustomerShowtimeCatalogService $customerCatalog,
        private readonly CinemaContext $context,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'open' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['name'])],
        ]);
        $query = Cinema::query()->active()->with('operatingHours')->orderBy('name');
        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%"));
        }
        foreach (['city', 'district'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        $cinemas = $query->limit(50)->get();
        $today = CarbonImmutable::today(config('cinema.timezone', 'Asia/Ho_Chi_Minh'));
        $showtimes = $this->catalog->betweenForCinemas(
            $cinemas->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            $today->toDateString(),
            $today->addDays(PublicShowtimeCatalog::WINDOW_DAYS - 1)->toDateString(),
        )->groupBy('cinema_id');

        $cinemas->each(function (Cinema $cinema) use ($showtimes): void {
            $branchShowtimes = $showtimes->get($cinema->id, collect());
            $cinema->setAttribute('available_movie_count', $branchShowtimes->pluck('movie_id')->unique()->count());
            $cinema->setAttribute('upcoming_showtime_count', $branchShowtimes->count());
            $cinema->setAttribute('is_accepting_showtimes', $this->acceptingNow($cinema));
            $cinema->setAttribute('today_hours', $this->todayHours($cinema));
        });
        if (! empty($filters['open'])) {
            $cinemas = $cinemas->filter(fn (Cinema $cinema): bool => $cinema->is_accepting_showtimes === true)->values();
        }

        return view('user.cinemas.index', [
            'cinemas' => $cinemas,
            'cities' => Cinema::query()->active()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
            'districts' => Cinema::query()->active()->whereNotNull('district')->distinct()->orderBy('district')->pluck('district'),
            'preferredCinema' => $this->context->preference(),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, Cinema $cinema): View
    {
        abort_unless($cinema->status === 'active' && $cinema->archived_at === null, 404);
        $cinema->load('operatingHours');
        $date = $this->catalog->date($request->query('date'), $cinema);
        $showtimes = $this->customerCatalog->forDate($date, $cinema);

        return view('user.cinemas.show', [
            'cinema' => $cinema,
            'selectedDate' => $date,
            'dates' => $this->catalog->dates($cinema),
            'showtimes' => $showtimes,
            'showtimesByMovie' => $showtimes->groupBy('movie_id'),
            'preferredCinema' => $this->context->preference(),
        ]);
    }

    private function acceptingNow(Cinema $cinema): ?bool
    {
        $now = CarbonImmutable::now($cinema->timezone);
        $hours = $cinema->operatingHours->firstWhere('day_of_week', $now->dayOfWeekIso);
        if (! $hours) {
            return null;
        }
        if ($hours->is_closed) {
            return false;
        }
        $time = $now->format('H:i:s');

        return $time >= substr((string) $hours->opens_at, 0, 8)
            && $time <= substr((string) $hours->latest_show_start_at, 0, 8);
    }

    private function todayHours(Cinema $cinema): string
    {
        $now = CarbonImmutable::now($cinema->timezone);
        $hours = $cinema->operatingHours->firstWhere('day_of_week', $now->dayOfWeekIso);
        if (! $hours || $hours->is_closed) {
            return 'Đóng cửa hôm nay';
        }

        return 'Mở từ '.substr((string) $hours->opens_at, 0, 5).' · nhận suất đến '.substr((string) $hours->latest_show_start_at, 0, 5);
    }
}
