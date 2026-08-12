<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\SeatIncidentResolution;
use App\Services\CinemaAccessService;
use App\Services\Tickets\BookingLookupCapability;
use App\Services\Tickets\BookingQrPayload;
use App\Services\Tickets\TicketResolutionService;
use App\Support\PrivacyMask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

final class TicketWorkspaceController extends Controller
{
    public function index(Request $request, CinemaAccessService $cinemas): View
    {
        return view('staff.tickets.index', ['cinema' => $cinemas->currentCinema($request->user())]);
    }

    public function resolve(
        Request $request,
        BookingLookupCapability $capabilities,
        BookingQrPayload $payloads,
        TicketResolutionService $tickets,
    ): View {
        $validated = $request->validate(['ticket' => ['required', 'string', 'max:512']]);
        $input = strtoupper(trim($validated['ticket']));
        $capability = $payloads->capabilityFrom($validated['ticket']);
        $bookingId = $capabilities->bookingId($capability);
        $key = 'staff-ticket-resolve:'.$request->user()->id.':'.($bookingId ?? hash('sha256', $input));
        abort_if(RateLimiter::tooManyAttempts($key, 12), 429);
        RateLimiter::hit($key, 60);

        $booking = $capability !== null
            ? $tickets->resolve($capability, $request->user())
            : $tickets->resolveBookingCode($input, $request->user());

        return $this->operationsView($booking);
    }

    public function operations(Request $request, Booking $booking, TicketResolutionService $tickets): View
    {
        return $this->operationsView($tickets->authorizedBooking($booking, $request->user()));
    }

    private function operationsView(Booking $booking): View
    {
        $verified = $booking->payments->contains(
            fn ($payment): bool => $payment->hasAuthoritativeSuccessEvidence()
        );
        $eligibility = match (true) {
            $booking->payment_status === 'refunded' => 'Vé đã được hoàn tiền và không còn hiệu lực.',
            $booking->booking_status === 'cancelled' => 'Đơn đã hủy.',
            $booking->booking_status === 'expired' => 'Đơn đã hết hạn.',
            $booking->payment_status !== 'paid' || ! $verified => 'Vé chưa có thanh toán được xác minh.',
            default => 'Vé hợp lệ và đã thanh toán.',
        };
        $incidentReprints = SeatIncidentResolution::query()
            ->where('reprint_required', true)->whereNull('reprint_satisfied_at')
            ->whereHas('impact', fn ($query) => $query->where('resolution_status', 'unresolved')
                ->whereHas('incident', fn ($incident) => $incident->where('status', 'open'))
                ->whereHas('bookingSeat', fn ($seat) => $seat->where('booking_id', $booking->id)))
            ->with('impact:id,booking_seat_id')->get()->keyBy('impact.booking_seat_id');

        return view('staff.tickets.operations', [
            'booking' => $booking,
            'customerName' => PrivacyMask::name($booking->customer_name ?: $booking->user?->name),
            'customerEmail' => PrivacyMask::email($booking->recipient_email),
            'eligibilityMessage' => $eligibility,
            'incidentReprints' => $incidentReprints,
        ]);
    }
}
