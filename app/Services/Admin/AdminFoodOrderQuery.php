<?php

namespace App\Services\Admin;

use App\Models\Order;
use App\Services\CinemaAccessService;
use App\Services\Reports\AuthoritativePaymentQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminFoodOrderQuery
{
    private const SUCCESSFUL_STATUSES = ['paid', 'completed'];

    public function __construct(
        private readonly CinemaAccessService $cinemaAccess,
        private readonly AuthoritativePaymentQuery $authoritativePayments,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'paid_at';
        $direction = $filters['direction'] ?? 'desc';
        $query = $this->successful($filters)->with($this->relations());

        match ($sort) {
            'total_amount' => $query->orderBy('orders.total_amount', $direction),
            'booking_code' => $query->orderBy('bookings.booking_code', $direction),
            default => $query->orderBy('successful_payments.finance_paid_at', $direction),
        };

        return $query->orderByDesc('orders.id')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();
    }

    /** @return array{total:int,item_quantity:int,revenue:int,unprinted:int} */
    public function summary(array $filters): array
    {
        $row = $this->successful($filters)
            ->reorder()
            ->select([])
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COALESCE(SUM(orders.total_amount), 0) AS revenue')
            ->selectRaw('COALESCE(SUM((SELECT COALESCE(SUM(order_items.quantity), 0) FROM order_items WHERE order_items.order_id = orders.id)), 0) AS item_quantity')
            ->selectRaw('COALESCE(SUM(CASE WHEN EXISTS (SELECT 1 FROM food_pickup_vouchers WHERE food_pickup_vouchers.booking_id = orders.booking_id AND food_pickup_vouchers.print_count = 0) THEN 1 ELSE 0 END), 0) AS unprinted')
            ->toBase()
            ->first();

        return [
            'total' => (int) ($row?->total ?? 0),
            'item_quantity' => (int) ($row?->item_quantity ?? 0),
            'revenue' => (int) ($row?->revenue ?? 0),
            'unprinted' => (int) ($row?->unprinted ?? 0),
        ];
    }

    public function findSuccessful(int $orderId): Order
    {
        return $this->successful([])
            ->with($this->relations())
            ->where('orders.id', $orderId)
            ->firstOrFail();
    }

    private function successful(array $filters): Builder
    {
        $query = Order::query()
            ->select([
                'orders.*',
                'successful_payments.payment_id as successful_payment_id',
                'successful_payments.provider as successful_provider',
                'successful_payments.amount as successful_payment_amount',
                'successful_payments.currency as successful_payment_currency',
                'successful_payments.finance_paid_at as successful_paid_at',
            ])
            ->join('bookings', 'bookings.id', '=', 'orders.booking_id')
            ->joinSub(
                $this->authoritativePayments->authoritative(),
                'successful_payments',
                'successful_payments.booking_id',
                '=',
                'bookings.id',
            )
            ->whereIn('orders.status', self::SUCCESSFUL_STATUSES)
            ->where('bookings.booking_status', 'paid')
            ->where('bookings.payment_status', 'paid')
            ->whereColumn('orders.pickup_cinema_id', 'bookings.cinema_id')
            ->whereHas('items');

        $this->cinemaAccess->scope($query, auth()->user(), 'bookings.cinema_id');

        return $query
            ->when($filters['search'] ?? null, function (Builder $query, string $value): void {
                $like = '%'.$this->escapeLike($value).'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query->where('bookings.booking_code', 'like', $like)
                        ->orWhere('orders.customer_name', 'like', $like)
                        ->orWhere('orders.customer_phone', 'like', $like)
                        ->orWhere('orders.customer_email', 'like', $like)
                        ->orWhereHas('booking.foodPickupVoucher', fn (Builder $voucher) => $voucher
                            ->where('voucher_code', 'like', $like));
                });
            })
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query
                ->whereDate('successful_payments.finance_paid_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query
                ->whereDate('successful_payments.finance_paid_at', '<=', $date))
            ->when(($filters['voucher_status'] ?? null) === 'unprinted', fn (Builder $query) => $query
                ->whereHas('booking.foodPickupVoucher', fn (Builder $voucher) => $voucher->where('print_count', 0)))
            ->when(($filters['voucher_status'] ?? null) === 'printed', fn (Builder $query) => $query
                ->whereHas('booking.foodPickupVoucher', fn (Builder $voucher) => $voucher->where('print_count', '>', 0)))
            ->when(($filters['voucher_status'] ?? null) === 'missing', fn (Builder $query) => $query
                ->whereDoesntHave('booking.foodPickupVoucher'));
    }

    private function relations(): array
    {
        return [
            'items' => fn ($query) => $query->orderBy('id'),
            'items.food:id,name',
            'pickupCinema:id,code,name,address',
            'booking:id,user_id,showtime_id,cinema_id,booking_code,customer_name,customer_phone,customer_email,sales_channel,booking_status,payment_status',
            'booking.user:id,name,email',
            'booking.showtime:id,movie_id,cinema_id,room_id,show_date,show_time',
            'booking.showtime.movie:id,title',
            'booking.showtime.room:id,code,name',
            'booking.foodPickupVoucher:id,booking_id,voucher_code,print_count,last_printed_at,last_printed_by_user_id',
            'booking.foodPickupVoucher.lastPrintedBy:id,name',
        ];
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
