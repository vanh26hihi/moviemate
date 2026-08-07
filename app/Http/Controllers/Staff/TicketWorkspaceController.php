<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\CinemaAccessService;
use App\Services\Tickets\TicketCheckinCapability;
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
        TicketCheckinCapability $capabilities,
        TicketResolutionService $tickets,
    ): View {
        $validated = $request->validate(['ticket' => ['required', 'string', 'max:512']]);
        $bookingId = $capabilities->bookingId($validated['ticket']);
        $key = 'staff-ticket-resolve:'.$request->user()->id.':'.($bookingId ?? 'invalid');
        abort_if(RateLimiter::tooManyAttempts($key, 12), 429);
        RateLimiter::hit($key, 60);

        return $this->operationsView($tickets->resolve($validated['ticket'], $request->user()));
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
            $booking->booking_status === 'used' => 'Vé đã được sử dụng.',
            $booking->payment_status !== 'paid' || ! $verified => 'Vé chưa có thanh toán được xác minh.',
            default => 'Vé hợp lệ và đã thanh toán.',
        };

        return view('staff.tickets.operations', [
            'booking' => $booking,
            'customerName' => PrivacyMask::name($booking->user?->name),
            'customerEmail' => PrivacyMask::email($booking->recipient_email),
            'eligibilityMessage' => $eligibility,
            'printState' => $booking->ticketPrint,
        ]);
    }
}
