<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\BookingExpirationService;
use App\Services\Payments\BookingPaymentActionPolicy;
use App\Services\Tickets\BookingTicketEligibility;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class BookingHistoryController extends Controller
{
    public function __invoke(
        Request $request,
        BookingExpirationService $expiration,
        BookingPaymentActionPolicy $paymentActions,
        BookingTicketEligibility $ticketEligibility,
    ): View {
        $expiration->expireStaleForUser((int) $request->user()->getAuthIdentifier());

        $status = $request->string('status')->toString();
        $statusFilters = [
            'pending' => 'pending_payment',
            'paid' => 'paid',
            'cancelled' => 'cancelled',
            'expired' => 'expired',
        ];

        $bookings = $request->user()->bookings()
            ->with([
                'showtime.movie',
                'showtime.cinema',
                'showtime.room',
                'showtime.presentationFormat',
                'bookingSeats.seat',
                'payments',
                'promotionUsage',
            ])
            ->when(
                isset($statusFilters[$status]),
                fn ($query) => $query->where('booking_status', $statusFilters[$status]),
            )
            ->latest()
            ->paginate(10);

        $bookings->getCollection()->each(function ($booking): void {
            $booking->setRelation('showtimeCancellationImpact', null);
            $booking->setRelation('refundCase', null);
        });
        $cancelledBookings = $bookings->getCollection()->where('booking_status', 'cancelled')->values();
        if ($cancelledBookings->isNotEmpty()) {
            $cancelledBookings->load(['showtimeCancellationImpact.cancellation', 'refundCase']);
        }

        $bookingActions = $bookings->getCollection()->mapWithKeys(
            fn ($booking): array => [$booking->id => $paymentActions->evaluate($booking)],
        );
        $ticketableBookingIds = $bookings->getCollection()
            ->filter(fn ($booking): bool => $ticketEligibility->isUsable($booking))
            ->modelKeys();

        return view('user.bookings.history', compact(
            'bookings',
            'bookingActions',
            'ticketableBookingIds',
        ));
    }
}
