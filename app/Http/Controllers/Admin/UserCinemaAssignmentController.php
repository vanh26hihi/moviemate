<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Models\User;
use App\Models\UserCinemaAssignment;
use App\Services\ActivityLogger;
use App\Services\CinemaAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UserCinemaAssignmentController extends Controller
{
    public function __construct(
        private readonly CinemaAccessService $access,
        private readonly ActivityLogger $activity,
    ) {}

    public function store(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate(['cinema_id' => ['required', 'integer', 'exists:cinemas,id']]);
        $cinema = Cinema::query()->active()->findOrFail($validated['cinema_id']);
        $this->authorizeTarget($request, $user, $cinema);

        DB::transaction(function () use ($request, $user, $cinema): void {
            $assignment = UserCinemaAssignment::query()->updateOrCreate(
                ['user_id' => $user->id, 'cinema_id' => $cinema->id],
                [
                    'assigned_by_user_id' => $request->user()->id,
                    'status' => UserCinemaAssignment::STATUS_ACTIVE,
                    'assigned_at' => now(),
                ],
            );
            $this->activity->log('cinema_assignment.created', $assignment, after: [
                'user_id' => $user->id,
                'cinema_id' => $cinema->id,
                'status' => $assignment->status,
            ]);
        });

        return back()->with('success', 'Đã phân công '.$user->name.' vào '.$cinema->name.'.');
    }

    public function destroy(Request $request, User $user, UserCinemaAssignment $assignment): RedirectResponse
    {
        abort_unless((int) $assignment->user_id === (int) $user->id, 404);
        $cinema = $assignment->cinema()->firstOrFail();
        $this->authorizeTarget($request, $user, $cinema);

        DB::transaction(function () use ($assignment): void {
            $before = ['status' => $assignment->status, 'cinema_id' => $assignment->cinema_id, 'user_id' => $assignment->user_id];
            $assignment->update(['status' => UserCinemaAssignment::STATUS_REVOKED]);
            $this->activity->log('cinema_assignment.revoked', $assignment, $before, ['status' => $assignment->status]);
        });

        return back()->with('success', 'Đã thu hồi phân công chi nhánh.');
    }

    private function authorizeTarget(Request $request, User $target, Cinema $cinema): void
    {
        $actor = $request->user();
        $this->access->authorizeCinema($actor, (int) $cinema->id);
        if (! $target->hasRole(['manager', 'staff'])) {
            throw ValidationException::withMessages(['user' => 'Chỉ Manager hoặc Staff được phân công chi nhánh.']);
        }
        if (! $this->access->hasGlobalAccess($actor) && ! $target->hasRole('staff')) {
            abort(403, 'Manager chỉ được quản lý phân công Staff.');
        }
    }
}
