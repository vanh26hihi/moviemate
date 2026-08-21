<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CinemaAccessService;
use App\Services\Admin\UserDetailReadModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly CinemaAccessService $cinemaAccess) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'exists:roles,slug'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $query = User::query()->with(['role', 'activeCinemaAssignments.cinema']);
        if (! $this->cinemaAccess->hasGlobalAccess($request->user())) {
            $cinemaId = $this->cinemaAccess->currentCinemaId($request->user());
            $query->where(function ($query) use ($request, $cinemaId): void {
                $query->whereKey($request->user()->id)
                    ->orWhere(function ($query) use ($cinemaId): void {
                        $query->whereHas('role', fn ($role) => $role->where('slug', 'staff'))
                            ->when($cinemaId, fn ($query) => $query->whereHas('activeCinemaAssignments', fn ($assignments) => $assignments->where('cinema_id', $cinemaId)), fn ($query) => $query->whereRaw('1 = 0'));
                    });
            });
        }
        $users = $query
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->whereHas('role', fn ($query) => $query->where('slug', $role)))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest('id')->paginate(20)->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function edit(User $user): View
    {
        Gate::authorize('view', $user);

        return view('admin.users.edit', [
            'managedUser' => $user->load(['role', 'cinemaAssignments.cinema']),
            'roles' => Role::query()->orderBy('name')->get(),
            'assignableCinemas' => $this->cinemaAccess->accessibleCinemas(auth()->user()),
        ]);
    }

    public function show(Request $request, User $user, UserDetailReadModel $readModel): View
    {
        Gate::authorize('view', $user);

        $dateToRules = ['nullable', 'date_format:Y-m-d'];
        if ($request->filled('date_from')) {
            $dateToRules[] = 'after_or_equal:date_from';
        }

        $filters = $request->validate([
            'booking_search' => ['nullable', 'string', 'max:100'],
            'booking_status' => ['nullable', 'in:'.implode(',', \App\Models\Booking::STATUSES)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => $dateToRules,
        ]);

        return view('admin.users.show', $readModel->build($request->user(), $user, $filters));
    }

    public function updateRole(Request $request, User $user, ActivityLogger $activityLogger): RedirectResponse
    {
        Gate::authorize('manageRole', User::class);
        $validated = $request->validate(['role' => ['required', 'string', 'exists:roles,slug']]);
        $newRole = Role::query()->where('slug', $validated['role'])->firstOrFail();

        DB::transaction(function () use ($user, $newRole, $activityLogger): void {
            Role::query()->where('slug', 'admin')->lockForUpdate()->firstOrFail();
            $target = User::query()->lockForUpdate()->findOrFail($user->id);
            $target->load('role');
            $beforeRole = $target->role?->slug;
            if ($target->isActive() && $target->hasRole('admin') && $newRole->slug !== 'admin') {
                $this->ensureAnotherActiveAdminExists($target);
            }
            $target->role()->associate($newRole);
            $target->save();
            $activityLogger->log(
                'user.role_updated',
                $target,
                ['role_slug' => $beforeRole],
                ['role_slug' => $newRole->slug],
            );
        });

        return back()->with('success', 'Đã cập nhật vai trò người dùng.');
    }

    public function updateStatus(Request $request, User $user, ActivityLogger $activityLogger): RedirectResponse
    {
        Gate::authorize('manageStatus', User::class);
        $validated = $request->validate(['status' => ['required', 'in:active,inactive']]);

        DB::transaction(function () use ($user, $validated, $activityLogger): void {
            Role::query()->where('slug', 'admin')->lockForUpdate()->firstOrFail();
            $target = User::query()->lockForUpdate()->findOrFail($user->id);
            $target->load('role');
            $beforeStatus = $target->status;
            if ($target->isActive() && $target->hasRole('admin') && $validated['status'] === 'inactive') {
                $this->ensureAnotherActiveAdminExists($target);
            }
            $target->status = $validated['status'];
            $target->save();
            if ($beforeStatus !== $target->status) {
                $activityLogger->log(
                    'user.status_updated',
                    $target,
                    ['status' => $beforeStatus],
                    ['status' => $target->status],
                );
            }
        });

        return back()->with('success', 'Đã cập nhật trạng thái người dùng.');
    }

    private function ensureAnotherActiveAdminExists(User $target): void
    {
        $anotherAdminExists = User::query()->whereKeyNot($target->id)
            ->where('status', 'active')
            ->whereHas('role', fn ($query) => $query->where('slug', 'admin'))
            ->exists();

        if (! $anotherAdminExists) {
            throw ValidationException::withMessages([
                'admin' => 'Không thể thay đổi Admin đang hoạt động cuối cùng.',
            ]);
        }
    }
}
