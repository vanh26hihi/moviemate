<?php

namespace App\Services\Tickets;

use App\Models\AdmissionTicket;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\FoodPickupVoucher;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TicketArtifactProvisioner
{
    public function provisionSeat(BookingSeat $bookingSeat): AdmissionTicket
    {
        return AdmissionTicket::query()->firstOrCreate(
            ['booking_seat_id' => $bookingSeat->id],
            ['booking_id' => $bookingSeat->booking_id, 'ticket_code' => $this->uniqueCode('AT')],
        );
    }

    public function provisionFoodForOrderItem(OrderItem $item): ?FoodPickupVoucher
    {
        $bookingId = (int) $item->order()->value('booking_id');
        if ($bookingId < 1) {
            return null;
        }

        return FoodPickupVoucher::query()->firstOrCreate(
            ['booking_id' => $bookingId],
            ['voucher_code' => $this->uniqueCode('FV')],
        );
    }

    public function provision(Booking $booking): void
    {
        if ($booking->payment_status !== 'paid'
            || ! in_array($booking->booking_status, ['paid', 'used'], true)) {
            return;
        }

        DB::transaction(function () use ($booking): void {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
            if ($booking->payment_status !== 'paid'
                || ! in_array($booking->booking_status, ['paid', 'used'], true)) {
                return;
            }

            foreach ($booking->bookingSeats()->orderBy('id')->get(['id', 'booking_id']) as $bookingSeat) {
                $this->provisionSeat($bookingSeat);
            }

            $hasFood = $booking->foodOrder()->whereHas('items')->exists();
            if ($hasFood) {
                FoodPickupVoucher::query()->firstOrCreate(
                    ['booking_id' => $booking->id],
                    ['voucher_code' => $this->uniqueCode('FV')],
                );
            }
        }, 3);
    }

    private function uniqueCode(string $prefix): string
    {
        return $prefix.'-'.strtoupper(Str::random(26));
    }
}
