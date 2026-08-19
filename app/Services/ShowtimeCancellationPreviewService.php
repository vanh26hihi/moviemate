<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Showtime;

final class ShowtimeCancellationPreviewService
{
    /** @return array{booking_count:int,pending_count:int,paid_count:int,terminal_count:int,seat_count:int,refund_amount:int,currency:string} */
    public function summarize(Showtime $showtime): array
    {
        $bookings = Booking::query()
            ->where('showtime_id', $showtime->id)
            ->withCount('bookingSeats')
            ->with(['payments' => fn ($query) => $query->orderBy('id')])
            ->orderBy('id')
            ->get();
        $refundAmount = 0;
        $paidCount = 0;
        foreach ($bookings as $booking) {
            $payment = $booking->payments
                ->filter(fn (Payment $payment): bool => $payment->hasAuthoritativeSuccessEvidence())
                ->sortByDesc('id')
                ->first();
            if ($payment) {
                $paidCount++;
                $refundAmount += (int) $payment->amount;
            }
        }

        return [
            'booking_count' => $bookings->count(),
            'pending_count' => $bookings->where('booking_status', 'pending_payment')->count(),
            'paid_count' => $paidCount,
            'terminal_count' => $bookings->whereIn('booking_status', ['cancelled', 'expired'])->count(),
            'seat_count' => (int) $bookings->sum('booking_seats_count'),
            'refund_amount' => $refundAmount,
            'currency' => 'VND',
        ];
    }
}
