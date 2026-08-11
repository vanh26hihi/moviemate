<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\CheckinTicketRequest;
use App\Models\AdmissionTicket;
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

    public function store(CheckinTicketRequest $request, TicketQrPayload $payloads, TicketResolutionService $tickets): RedirectResponse
    {
        $input = trim((string) $request->validated('ticket'));
        $capability = $payloads->capabilityFrom($input);
        $rateKey = 'staff-ticket-checkin-lookup:'.$request->user()->id.':'.hash('sha256', $input);
        abort_if(RateLimiter::tooManyAttempts($rateKey, 12), 429);
        RateLimiter::hit($rateKey, 60);

        $ticket = $capability !== null
            ? $tickets->resolveTicket($capability, $request->user())
            : $tickets->resolveTicketCode(strtoupper($input), $request->user());

        return redirect()->route('staff.tickets.check')->with('ticket_lookup', [
            'id' => $ticket->id,
            'ticket_code' => $ticket->ticket_code,
            'booking_code' => $ticket->booking->booking_code,
            'movie' => $ticket->booking->showtime?->movie?->title,
            'showtime' => $ticket->booking->showtime_label,
            'cinema' => $ticket->booking->showtime?->cinema?->name,
            'room' => $ticket->booking->showtime?->room?->name,
            'seat' => $ticket->seat_code,
            'status' => $ticket->used_at ? 'used' : 'unused',
            'used_at' => $ticket->used_at?->format('d/m/Y H:i:s'),
        ]);
    }

    public function confirm(
        Request $request,
        AdmissionTicket $admissionTicket,
        TicketResolutionService $tickets,
        TicketCheckinCapability $capabilities,
        TicketCheckinService $checkins,
    ): RedirectResponse {
        $ticket = $tickets->authorizedTicket($admissionTicket, $request->user());
        $key = 'staff-ticket-admit:'.$request->user()->id.':'.$ticket->id;
        abort_if(RateLimiter::tooManyAttempts($key, 12), 429);
        RateLimiter::hit($key, 60);
        $result = $checkins->checkIn($capabilities->issue($ticket), $request->user());

        return redirect()->route('staff.tickets.check')->with('checkin_result', $this->resultPayload($result));
    }

    public function storeBooking(
        Request $request,
        Booking $booking,
        TicketResolutionService $tickets,
        TicketCheckinCapability $capabilities,
        TicketCheckinService $checkins,
    ): RedirectResponse {
        $booking = $tickets->authorizedBooking($booking, $request->user());
        $ticket = $booking->admissionTickets->sortBy('id')->first(fn (AdmissionTicket $ticket) => $ticket->used_at === null);
        abort_unless($ticket, 409, 'Tất cả vé trong đơn đã được sử dụng.');
        $result = $checkins->checkIn($capabilities->issue($ticket), $request->user());

        return redirect()->route('staff.tickets.operations', $booking)->with('checkin_result', $this->resultPayload($result));
    }

    private function resultPayload($result): array
    {
        return [
            'result' => $result->result,
            'message' => $result->message,
            'booking_code' => $result->booking?->booking_code,
            'ticket_code' => $result->ticket?->ticket_code,
            'seat' => $result->ticket?->seat_code,
            'used_at' => $result->ticket?->used_at?->format('d/m/Y H:i:s'),
        ];
    }
}
