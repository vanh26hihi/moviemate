<?php

namespace App\Services;

use App\Domain\Bookings\FoodLine;
use App\Domain\Bookings\FoodPriceBreakdown;
use App\Domain\Money\VndAmount;
use App\Exceptions\FoodSelectionValidationException;
use App\Models\Booking;
use App\Models\FoodItem;
use App\Models\Order;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use OverflowException;

class BookingFoodService
{
    public function __construct(
        private readonly CinemaContext $cinemaContext,
        private readonly FoodSelectionCanonicalizer $foodSelections,
    ) {}

    public function calculate(array|Collection|null $payload, ?int $cinemaId = null): FoodPriceBreakdown
    {
        $selection = $this->foodSelections->canonicalize($payload);
        if ($selection === []) {
            return FoodPriceBreakdown::empty();
        }

        $foods = FoodItem::query()
            ->whereIn('id', array_column($selection, 'food_id'))
            ->when($cinemaId !== null, fn ($query) => $query->where(
                fn ($scope) => $scope->whereNull('cinema_id')->orWhere('cinema_id', $cinemaId)
            ))
            ->get()->keyBy('id');
        $lines = [];
        $subtotal = VndAmount::zero();

        foreach ($selection as ['food_id' => $foodId, 'quantity' => $quantity]) {
            /** @var FoodItem|null $food */
            $food = $foods->get($foodId);
            if (! $food) {
                throw FoodSelectionValidationException::unavailable();
            }
            if (! $food->active) {
                throw FoodSelectionValidationException::unavailable();
            }

            try {
                $unitPrice = VndAmount::fromDatabase($food->getRawOriginal('price') ?? $food->price);
                $lineTotal = $unitPrice->multiply($quantity);
                $subtotal = $subtotal->add($lineTotal);
            } catch (InvalidArgumentException|OverflowException $exception) {
                throw FoodSelectionValidationException::invalidPrice();
            }

            $lines[] = new FoodLine(
                (int) $food->getKey(),
                (string) $food->name,
                $unitPrice->value(),
                $quantity,
                $lineTotal->value(),
            );
        }

        return new FoodPriceBreakdown(
            $subtotal->value(),
            $lines,
            $cinemaId ?? $this->cinemaContext->id(),
        );
    }

    public function persist(FoodPriceBreakdown $food, array $attributes = []): ?Order
    {
        if ($food->isEmpty()) {
            return null;
        }
        if ($food->pickupCinemaId === null) {
            throw new InvalidArgumentException('A food order requires the canonical pickup cinema.');
        }
        if (! isset($attributes['booking_id'])) {
            throw new InvalidArgumentException('Food orders must belong to a unified booking checkout.');
        }

        $booking = Booking::query()->lockForUpdate()->findOrFail((int) $attributes['booking_id']);
        if ($food->pickupCinemaId !== (int) $booking->cinema_id) {
            throw new InvalidArgumentException('Food pickup must use the booking cinema.');
        }
        if ($booking->booking_status !== 'pending_payment' || $booking->payment_status !== 'unpaid') {
            throw new InvalidArgumentException('Unified food can only be attached to an unpaid pending booking.');
        }

        $order = Order::query()->create([
            'booking_id' => $booking->id,
            'user_id' => $booking->user_id,
            'customer_name' => (string) ($attributes['customer_name'] ?? $booking->customer_name ?? ''),
            'customer_phone' => $attributes['customer_phone'] ?? $booking->customer_phone,
            'customer_email' => $booking->customer_email,
            'pickup_cinema_id' => $booking->cinema_id,
            'subtotal' => $food->foodSubtotal,
            'total_amount' => $food->foodSubtotal,
            'status' => 'pending',
        ]);

        foreach ($food->lines as $line) {
            $order->items()->create([
                'food_item_id' => $line->foodId,
                'quantity' => $line->quantity,
                'snapshot_name' => $line->snapshotName,
                'unit_price' => $line->unitPrice,
                'line_total' => $line->lineTotal,
                'price' => $line->unitPrice,
                'total' => $line->lineTotal,
            ]);
        }

        return $order->load('items');
    }

    public function transitionForBooking(Booking|int $booking, string $status): int
    {
        if (! in_array($status, ['expired', 'cancelled'], true)) {
            throw new InvalidArgumentException('Unified food orders may only transition to expired or cancelled here.');
        }

        $booking = $booking instanceof Booking
            ? $booking->fresh()
            : Booking::query()->find($booking);
        if (! $booking || $booking->booking_status !== $status) {
            throw new InvalidArgumentException("Booking must be {$status} before its food order can transition.");
        }

        return Order::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'pending')
            ->update(['status' => $status]);
    }
}
