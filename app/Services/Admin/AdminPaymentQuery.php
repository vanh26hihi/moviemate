<?php

namespace App\Services\Admin;

use App\Models\Payment;
use App\Services\CinemaAccessService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminPaymentQuery
{
    public function __construct(private readonly CinemaAccessService $cinemaAccess) {}

    /** @var list<string> */
    public const SAFE_COLUMNS = [
        'payments.id', 'payments.booking_id', 'payments.provider', 'payments.app_trans_id',
        'payments.order_code', 'payments.transaction_code', 'payments.amount', 'payments.currency', 'payments.status',
        'payments.transaction_status', 'payments.response_code', 'payments.transaction_id',
        'payments.provider_transaction_created_at', 'payments.provider_paid_at', 'payments.zp_trans_id',
        'payments.provider_return_code', 'payments.provider_sub_return_code', 'payments.callback_received_at',
        'payments.last_queried_at', 'payments.verified_at', 'payments.paid_at', 'payments.failed_at',
        'payments.settled_by_user_id', 'payments.settled_at',
        'payments.failure_reason', 'payments.expires_at', 'payments.reconcile_until',
        'payments.created_at', 'payments.updated_at',
    ];

    public function paginate(array $filters): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        $query = Payment::query()
            ->select(self::SAFE_COLUMNS)
            ->with([
                'booking:id,booking_code,total_amount,payment_status,booking_status,paid_at',
                'booking.authoritativePayment' => fn ($query) => $query->select([
                    'payments.id', 'payments.booking_id', 'payments.provider', 'payments.status', 'payments.verified_at',
                    'payments.settled_by_user_id', 'payments.settled_at',
                ]),
            ])
            ->whereHas('booking', function (Builder $query): void {
                $this->cinemaAccess->scope($query, auth()->user(), 'bookings.cinema_id');
            })
            ->when($filters['booking_code'] ?? null, fn (Builder $query, string $value) => $query
                ->whereHas('booking', fn (Builder $query) => $query->where('booking_code', 'like', '%'.$this->escapeLike($value).'%')))
            ->when($filters['provider'] ?? null, fn (Builder $query, string $value) => $query->where('provider', $value))
            ->when($filters['reference'] ?? null, function (Builder $query, string $value): void {
                $like = '%'.$this->escapeLike($value).'%';
                $query->where(fn (Builder $query) => $query
                    ->where('app_trans_id', 'like', $like)
                    ->orWhere('order_code', 'like', $like)
                    ->orWhere('transaction_code', 'like', $like)
                    ->orWhere('transaction_id', 'like', $like)
                    ->orWhere('zp_trans_id', 'like', $like));
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $value) => $query->where('status', $value))
            ->when(($filters['verified'] ?? null) === 'yes', fn (Builder $query) => $this->authoritativeEvidence($query))
            ->when(($filters['verified'] ?? null) === 'no', fn (Builder $query) => $query->whereNot(fn (Builder $query) => $this->authoritativeEvidence($query)))
            ->when(($filters['review'] ?? null) === 'yes', fn (Builder $query) => $query->where('status', Payment::STATUS_REVIEW))
            ->when(($filters['review'] ?? null) === 'no', fn (Builder $query) => $query->where('status', '!=', Payment::STATUS_REVIEW))
            ->when($filters['created_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['created_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters['paid_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('paid_at', '>=', $date))
            ->when($filters['paid_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('paid_at', '<=', $date))
            ->when(isset($filters['amount_min']), fn (Builder $query) => $query->where('amount', '>=', (int) $filters['amount_min']))
            ->when(isset($filters['amount_max']), fn (Builder $query) => $query->where('amount', '<=', (int) $filters['amount_max']))
            ->when(($filters['amount_mismatch'] ?? null) === 'yes', fn (Builder $query) => $this->amountMismatch($query, true))
            ->when(($filters['amount_mismatch'] ?? null) === 'no', fn (Builder $query) => $this->amountMismatch($query, false))
            ->when(($filters['reconciled'] ?? null) === 'yes', fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->whereNotNull('last_queried_at')->orWhereNotNull('verified_at')))
            ->when(($filters['reconciled'] ?? null) === 'no', fn (Builder $query) => $query
                ->whereNull('last_queried_at')->whereNull('verified_at'));

        return $query->orderBy($sort, $direction)->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 25))->withQueryString();
    }

    private function amountMismatch(Builder $query, bool $mismatch): Builder
    {
        return $query->whereHas('booking', fn (Builder $booking) => $booking->whereColumn(
            'bookings.total_amount', $mismatch ? '!=' : '=', 'payments.amount',
        ));
    }

    private function authoritativeEvidence(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNotNull('verified_at')
                ->orWhere(function (Builder $counter): void {
                    $counter->where('provider', Payment::PROVIDER_COUNTER_CASH)
                        ->whereNotNull('settled_at')
                        ->whereNotNull('settled_by_user_id');
                });
        });
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
