<?php

namespace App\Services\Admin;

use App\Models\Payment;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class PaymentReconciliationQuery
{
    private const CACHE_KEY = 'admin:payment-reconciliation:badge:v1';

    public function __construct(private readonly CacheRepository $cache) {}

    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return $this->attentionQuery()
            ->select(AdminPaymentQuery::SAFE_COLUMNS)
            ->with('booking:id,booking_code,total_amount,payment_status,booking_status')
            ->selectRaw($this->prioritySql().' as reconciliation_priority')
            ->selectRaw($this->reasonSql().' as reconciliation_reason')
            ->orderByRaw("CASE reconciliation_priority WHEN 'Khẩn cấp' THEN 1 WHEN 'Cao' THEN 2 ELSE 3 END")
            ->orderBy('payments.created_at')
            ->orderBy('payments.id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function badgeLabel(): ?string
    {
        $count = app()->environment('testing')
            ? $this->attentionQuery()->count()
            : $this->cache->remember(self::CACHE_KEY, now()->addMinute(), fn () => $this->attentionQuery()->count());

        return $count === 0 ? null : ($count > 99 ? '99+' : (string) $count);
    }

    public function forgetBadge(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    public function attentionQuery(): Builder
    {
        $stale = now()->subMinutes(15);

        return Payment::query()
            ->join('bookings', 'bookings.id', '=', 'payments.booking_id')
            ->where(function (Builder $query) use ($stale): void {
                $query->whereColumn('payments.amount', '!=', 'bookings.total_amount')
                    ->orWhere(fn (Builder $query) => $query->where('payments.status', Payment::STATUS_SUCCESS)->whereNull('payments.verified_at'))
                    ->orWhere(fn (Builder $query) => $query->where('payments.status', Payment::STATUS_SUCCESS)->whereNotNull('payments.verified_at')
                        ->where(fn (Builder $query) => $query->where('bookings.payment_status', '!=', 'paid')->orWhereNotIn('bookings.booking_status', ['paid', 'used'])))
                    ->orWhere(fn (Builder $query) => $query->whereIn('payments.status', [Payment::STATUS_REVIEW, Payment::STATUS_UNRESOLVED]))
                    ->orWhere(fn (Builder $query) => $query->where('payments.status', Payment::STATUS_FAILED)
                        ->where('payments.failure_reason', 'query_failed'))
                    ->orWhere(fn (Builder $query) => $query->whereIn('payments.status', Payment::RECONCILABLE_STATUSES)
                        ->where(fn (Builder $query) => $query->where('payments.reconcile_until', '<=', now())
                            ->orWhere(fn (Builder $query) => $query->whereNull('payments.last_queried_at')->where('payments.created_at', '<=', now()->subMinutes(5)))
                            ->orWhere('payments.last_queried_at', '<=', $stale)))
                    ->orWhere(function (Builder $query): void {
                        $query->where('bookings.payment_status', 'paid')
                            ->whereNotExists(function ($subquery): void {
                                $subquery->selectRaw('1')->from('payments as verified_payments')
                                    ->whereColumn('verified_payments.booking_id', 'bookings.id')
                                    ->where('verified_payments.status', Payment::STATUS_SUCCESS)
                                    ->whereNotNull('verified_payments.verified_at');
                            });
                    });
            });
    }

    private function prioritySql(): string
    {
        return "CASE
            WHEN payments.amount <> bookings.total_amount THEN 'Khẩn cấp'
            WHEN payments.status = 'success' AND payments.verified_at IS NULL THEN 'Khẩn cấp'
            WHEN payments.status = 'success' AND payments.verified_at IS NOT NULL
                AND (bookings.payment_status <> 'paid' OR bookings.booking_status NOT IN ('paid', 'used')) THEN 'Khẩn cấp'
            WHEN bookings.payment_status = 'paid' AND NOT EXISTS (
                SELECT 1 FROM payments verified_payments
                WHERE verified_payments.booking_id = bookings.id
                  AND verified_payments.status = 'success' AND verified_payments.verified_at IS NOT NULL
            ) THEN 'Khẩn cấp'
            WHEN payments.status = 'failed' AND payments.failure_reason = 'query_failed' THEN 'Cao'
            WHEN payments.status IN ('review', 'unresolved') OR payments.reconcile_until <= CURRENT_TIMESTAMP THEN 'Cao'
            ELSE 'Bình thường' END";
    }

    private function reasonSql(): string
    {
        return "CASE
            WHEN payments.amount <> bookings.total_amount THEN 'Số tiền giao dịch không khớp tổng đơn'
            WHEN payments.status = 'success' AND payments.verified_at IS NULL THEN 'Trạng thái thành công chưa có bằng chứng xác minh'
            WHEN payments.status = 'success' AND payments.verified_at IS NOT NULL
                AND bookings.payment_status = 'refunded' THEN 'Đơn đã hoàn tiền; chỉ kiểm tra lịch sử, không tái kích hoạt'
            WHEN payments.status = 'success' AND payments.verified_at IS NOT NULL
                AND (bookings.payment_status <> 'paid' OR bookings.booking_status NOT IN ('paid', 'used')) THEN 'Giao dịch đã xác minh nhưng đơn chưa đồng bộ'
            WHEN bookings.payment_status = 'paid' AND NOT EXISTS (
                SELECT 1 FROM payments verified_payments
                WHERE verified_payments.booking_id = bookings.id
                  AND verified_payments.status = 'success' AND verified_payments.verified_at IS NOT NULL
            ) THEN 'Đơn ghi nhận đã thanh toán nhưng thiếu giao dịch có thẩm quyền'
            WHEN payments.status = 'review' THEN 'Giao dịch đang chờ kiểm tra'
            WHEN payments.status = 'unresolved' THEN 'Kết quả provider chưa xác định'
            WHEN payments.status = 'failed' AND payments.failure_reason = 'query_failed' THEN 'Provider xác nhận giao dịch thất bại qua truy vấn'
            WHEN payments.reconcile_until <= CURRENT_TIMESTAMP THEN 'Đã quá thời hạn đối soát'
            ELSE 'Giao dịch chờ provider đã lâu' END";
    }
}
