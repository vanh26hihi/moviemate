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
            'used' => 'used',
            'cancelled' => 'cancelled',
            'expired' => 'expired',
        ];

        $bookings = $request->user()->bookings()
            ->with([
                'showtime.movie',
                'showtime.cinema',
                'showtime.room',
                'bookingSeats.seat',
                'payments',
                'discountCodeRedemptions',
                'pointRedemption',
            ])
            ->when(
                isset($statusFilters[$status]),
                fn ($query) => $query->where('booking_status', $statusFilters[$status]),
            )
            ->latest()
            ->paginate(10);

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
