<?php

namespace App\Services\Admin;

use App\Models\Booking;
use App\Models\Cinema;
use App\Models\Payment;
use App\Services\CinemaAccessService;
use App\Services\Reports\AuthoritativePaymentQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminPaymentQuery
{
    /** Columns permitted on the permission-protected payment detail query. */
    public const SAFE_COLUMNS = [
        'payments.id', 'payments.booking_id', 'payments.provider', 'payments.app_trans_id',
        'payments.order_code', 'payments.transaction_code', 'payments.amount', 'payments.currency', 'payments.status',
        'payments.transaction_status', 'payments.response_code', 'payments.transaction_id',
        'payments.provider_transaction_created_at', 'payments.provider_paid_at', 'payments.zp_trans_id',
        'payments.provider_return_code', 'payments.provider_sub_return_code', 'payments.callback_received_at',
        'payments.last_queried_at', 'payments.verified_at', 'payments.paid_at', 'payments.failed_at',
        'payments.settled_by_user_id', 'payments.settled_at', 'payments.failure_reason',
        'payments.expires_at', 'payments.reconcile_until', 'payments.created_at', 'payments.updated_at',
    ];

    public function __construct(
        private readonly CinemaAccessService $cinemaAccess,
        private readonly AuthoritativePaymentQuery $authoritativePayments,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'paid_at';
        $direction = $filters['direction'] ?? 'desc';
        $query = $this->successful($filters)->with([
            'booking:id,user_id,showtime_id,cinema_id,booking_code,customer_name,customer_email,customer_phone,total_amount,payment_status,booking_status,sales_channel',
            'booking.user:id,name,email',
            'booking.showtime:id,cinema_id',
            'booking.showtime.cinema:id,code,name',
            'settledBy:id,name,email',
        ]);

        if ($sort === 'paid_at') {
            $query->orderBy('successful_payments.finance_paid_at', $direction);
        } elseif ($sort === 'booking_code') {
            $query->orderBy(
                Booking::query()->select('booking_code')
                    ->whereColumn('bookings.id', 'payments.booking_id')->limit(1),
                $direction,
            );
        } else {
            $query->orderBy('payments.amount', $direction);
        }

        return $query->orderByDesc('payments.id')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();
    }

    /** @return array{total:int,revenue:int,online:int,counter:int} */
    public function summary(array $filters): array
    {
        $row = $this->successful($filters)
            ->reorder()
            ->select([])
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COALESCE(SUM(payments.amount), 0) AS revenue')
            ->selectRaw("SUM(CASE WHEN EXISTS (SELECT 1 FROM bookings summary_bookings WHERE summary_bookings.id = payments.booking_id AND summary_bookings.sales_channel = 'online') THEN 1 ELSE 0 END) AS online_count")
            ->selectRaw("SUM(CASE WHEN EXISTS (SELECT 1 FROM bookings summary_bookings WHERE summary_bookings.id = payments.booking_id AND summary_bookings.sales_channel = 'counter') THEN 1 ELSE 0 END) AS counter_count")
            ->toBase()
            ->first();

        return [
            'total' => (int) ($row?->total ?? 0),
            'revenue' => (int) ($row?->revenue ?? 0),
            'online' => (int) ($row?->online_count ?? 0),
            'counter' => (int) ($row?->counter_count ?? 0),
        ];
    }

    public function filterOptions(): array
    {
        $cinemas = Cinema::query()->active();
        $this->cinemaAccess->scope($cinemas, auth()->user(), 'cinemas.id');

        return ['cinemas' => $cinemas->orderBy('name')->get(['id', 'code', 'name'])];
    }

    private function successful(array $filters): Builder
    {
        $query = Payment::query()
            ->select([
                'payments.id', 'payments.booking_id', 'payments.provider', 'payments.amount', 'payments.currency',
                'payments.status', 'payments.verified_at', 'payments.settled_at', 'payments.settled_by_user_id',
                'payments.created_at', 'payments.updated_at',
                'successful_payments.finance_paid_at as successful_paid_at',
            ])
            ->joinSub(
                $this->authoritativePayments->authoritative(),
                'successful_payments',
                'successful_payments.payment_id',
                '=',
                'payments.id',
            )
            ->where('payments.status', Payment::STATUS_SUCCESS)
            ->whereHas('booking', function (Builder $booking): void {
                $booking->where('booking_status', 'paid')->where('payment_status', 'paid');
                $this->cinemaAccess->scope($booking, auth()->user(), 'bookings.cinema_id');
            });

        return $query
            ->when($filters['search'] ?? null, function (Builder $query, string $value): void {
                $like = '%'.$this->escapeLike($value).'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query->whereHas('booking', fn (Builder $booking) => $booking
                        ->where('booking_code', 'like', $like))
                        ->orWhere('payments.app_trans_id', 'like', $like)
                        ->orWhere('payments.order_code', 'like', $like)
                        ->orWhere('payments.transaction_code', 'like', $like)
                        ->orWhere('payments.transaction_id', 'like', $like)
                        ->orWhere('payments.zp_trans_id', 'like', $like);
                });
            })
            ->when($filters['cinema_id'] ?? null, fn (Builder $query, int|string $id) => $query
                ->whereHas('booking', fn (Builder $booking) => $booking->where('cinema_id', (int) $id)))
            ->when($filters['provider'] ?? null, fn (Builder $query, string $provider) => $query
                ->where('payments.provider', $provider))
            ->when($filters['sales_channel'] ?? null, fn (Builder $query, string $channel) => $query
                ->whereHas('booking', fn (Builder $booking) => $booking->where('sales_channel', $channel)))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query
                ->whereDate('successful_payments.finance_paid_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query
                ->whereDate('successful_payments.finance_paid_at', '<=', $date));
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
