<?php

namespace App\Services\Payments;

use App\Domain\Money\VndAmount;
use App\Exceptions\PaymentInitiationException;
use App\Models\Booking;
use Throwable;

final class AuthoritativeBookingPaymentAmount
{
    public function resolve(Booking $booking): int
    {
        try {
            $total = VndAmount::fromDatabase($booking->getRawOriginal('total_amount'));
            $seatSubtotal = VndAmount::fromDatabase($booking->getRawOriginal('seat_subtotal'));
            $foodSubtotal = VndAmount::fromDatabase($booking->getRawOriginal('food_subtotal'));
            $gross = VndAmount::fromDatabase($booking->getRawOriginal('gross_amount'));
            $promotionDiscount = VndAmount::fromDatabase(
                $booking->getRawOriginal('promotion_discount_amount'),
            );
            $pointsDiscount = VndAmount::fromDatabase($booking->getRawOriginal('points_discount_amount'));
            $calculatedGross = $seatSubtotal->add($foodSubtotal);
            $discounts = $promotionDiscount->add($pointsDiscount);
        } catch (Throwable $exception) {
            throw new PaymentInitiationException('Stored booking pricing is invalid.', previous: $exception);
        }

        if (! $calculatedGross->equals($gross)
            || $discounts->compareTo($gross) > 0
            || $gross->value() - $discounts->value() !== $total->value()
            || $total->value() <= 0) {
            throw new PaymentInitiationException('Stored booking pricing failed server verification.');
        }

        return $total->value();
    }
}
