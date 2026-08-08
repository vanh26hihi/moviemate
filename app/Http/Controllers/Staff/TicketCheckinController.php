<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\CheckinTicketRequest;
use App\Services\Tickets\TicketCheckinCapability;
use App\Services\Tickets\TicketCheckinService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

final class TicketCheckinController extends Controller
{
    public function show(): View
    {
        return view('staff.tickets.check');
    }

    public function store(
        CheckinTicketRequest $request,
        TicketCheckinCapability $capabilities,
        TicketCheckinService $checkins,
    ): RedirectResponse {
        $capability = (string) $request->validated('ticket');
        $bookingId = $capabilities->bookingId($capability);
        $key = implode(':', ['staff-ticket-checkin', $request->user()->id, $bookingId ?? 'invalid']);
        abort_if(RateLimiter::tooManyAttempts($key, 12), 429);
        RateLimiter::hit($key, 60);

        $result = $checkins->checkIn($capability, $request->user());

        return redirect()->route('staff.tickets.check')->with('checkin_result', [
            'result' => $result->result,
            'message' => $result->message,
            'booking_code' => $result->booking?->booking_code,
            'used_at' => $result->booking?->used_at?->format('d/m/Y H:i:s'),
        ]);
    }
}
