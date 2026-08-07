<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\CheckinTicketRequest;
use App\Models\Booking;
use App\Services\Tickets\TicketCheckinCapability;
use App\Services\Tickets\TicketCheckinService;
use App\Services\Tickets\TicketQrPayload;
use App\Services\Tickets\TicketResolutionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        TicketQrPayload $payloads,
        TicketCheckinService $checkins,
    ): RedirectResponse {
        $capability = $payloads->capabilityFrom((string) $request->validated('ticket'));
        $bookingId = $capabilities->bookingId($capability);
        $key = implode(':', ['staff-ticket-checkin', $request->user()->id, $bookingId ?? 'invalid']);
        abort_if(RateLimiter::tooManyAttempts($key, 12), 429);
        RateLimiter::hit($key, 60);

        $result = $checkins->checkIn((string) $capability, $request->user());

        return redirect()->route('staff.tickets.check')->with('checkin_result', [
            'result' => $result->result,
            'message' => $result->message,
            'booking_code' => $result->booking?->booking_code,
            'used_at' => $result->booking?->used_at?->format('d/m/Y H:i:s'),
        ]);
    }

    public function storeBooking(
        Request $request,
        Booking $booking,
        TicketResolutionService $tickets,
        TicketCheckinCapability $capabilities,
        TicketCheckinService $checkins,
    ): RedirectResponse {
        $booking = $tickets->authorizedBooking($booking, $request->user());
        $key = implode(':', ['staff-ticket-checkin-booking', $request->user()->id, $booking->id]);
        abort_if(RateLimiter::tooManyAttempts($key, 12), 429);
        RateLimiter::hit($key, 60);
        $result = $checkins->checkIn($capabilities->issue($booking), $request->user());

        return redirect()->route('staff.tickets.operations', $booking)->with('checkin_result', [
            'result' => $result->result,
            'message' => $result->message,
            'booking_code' => $result->booking?->booking_code,
            'used_at' => $result->booking?->used_at?->format('d/m/Y H:i:s'),
        ]);
    }
}
