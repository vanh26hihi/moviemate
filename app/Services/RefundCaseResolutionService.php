<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\RefundCase;
use App\Models\ShowtimeCancellation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RefundCaseResolutionService
{
    public function __construct(
        private readonly CinemaAccessService $cinemaAccess,
        private readonly ActivityLogger $activities,
    ) {}

    public function resolve(RefundCase $refundCase, User $actor, array $evidence): RefundCase
    {
        return DB::transaction(function () use ($refundCase, $actor, $evidence): RefundCase {
            $locked = RefundCase::query()->lockForUpdate()->findOrFail($refundCase->id);
            $this->cinemaAccess->authorizeCinema($actor, (int) $locked->cinema_id);
            if ($locked->status === RefundCase::STATUS_RESOLVED) {
                return $locked;
            }
            $payment = Payment::query()->lockForUpdate()->findOrFail($locked->payment_id);
            if ((int) $payment->booking_id !== (int) $locked->booking_id
                || ! $payment->hasAuthoritativeSuccessEvidence()
                || (int) $payment->amount !== (int) $locked->required_amount
                || strtoupper((string) $payment->currency) !== strtoupper((string) $locked->currency)) {
                throw ValidationException::withMessages([
                    'refund_case' => 'Payment gốc không còn khớp với nghĩa vụ hoàn tiền. Dừng xử lý và đối soát.',
                ]);
            }
            if ((int) $evidence['resolved_amount'] !== (int) $locked->required_amount) {
                throw ValidationException::withMessages([
                    'resolved_amount' => 'Số tiền ghi nhận phải bằng chính xác nghĩa vụ hoàn tiền.',
                ]);
            }

            $now = now();
            $locked->forceFill([
                'status' => RefundCase::STATUS_RESOLVED,
                'resolution_method' => $evidence['resolution_method'],
                'resolution_reference' => trim($evidence['resolution_reference']),
                'resolution_note' => isset($evidence['resolution_note']) ? trim($evidence['resolution_note']) ?: null : null,
                'resolved_by_user_id' => $actor->id,
                'resolved_at' => $now,
            ])->save();
            $cancellation = ShowtimeCancellation::query()->lockForUpdate()->findOrFail($locked->showtime_cancellation_id);
            if (! RefundCase::query()
                ->where('showtime_cancellation_id', $cancellation->id)
                ->where('status', RefundCase::STATUS_REQUIRED)
                ->lockForUpdate()
                ->exists()) {
                $cancellation->forceFill([
                    'status' => ShowtimeCancellation::STATUS_RESOLVED,
                    'resolved_by_user_id' => $actor->id,
                    'resolved_at' => $now,
                ])->save();
            }
            $this->activities->log(
                'refund_case.manual_resolution_recorded',
                $locked,
                ['status' => RefundCase::STATUS_REQUIRED],
                ['status' => RefundCase::STATUS_RESOLVED],
                [
                    'refund_case_id' => $locked->id,
                    'booking_id' => $locked->booking_id,
                    'payment_id' => $locked->payment_id,
                    'cinema_id' => $locked->cinema_id,
                    'required_amount' => $locked->required_amount,
                    'currency' => $locked->currency,
                    'resolution_method' => $locked->resolution_method,
                    'resolution_reference' => $locked->resolution_reference,
                    'result' => 'external_refund_recorded',
                ],
            );

            return $locked->fresh();
        }, 3);
    }
}
