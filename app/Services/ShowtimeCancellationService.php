<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Payment;
use App\Models\RefundCase;
use App\Models\Showtime;
use App\Models\ShowtimeCancellation;
use App\Models\ShowtimeCancellationImpact;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ShowtimeCancellationService
{
    public function __construct(
        private readonly ShowtimeLifecycleService $lifecycle,
        private readonly BookingSeatLockService $seatLocks,
        private readonly BookingFoodService $food,
        private readonly PromotionService $promotions,
        private readonly ActivityLogger $activities,
    ) {}

    public function cancel(Showtime $showtime, User $actor, string $reasonCode, ?string $reasonNote): ShowtimeCancellation
    {
        if (! array_key_exists($reasonCode, ShowtimeCancellation::REASONS)) {
            throw ValidationException::withMessages(['reason_code' => 'Lý do hủy suất chiếu không hợp lệ.']);
        }

        return DB::transaction(function () use ($showtime, $actor, $reasonCode, $reasonNote): ShowtimeCancellation {
            $lockedShowtime = Showtime::query()->lockForUpdate()->findOrFail($showtime->id);
            $existing = ShowtimeCancellation::query()
                ->where('showtime_id', $lockedShowtime->id)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }
            if ($lockedShowtime->status !== 'active') {
                throw ValidationException::withMessages(['showtime' => 'Chỉ suất chiếu đang hoạt động mới có thể hủy.']);
            }
            if ($this->lifecycle->state($lockedShowtime) === ShowtimeLifecycleService::COMPLETED) {
                throw ValidationException::withMessages(['showtime' => 'Suất chiếu đã kết thúc nên không thể hủy.']);
            }

            $bookings = Booking::query()
                ->where('showtime_id', $lockedShowtime->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $payments = Payment::query()
                ->whereIn('booking_id', $bookings->modelKeys())
                ->orderBy('booking_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->groupBy('booking_id');
            $seatCounts = BookingSeat::query()
                ->whereIn('booking_id', $bookings->modelKeys())
                ->selectRaw('booking_id, COUNT(*) AS aggregate')
                ->groupBy('booking_id')
                ->pluck('aggregate', 'booking_id');
            $ticketCounts = DB::table('admission_tickets')
                ->whereIn('booking_id', $bookings->modelKeys())
                ->selectRaw('booking_id, COUNT(*) AS ticket_count, SUM(CASE WHEN print_count > 0 THEN 1 ELSE 0 END) AS printed_count')
                ->groupBy('booking_id')
                ->get()
                ->keyBy('booking_id');
            $foodBookings = DB::table('orders')->whereIn('booking_id', $bookings->modelKeys())->pluck('id', 'booking_id');
            $vouchers = DB::table('food_pickup_vouchers')
                ->whereIn('booking_id', $bookings->modelKeys())
                ->get(['booking_id', 'id', 'print_count'])
                ->keyBy('booking_id');
            $now = now();
            $cancellation = ShowtimeCancellation::query()->create([
                'showtime_id' => $lockedShowtime->id,
                'cinema_id' => $lockedShowtime->cinema_id,
                'reason_code' => $reasonCode,
                'reason_note' => $reasonNote,
                'status' => ShowtimeCancellation::STATUS_OPEN,
                'cancelled_by_user_id' => $actor->id,
                'cancelled_at' => $now,
            ]);

            foreach ($bookings as $booking) {
                /** @var Collection<int, Payment> $bookingPayments */
                $bookingPayments = $payments->get($booking->id, collect());
                $authoritative = $bookingPayments
                    ->filter(fn (Payment $payment): bool => $payment->hasAuthoritativeSuccessEvidence())
                    ->sortByDesc('id')
                    ->first();
                $beforeStatus = (string) $booking->booking_status;
                $beforePaymentStatus = (string) $booking->payment_status;
                $alreadyTerminal = in_array($beforeStatus, ['cancelled', 'expired'], true);
                $outcome = $alreadyTerminal
                    ? ShowtimeCancellationImpact::OUTCOME_ALREADY_TERMINAL
                    : ($authoritative ? ShowtimeCancellationImpact::OUTCOME_REFUND_REQUIRED : ShowtimeCancellationImpact::OUTCOME_UNPAID_CANCELLED);
                $amount = $authoritative ? (int) $authoritative->amount : 0;
                $impact = ShowtimeCancellationImpact::query()->create([
                    'showtime_cancellation_id' => $cancellation->id,
                    'booking_id' => $booking->id,
                    'outcome' => $outcome,
                    'booking_status_before' => $beforeStatus,
                    'payment_status_before' => $beforePaymentStatus,
                    'authoritative_amount' => $amount,
                    'currency' => strtoupper((string) ($authoritative?->currency ?? $booking->currency ?? 'VND')),
                    'seat_count' => (int) ($seatCounts->get($booking->id) ?? 0),
                    'audit_snapshot' => $this->impactSnapshot(
                        $booking,
                        $bookingPayments,
                        $authoritative,
                        $ticketCounts->get($booking->id),
                        $foodBookings->has($booking->id),
                        $vouchers->get($booking->id),
                    ),
                ]);

                if (! $alreadyTerminal) {
                    $booking->forceFill(['booking_status' => 'cancelled'])->save();
                    $this->seatLocks->release($booking);
                    if (! $authoritative) {
                        $this->food->transitionForBooking($booking, 'cancelled');
                        $this->promotions->release($booking);
                    }
                }
                if ($authoritative) {
                    $this->createRefundCase($cancellation, $impact, $booking, $authoritative);
                }
            }

            $lockedShowtime->forceFill(['status' => 'cancelled'])->save();
            $this->resolveIfComplete($cancellation, $actor, $now);
            $this->activities->log(
                'showtime.cancelled_by_cinema',
                $cancellation,
                ['showtime_status' => 'active'],
                ['showtime_status' => 'cancelled', 'cancellation_status' => $cancellation->fresh()->status],
                [
                    'showtime_id' => $lockedShowtime->id,
                    'cinema_id' => $lockedShowtime->cinema_id,
                    'booking_count' => $bookings->count(),
                    'booking_ids' => $bookings->modelKeys(),
                    'refund_case_count' => $cancellation->refundCases()->count(),
                    'refund_case_ids' => $cancellation->refundCases()->orderBy('id')->pluck('id')->all(),
                    'reason_code' => $reasonCode,
                ],
            );

            return $cancellation->fresh(['impacts', 'refundCases']);
        }, 3);
    }

    public function recordLateAuthoritativeSuccess(Booking $booking, Payment $payment): RefundCase
    {
        $impact = ShowtimeCancellationImpact::query()
            ->where('booking_id', $booking->id)
            ->lockForUpdate()
            ->firstOrFail();
        $cancellation = ShowtimeCancellation::query()->lockForUpdate()->findOrFail($impact->showtime_cancellation_id);
        $impact->forceFill([
            'outcome' => ShowtimeCancellationImpact::OUTCOME_REFUND_REQUIRED,
            'authoritative_amount' => (int) $payment->amount,
            'currency' => strtoupper((string) $payment->currency),
            'audit_snapshot' => [
                ...($impact->audit_snapshot ?? []),
                'late_authoritative_success' => [
                    'payment_id' => $payment->id,
                    'provider' => $payment->provider,
                    'amount' => (int) $payment->amount,
                    'recorded_at' => now()->toIso8601String(),
                ],
            ],
        ])->save();
        $cancellation->forceFill([
            'status' => ShowtimeCancellation::STATUS_OPEN,
            'resolved_by_user_id' => null,
            'resolved_at' => null,
        ])->save();

        return $this->createRefundCase($cancellation, $impact, $booking, $payment);
    }

    private function createRefundCase(ShowtimeCancellation $cancellation, ShowtimeCancellationImpact $impact, Booking $booking, Payment $payment): RefundCase
    {
        return RefundCase::query()->firstOrCreate(
            ['showtime_cancellation_impact_id' => $impact->id],
            [
                'showtime_cancellation_id' => $cancellation->id,
                'cinema_id' => $cancellation->cinema_id,
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'status' => RefundCase::STATUS_REQUIRED,
                'required_amount' => (int) $payment->amount,
                'currency' => strtoupper((string) $payment->currency),
            ],
        );
    }

    private function resolveIfComplete(ShowtimeCancellation $cancellation, User $actor, mixed $resolvedAt): void
    {
        if ($cancellation->refundCases()->where('status', RefundCase::STATUS_REQUIRED)->exists()) {
            return;
        }
        $cancellation->forceFill([
            'status' => ShowtimeCancellation::STATUS_RESOLVED,
            'resolved_by_user_id' => $actor->id,
            'resolved_at' => $resolvedAt,
        ])->save();
    }

    /** @param Collection<int, Payment> $payments */
    private function impactSnapshot(
        Booking $booking,
        Collection $payments,
        ?Payment $authoritative,
        mixed $ticketCounts,
        bool $hadFood,
        mixed $voucher,
    ): array {
        return [
            'booking_id' => $booking->id,
            'booking_code' => $booking->booking_code,
            'booking_status' => $booking->booking_status,
            'payment_status' => $booking->payment_status,
            'sales_channel' => $booking->sales_channel,
            'total_amount' => (int) $booking->total_amount,
            'currency' => strtoupper((string) ($booking->currency ?? 'VND')),
            'authoritative_payment_id' => $authoritative?->id,
            'had_food' => $hadFood,
            'admission_ticket_count' => (int) ($ticketCounts?->ticket_count ?? 0),
            'printed_ticket_count' => (int) ($ticketCounts?->printed_count ?? 0),
            'food_pickup_voucher_id' => $voucher?->id,
            'food_pickup_voucher_print_count' => (int) ($voucher?->print_count ?? 0),
            'payment_attempts' => $payments->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'provider' => $payment->provider,
                'status' => $payment->status,
                'amount' => (int) $payment->amount,
                'authoritative' => $payment->hasAuthoritativeSuccessEvidence(),
            ])->values()->all(),
        ];
    }
}
