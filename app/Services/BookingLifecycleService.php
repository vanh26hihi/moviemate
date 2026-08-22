<?php

namespace App\Services;

use App\Models\Booking;

final class BookingLifecycleService
{
    public function __construct(
        private readonly BookingStateService $stateService,
    ) {}

    public function phase(Booking $booking): string
    {
        return $this->stateService->normalizedStatus($booking);
    }

    public function requiresAction(Booking $booking): bool
    {
        return in_array($this->phase($booking), ['pending', 'expired'], true);
    }

    public function allowedTransitions(Booking $booking): array
    {
        return match ($this->phase($booking)) {
            'pending' => ['pay', 'cancel', 'expire'],
            'paid' => ['deliver', 'refund', 'review'],
            'expired' => ['rebook', 'review'],
            'cancelled' => [],
            default => [],
        };
    }

    public function canTransitionTo(Booking $booking, string $target): bool
    {
        $validTargets = ['pay', 'cancel', 'expire', 'deliver', 'refund', 'review', 'rebook'];
        if (! in_array($target, $validTargets, true)) {
            return false;
        }

        return match ($this->phase($booking)) {
            'pending' => in_array($target, ['pay', 'cancel', 'expire'], true),
            'paid' => in_array($target, ['deliver', 'refund', 'review'], true),
            'expired' => in_array($target, ['rebook', 'review'], true),
            'cancelled' => false,
            default => false,
        };
    }

    public function transitionPlan(Booking $booking): array
    {
        $phase = $this->phase($booking);

        $plan = [
            'phase' => $phase,
            'requires_action' => $this->requiresAction($booking),
            'status_label' => $this->stateService->statusLabel($booking),
            'next_hint' => $this->stateService->nextActionHint($booking),
            'remaining_minutes' => $this->stateService->remainingMinutes($booking),
            'actions' => [
                'pay' => $this->stateService->canBePaid($booking),
                'cancel' => $this->stateService->canBeCancelled($booking),
                'expire' => $this->stateService->isExpired($booking),
                'deliver' => $phase === 'paid',
                'refund' => $phase === 'paid',
                'rebook' => $phase === 'expired',
                'review' => in_array($phase, ['paid', 'expired', 'cancelled'], true),
            ],
        ];

        $plan['allowed_transitions'] = $this->allowedTransitions($booking);

        return $plan;
    }

    public function summary(Booking $booking): array
    {
        $phase = $this->phase($booking);

        return [
            'id' => $booking->id,
            'phase' => $phase,
            'status_label' => $this->stateService->statusLabel($booking),
            'can_be_paid' => $this->stateService->canBePaid($booking),
            'can_be_cancelled' => $this->stateService->canBeCancelled($booking),
            'is_expired' => $this->stateService->isExpired($booking),
            'remaining_minutes' => $this->stateService->remainingMinutes($booking),
            'requires_action' => $this->requiresAction($booking),
            'allowed_transitions' => $this->allowedTransitions($booking),
            'next_action_hint' => $this->stateService->nextActionHint($booking),
        ];
    }
}
