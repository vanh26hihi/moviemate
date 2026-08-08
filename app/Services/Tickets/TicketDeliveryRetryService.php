<?php

namespace App\Services\Tickets;

use App\Domain\Tickets\TicketDeliveryRetryResult;
use App\Models\Booking;
use App\Models\BookingTicketDelivery;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class TicketDeliveryRetryService
{
    public function __construct(private readonly BookingTicketEligibility $eligibility) {}

    public function retry(BookingTicketDelivery $delivery): TicketDeliveryRetryResult
    {
        return DB::transaction(function () use ($delivery): TicketDeliveryRetryResult {
            $now = now()->startOfSecond();
            $locked = BookingTicketDelivery::query()->lockForUpdate()->findOrFail($delivery->getKey());
            $booking = Booking::query()->with('payments')->lockForUpdate()->findOrFail($locked->booking_id);

            if (! $this->eligibility->isDeliverable($booking)) {
                return new TicketDeliveryRetryResult('ineligible', $locked, false);
            }
            if ($locked->status === BookingTicketDelivery::STATUS_SENT) {
                return new TicketDeliveryRetryResult('sent', $locked, false);
            }
            if ($this->hasActiveClaim($locked, $now)) {
                return new TicketDeliveryRetryResult('active_claim', $locked, false);
            }
            if ($locked->status === BookingTicketDelivery::STATUS_PENDING
                && ($locked->available_at === null || $locked->available_at?->lte($now))) {
                return new TicketDeliveryRetryResult('already_queued', $locked, false);
            }

            $category = $locked->status === BookingTicketDelivery::STATUS_PROCESSING
                ? 'expired_claim_released'
                : 'queued';
            $locked->forceFill([
                'status' => BookingTicketDelivery::STATUS_PENDING,
                'available_at' => $now,
                'processing_started_at' => null,
                'lease_expires_at' => null,
            ])->save();

            return new TicketDeliveryRetryResult($category, $locked, true);
        });
    }

    public function hasActiveClaim(BookingTicketDelivery $delivery, ?CarbonInterface $now = null): bool
    {
        if ($delivery->status !== BookingTicketDelivery::STATUS_PROCESSING) {
            return false;
        }

        $now ??= now()->startOfSecond();
        if ($delivery->lease_expires_at !== null) {
            return $delivery->lease_expires_at->gt($now);
        }

        $leaseSeconds = max(30, (int) config('payment.ticket_delivery.lease_seconds', 300));

        return $delivery->processing_started_at?->gt($now->copy()->subSeconds($leaseSeconds)) === true;
    }
}
