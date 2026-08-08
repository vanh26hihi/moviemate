<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexTicketCheckinRequest;
use App\Models\TicketCheckinEvent;
use App\Services\Admin\AdminTicketCheckinDetailService;
use App\Services\Admin\AdminTicketCheckinQuery;
use Illuminate\View\View;

final class TicketCheckinController extends Controller
{
    public function index(IndexTicketCheckinRequest $request, AdminTicketCheckinQuery $events): View
    {
        $filters = $request->validated();

        return view('admin.ticket-checkins.index', [
            'events' => $events->paginate($filters),
            'filters' => $filters,
        ]);
    }

    public function show(
        TicketCheckinEvent $ticketCheckinEvent,
        AdminTicketCheckinDetailService $details,
    ): View {
        return view('admin.ticket-checkins.show', $details->get(
            $ticketCheckinEvent,
            request()->user()?->can('activity_logs.view') === true,
        ));
    }
}
