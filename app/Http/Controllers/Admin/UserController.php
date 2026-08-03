<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'exists:roles,slug'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $users = User::query()->with('role')
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
            'managedUser' => $user->load('role'),
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manageRole', User::class);
        $validated = $request->validate(['role' => ['required', 'string', 'exists:roles,slug']]);
        $newRole = Role::query()->where('slug', $validated['role'])->firstOrFail();

        DB::transaction(function () use ($user, $newRole): void {
            Role::query()->where('slug', 'admin')->lockForUpdate()->firstOrFail();
            $target = User::query()->lockForUpdate()->findOrFail($user->id);
            $target->load('role');
            if ($target->isActive() && $target->hasRole('admin') && $newRole->slug !== 'admin') {
                $this->ensureAnotherActiveAdminExists($target);
            }
            $target->role()->associate($newRole);
            $target->save();
        });

        return back()->with('success', 'Đã cập nhật vai trò người dùng.');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manageStatus', User::class);
        $validated = $request->validate(['status' => ['required', 'in:active,inactive']]);

        DB::transaction(function () use ($user, $validated): void {
            Role::query()->where('slug', 'admin')->lockForUpdate()->firstOrFail();
            $target = User::query()->lockForUpdate()->findOrFail($user->id);
            $target->load('role');
            if ($target->isActive() && $target->hasRole('admin') && $validated['status'] === 'inactive') {
                $this->ensureAnotherActiveAdminExists($target);
            }
            $target->status = $validated['status'];
            $target->save();
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
