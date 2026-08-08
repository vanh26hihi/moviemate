<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $dateToRules = ['nullable', 'date_format:Y-m-d'];
        if ($request->filled('date_from')) {
            $dateToRules[] = 'after_or_equal:date_from';
        }

        $filters = $request->validate([
            'actor' => ['nullable', 'integer', 'min:1'],
            'action' => ['nullable', 'string', 'max:100'],
            'subject_type' => ['nullable', 'string', 'max:191'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => $dateToRules,
            'route' => ['nullable', 'string', 'max:191'],
            'request_id' => ['nullable', 'string', 'max:100'],
        ]);

        $logs = ActivityLog::query()
            ->when($filters['actor'] ?? null, fn (Builder $query, string|int $actor): Builder => $query->where('actor_user_id', (int) $actor))
            ->when($filters['action'] ?? null, fn (Builder $query, string $action): Builder => $query->where('action', $action))
            ->when($filters['subject_type'] ?? null, fn (Builder $query, string $type): Builder => $query->where('subject_type', $type))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))
            ->when($filters['route'] ?? null, fn (Builder $query, string $route): Builder => $query->where('route_name', $route))
            ->when($filters['request_id'] ?? null, fn (Builder $query, string $requestId): Builder => $query->where('request_id', $requestId))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.activity-logs.index', [
            'logs' => $logs,
            'filters' => $filters,
            'actions' => ActivityLog::query()->whereNotNull('action')->distinct()->orderBy('action')->pluck('action'),
            'subjectTypes' => ActivityLog::query()->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type'),
            'routeNames' => ActivityLog::query()->whereNotNull('route_name')->distinct()->orderBy('route_name')->pluck('route_name'),
        ]);
    }

    public function show(ActivityLog $activityLog): View
    {
        return view('admin.activity-logs.show', compact('activityLog'));
    }
}
