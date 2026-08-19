<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Showtime;

final class ShowtimeCancellationPreviewService
{
    /** @return array{booking_count:int,pending_count:int,paid_count:int,terminal_count:int,seat_count:int,admission_ticket_count:int,printed_ticket_count:int,food_booking_count:int,voucher_count:int,printed_voucher_count:int,refund_amount:int,currency:string} */
    public function summarize(Showtime $showtime): array
    {
        $bookings = Booking::query()
            ->where('showtime_id', $showtime->id)
            ->withCount([
                'bookingSeats',
                'admissionTickets',
                'admissionTickets as printed_ticket_count' => fn ($query) => $query->where('print_count', '>', 0),
            ])
            ->withExists('foodOrder')
            ->with('foodPickupVoucher:id,booking_id,print_count')
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
            'admission_ticket_count' => (int) $bookings->sum('admission_tickets_count'),
            'printed_ticket_count' => (int) $bookings->sum('printed_ticket_count'),
            'food_booking_count' => $bookings->where('food_order_exists', true)->count(),
            'voucher_count' => $bookings->whereNotNull('foodPickupVoucher')->count(),
            'printed_voucher_count' => $bookings->filter(fn (Booking $booking): bool => (int) $booking->foodPickupVoucher?->print_count > 0)->count(),
            'refund_amount' => $refundAmount,
            'currency' => 'VND',
        ];
    }
}
