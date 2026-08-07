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
        $booking = $tickets->resolvePublic($capability);
        $isUsable = $eligibility->isUsable($booking);
        $isDeliverable = $eligibility->isDeliverable($booking);
        $verifiedPayment = $eligibility->verifiedPayment($booking);
        $ticketQrPayload = $isDeliverable ? $payloads->url($booking) : null;
        $ticketRecipient = PrivacyMask::email($booking->recipient_email);
        $ticketCustomer = PrivacyMask::name($booking->user?->name);
        $backUrl = route('home');
        $backLabel = 'Về trang chủ';
        $ticketState = match (true) {
            $booking->payment_status === 'refunded' => 'refunded',
            $booking->booking_status === 'cancelled' => 'cancelled',
            $booking->booking_status === 'expired' => 'expired',
            $booking->booking_status === 'used' => 'used',
            $isUsable => 'valid',
            default => 'invalid',
        };

        return response()->view('user.bookings.ticket', compact(
            'booking', 'isUsable', 'isDeliverable', 'verifiedPayment', 'ticketQrPayload',
            'ticketState', 'ticketRecipient', 'ticketCustomer', 'backUrl', 'backLabel'
        ))->header('Cache-Control', 'private, no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache');
    }
}
