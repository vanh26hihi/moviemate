<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\BookingCancellationService;
use App\Services\Tickets\BookingTicketEligibility;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class BookingHistoryController extends Controller
{
    public function __invoke(
        Request $request,
        BookingCancellationService $cancellations,
        BookingTicketEligibility $ticketEligibility,
    ): View {
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
            ])
            ->when(
                isset($statusFilters[$status]),
                fn ($query) => $query->where('booking_status', $statusFilters[$status]),
            )
            ->latest()
            ->paginate(10);

        $cancellableBookingIds = $bookings->getCollection()
            ->filter(fn ($booking): bool => $cancellations->isCancellable($booking))
            ->modelKeys();
        $ticketableBookingIds = $bookings->getCollection()
            ->filter(fn ($booking): bool => $ticketEligibility->isUsable($booking))
            ->modelKeys();

        return view('user.bookings.history', compact(
            'bookings',
            'cancellableBookingIds',
            'ticketableBookingIds',
        ));
    }
}
