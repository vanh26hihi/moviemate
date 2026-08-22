<?php

namespace App\Services\Tickets;

use App\Domain\Money\VndAmount;
use App\Domain\Tickets\BookingPrintAmounts;
use App\Models\Booking;
use Brick\Math\BigInteger;
use RuntimeException;
use Throwable;

final class BookingPrintAmountAllocator
{
    public function __construct(private readonly BookingTicketEligibility $eligibility) {}

    public function allocate(Booking $booking): BookingPrintAmounts
    {
        $booking->loadMissing([
            'payments',
            'bookingSeats.admissionTicket',
            'admissionTickets.bookingSeat',
            'foodOrder.items',
            'foodPickupVoucher',
        ]);

        $payment = $this->eligibility->verifiedPayment($booking);
        if (! $this->eligibility->isPrintable($booking) || $payment === null) {
            throw new RuntimeException('Only a paid booking with authoritative settlement can be allocated for printing.');
        }

        try {
            $seatSubtotal = VndAmount::fromDatabase($booking->getRawOriginal('seat_subtotal'))->value();
            $foodSubtotal = VndAmount::fromDatabase($booking->getRawOriginal('food_subtotal'))->value();
            $gross = VndAmount::fromDatabase($booking->getRawOriginal('gross_amount'))->value();
            $discount = VndAmount::fromDatabase($booking->getRawOriginal('promotion_discount_amount'))->value();
            $payable = VndAmount::fromDatabase($booking->getRawOriginal('total_amount'))->value();
            $settledAmount = VndAmount::fromDatabase($payment->getRawOriginal('amount'))->value();
        } catch (Throwable $exception) {
            throw new RuntimeException('The finalized booking commercial snapshot is invalid.', previous: $exception);
        }

        if ($gross !== $seatSubtotal + $foodSubtotal
            || $discount > $gross
            || $payable !== $gross - $discount
            || $settledAmount !== $payable
            || ($payment->currency ?: 'VND') !== ($booking->currency ?: 'VND')
            || ($booking->currency ?: 'VND') !== 'VND') {
            throw new RuntimeException('The finalized booking commercial snapshot is inconsistent.');
        }

        $tickets = $booking->admissionTickets->sortBy('booking_seat_id', SORT_NUMERIC)->values();
        if ($tickets->isEmpty()
            || $tickets->count() !== $booking->bookingSeats->count()
            || $tickets->pluck('booking_seat_id')->unique()->count() !== $tickets->count()) {
            throw new RuntimeException('Admission ticket artifacts do not match the finalized booking seats.');
        }

        $artifacts = [];
        $seatGross = 0;
        foreach ($tickets as $order => $ticket) {
            if (! $ticket->bookingSeat || (int) $ticket->bookingSeat->booking_id !== (int) $booking->id) {
                throw new RuntimeException('An admission ticket is not linked to the finalized booking.');
            }

            try {
                $weight = VndAmount::fromDatabase($ticket->bookingSeat->getRawOriginal('price'))->value();
            } catch (Throwable $exception) {
                throw new RuntimeException('An admission ticket gross snapshot is invalid.', previous: $exception);
            }
            $seatGross += $weight;
            $artifacts[] = [
                'key' => 'ticket:'.$ticket->id,
                'ticket_id' => (int) $ticket->id,
                'weight' => $weight,
                'order' => $order,
            ];
        }
        if ($seatGross !== $seatSubtotal) {
            throw new RuntimeException('Admission ticket gross snapshots do not equal the booking seat subtotal.');
        }

        $foodItems = $booking->foodOrder?->items ?? collect();
        $hasFood = $foodItems->isNotEmpty();
        $foodItemGross = VndAmount::zero();
        foreach ($foodItems as $item) {
            try {
                $quantity = (int) $item->quantity;
                $unitPrice = VndAmount::fromDatabase($item->getRawOriginal('unit_price'));
                $lineTotal = VndAmount::fromDatabase($item->getRawOriginal('line_total'));
                if ($quantity < 1 || ! $unitPrice->multiply($quantity)->equals($lineTotal)) {
                    throw new RuntimeException('A finalized food item snapshot is inconsistent.');
                }
                $foodItemGross = $foodItemGross->add($lineTotal);
            } catch (Throwable $exception) {
                throw new RuntimeException('A finalized food item snapshot is invalid.', previous: $exception);
            }
        }
        if ($foodItemGross->value() !== $foodSubtotal) {
            throw new RuntimeException('Finalized food item snapshots do not equal the booking food subtotal.');
        }

        $voucher = $booking->foodPickupVoucher;
        if ($hasFood && $voucher === null) {
            throw new RuntimeException('The finalized food component has no pickup voucher artifact.');
        }
        if (! $hasFood && $voucher !== null) {
            throw new RuntimeException('A food pickup voucher exists without a finalized food component.');
        }
        if ($voucher !== null) {
            $artifacts[] = [
                'key' => 'food:'.$voucher->id,
                'ticket_id' => null,
                'weight' => $foodSubtotal,
                'order' => count($artifacts),
            ];
        }

        $allocated = $this->proportionallyAllocate($artifacts, $gross, $payable);
        $ticketAmounts = [];
        foreach ($artifacts as $artifact) {
            if ($artifact['ticket_id'] !== null) {
                $ticketAmounts[$artifact['ticket_id']] = $allocated[$artifact['key']];
            }
        }
        $foodVoucherAmount = $voucher === null ? null : $allocated['food:'.$voucher->id];
        $result = new BookingPrintAmounts($ticketAmounts, $foodVoucherAmount, $payable);

        if ($result->allocatedTotal() !== $payable
            || collect($ticketAmounts)->contains(fn (int $amount): bool => $amount < 0)
            || ($foodVoucherAmount !== null && $foodVoucherAmount < 0)) {
            throw new RuntimeException('Physical print allocation failed its exact-sum invariant.');
        }

        return $result;
    }

    /**
     * @param  list<array{key:string, ticket_id:?int, weight:int, order:int}>  $artifacts
     * @return array<string, int>
     */
    private function proportionallyAllocate(array $artifacts, int $gross, int $payable): array
    {
        if ($gross === 0) {
            if ($payable !== 0) {
                throw new RuntimeException('A zero-gross booking cannot have a positive payable amount.');
            }

            return collect($artifacts)->mapWithKeys(fn (array $artifact): array => [$artifact['key'] => 0])->all();
        }

        if ($payable === $gross) {
            return collect($artifacts)->mapWithKeys(fn (array $artifact): array => [$artifact['key'] => $artifact['weight']])->all();
        }

        $rows = [];
        $allocatedTotal = 0;
        foreach ($artifacts as $artifact) {
            [$quotient, $remainder] = BigInteger::of($payable)
                ->multipliedBy($artifact['weight'])
                ->quotientAndRemainder($gross);
            $amount = $quotient->toInt();
            $allocatedTotal += $amount;
            $rows[] = $artifact + ['amount' => $amount, 'remainder' => $remainder];
        }

        usort($rows, function (array $left, array $right): int {
            $remainderOrder = $right['remainder']->compareTo($left['remainder']);

            return $remainderOrder !== 0 ? $remainderOrder : $left['order'] <=> $right['order'];
        });

        $units = $payable - $allocatedTotal;
        if ($units < 0 || $units >= count($rows)) {
            throw new RuntimeException('Physical print allocation produced an invalid rounding remainder.');
        }
        for ($index = 0; $index < $units; $index++) {
            $rows[$index]['amount']++;
        }

        $amounts = [];
        foreach ($rows as $row) {
            $amounts[$row['key']] = $row['amount'];
        }

        return $amounts;
    }
}
