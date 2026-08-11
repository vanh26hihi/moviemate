<?php

namespace App\Http\Controllers;

use App\Services\Tickets\BookingTicketEligibility;
use App\Services\Tickets\TicketQrPayload;
use App\Services\Tickets\TicketResolutionService;
use App\Support\PrivacyMask;
use Illuminate\Http\Response;

final class TicketVerificationController extends Controller
{
    public function __invoke(
        string $capability,
        TicketResolutionService $tickets,
        BookingTicketEligibility $eligibility,
        TicketQrPayload $payloads,
    ): Response {
        $singleAdmissionTicket = $tickets->resolvePublicTicket($capability);
        $booking = $singleAdmissionTicket->booking;
        $isUsable = $eligibility->isUsable($booking);
        $isDeliverable = $eligibility->isDeliverable($booking);
        $verifiedPayment = $eligibility->verifiedPayment($booking);
        $ticketQrPayloads = collect([$singleAdmissionTicket->id => $isDeliverable ? $payloads->url($singleAdmissionTicket) : null]);
        $ticketRecipient = PrivacyMask::email($booking->recipient_email);
        $ticketCustomer = PrivacyMask::name($booking->user?->name);
        $backUrl = route('home');
        $backLabel = 'Về trang chủ';
        $ticketState = $singleAdmissionTicket->used_at ? 'used' : ($isDeliverable ? 'valid' : 'invalid');

        return response()->view('user.bookings.ticket', compact(
            'booking', 'isUsable', 'isDeliverable', 'verifiedPayment', 'ticketQrPayloads',
            'ticketState', 'ticketRecipient', 'ticketCustomer', 'backUrl', 'backLabel', 'singleAdmissionTicket'
        ))->header('Cache-Control', 'private, no-store, no-cache, must-revalidate')->header('Pragma', 'no-cache');
    }
}
