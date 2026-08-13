<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Services\ActivityLogger;
use App\Services\Admin\Branch360ReadModel;
use App\Services\CinemaAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class CinemaController extends Controller
{
    public function __construct(
        private readonly CinemaAccessService $access,
        private readonly ActivityLogger $activity,
        private readonly Branch360ReadModel $branch360,
    ) {}

    public function index(Request $request): View
    {
        $query = Cinema::query()->withCount([
            'rooms',
            'rooms as active_rooms_count' => fn ($query) => $query->where('status', 'active'),
            'activeAssignments',
        ])->orderBy('name');
        if (! $this->access->hasGlobalAccess($request->user())) {
            $query->whereIn('id', $this->access->accessibleCinemas($request->user())->pluck('id'));
        }

        return view('admin.cinemas.index', ['cinemas' => $query->paginate(20)]);
    }

    public function create(): View
    {
        return view('admin.cinemas.form', ['cinema' => new Cinema]);
    }

    public function store(Request $request): RedirectResponse
    {
        $cinema = DB::transaction(function () use ($request): Cinema {
            $cinema = Cinema::query()->create($this->validated($request));
            $this->activity->log('cinema.created', $cinema, after: $this->auditData($cinema));

            return $cinema;
        });

        return redirect()->route('admin.cinemas.show', $cinema)->with('success', 'Đã tạo chi nhánh.');
    }

    public function show(Request $request, Cinema $cinema): View
    {
        $branch360 = $this->branch360->snapshot($cinema, $request->user());

        $cinema->load([
            'rooms' => fn ($query) => $query->withCount('showtimes')->orderBy('code'),
            'activeAssignments.user.role',
            'operatingHours',
        ])->loadCount([
            'rooms',
            'rooms as active_rooms_count' => fn ($query) => $query->where('status', 'active'),
            'showtimes as active_showtimes_count' => fn ($query) => $query->where('status', 'active')
                ->whereDate('show_date', '>=', now()->toDateString()),
            'bookings',
        ]);

        return view('admin.cinemas.show', compact('cinema', 'branch360'));
    }

    public function edit(Cinema $cinema): View
    {
        return view('admin.cinemas.form', compact('cinema'));
    }

    public function update(Request $request, Cinema $cinema): RedirectResponse
    {
        $before = $this->auditData($cinema);
        DB::transaction(function () use ($request, $cinema, $before): void {
            $cinema->update($this->validated($request, $cinema));
            $action = $before['status'] !== $cinema->status
                ? ($cinema->status === 'active' ? 'cinema.activated' : 'cinema.deactivated')
                : 'cinema.updated';
            $this->activity->log($action, $cinema, $before, $this->auditData($cinema));
        });

        return redirect()->route('admin.cinemas.show', $cinema)->with('success', 'Đã cập nhật chi nhánh.');
    }

    public function legacyShow(Request $request): View
    {
        $cinema = $this->access->currentCinema($request->user());

        return $cinema ? $this->show($request, $cinema) : $this->index($request);
    }

    public function legacyUpdate(Request $request): RedirectResponse
    {
        $cinema = $this->access->currentCinema($request->user());
        abort_unless($cinema, 403, 'Hãy chọn một chi nhánh trước khi cập nhật.');
        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'max:30'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
        $cinema->update($validated);

        return redirect()->route('admin.cinemas.show', $cinema)->with('success', 'Đã cập nhật thông tin chi nhánh.');
    }

    private function validated(Request $request, ?Cinema $cinema = null): array
    {
        $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9-]+$/', Rule::unique('cinemas', 'code')->ignore($cinema?->id)],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'timezone' => ['required', 'timezone:all'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function auditData(Cinema $cinema): array
    {
        return $cinema->only(['id', 'code', 'name', 'address', 'city', 'district', 'phone', 'latitude', 'longitude', 'timezone', 'status']);
    }
}
