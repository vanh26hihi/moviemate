<?php

namespace App\Services\Admin;

use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\PaymentReviewEvent;
use App\Support\PrivacyMask;

final class AdminPaymentDetailService
{
    public function get(Payment $payment, bool $canViewActivity): array
    {
        $payment->load([
            'booking:id,user_id,showtime_id,booking_code,customer_email,total_amount,payment_status,booking_status,paid_at,created_at',
            'booking.user:id,name,email',
            'booking.showtime:id,movie_id,room_id,show_date,show_time',
            'booking.showtime.movie:id,title',
            'booking.showtime.room:id,code,name',
            'booking.authoritativePayment' => fn ($query) => $query->select([
                'payments.id', 'payments.booking_id', 'payments.provider', 'payments.status', 'payments.verified_at',
            ]),
        ]);

        $attemptRows = Payment::query()->select(AdminPaymentQuery::SAFE_COLUMNS)
            ->where('booking_id', $payment->booking_id)->latest('id')->limit(101)->get();
        $attemptsTruncated = $attemptRows->count() > 100;
        $attempts = $attemptRows->take(100);
        $reviewEvents = PaymentReviewEvent::query()->with('actor:id,name')
            ->where('payment_id', $payment->id)->latest('id')->limit(50)->get();
        $activityLogs = $canViewActivity
            ? ActivityLog::query()->with('actor:id,name')->where('subject_type', $payment->getMorphClass())
                ->where('subject_id', (string) $payment->id)->latest('id')->limit(50)->get()
            : collect();

        return [
            'payment' => $payment,
            'attempts' => $attempts,
            'attemptsTruncated' => $attemptsTruncated,
            'reviewEvents' => $reviewEvents,
            'activityLogs' => $activityLogs,
            'isAuthoritative' => $payment->booking?->authoritativePayment?->id === $payment->id,
            'customerNameMasked' => PrivacyMask::name($payment->booking?->user?->name),
            'recipientEmailMasked' => PrivacyMask::email($payment->booking?->recipient_email),
            'evidence' => $this->evidence($payment),
        ];
    }

    /** @return array<string, array{state:string,label:string}> */
    private function evidence(Payment $payment): array
    {
        $verified = $payment->status === Payment::STATUS_SUCCESS && $payment->verified_at !== null;
        $reason = strtolower((string) $payment->failure_reason);
        $booking = $payment->booking;

        return [
            'provider' => $this->state(
                $verified ? 'yes' : 'unknown',
                $verified ? 'Provider đã được xác thực trong luồng xác minh' : 'Chưa có bằng chứng provider được chấp nhận',
            ),
            'amount' => $this->state(
                str_contains($reason, 'amount') ? 'no' : ($verified ? 'yes' : 'unknown'),
                str_contains($reason, 'amount') ? 'Không khớp' : ($verified ? 'Đã khớp' : 'Chưa xác định'),
            ),
            'booking' => $this->state(
                str_contains($reason, 'identity') || str_contains($reason, 'reference') ? 'no' : ($verified ? 'yes' : 'unknown'),
                str_contains($reason, 'identity') || str_contains($reason, 'reference') ? 'Không khớp' : ($verified ? 'Đã khớp' : 'Chưa xác định'),
            ),
            'transaction' => $this->state(
                str_contains($reason, 'duplicate') ? 'no' : ($verified ? 'yes' : 'unknown'),
                str_contains($reason, 'duplicate') ? 'Trùng mã giao dịch' : ($verified ? 'Đã kiểm tra tính duy nhất' : 'Chưa xác định'),
            ),
            'result' => $this->state(
                $verified ? 'yes' : 'unknown',
                $verified ? 'Kết quả provider đã được chấp nhận' : 'Chưa có kết quả được chấp nhận',
            ),
            'finalization' => $this->state(
                $verified && $booking && in_array($booking->booking_status, ['paid', 'used'], true) && $booking->payment_status === 'paid' ? 'yes' : 'unknown',
                $verified && $booking && in_array($booking->booking_status, ['paid', 'used'], true) && $booking->payment_status === 'paid'
                    ? 'Đơn đã hoàn tất nhất quán' : 'Chưa có bằng chứng hoàn tất nhất quán',
            ),
        ];
    }

    /** @return array{state:string,label:string} */
    private function state(string $state, string $label): array
    {
        return compact('state', 'label');
    }
}
