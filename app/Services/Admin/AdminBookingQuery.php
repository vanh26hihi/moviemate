<?php

namespace App\Services\Admin;

use App\Models\Booking;
use App\Models\Cinema;
use App\Models\Showtime;
use App\Services\CinemaAccessService;
use App\Services\Reports\AuthoritativePaymentQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminBookingQuery
{
    public function __construct(
        private readonly CinemaAccessService $cinemaAccess,
        private readonly AuthoritativePaymentQuery $authoritativePayments,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'paid_at';
        $direction = $filters['direction'] ?? 'desc';
        $query = $this->successful($filters)->with([
            'user:id,name,email',
            'createdByStaff:id,name,email',
            'showtime:id,movie_id,cinema_id,room_id,show_date,show_time',
            'showtime.movie:id,title',
            'showtime.cinema:id,code,name',
            'showtime.room:id,code,name',
            'bookingSeats:id,booking_id,seat_id,price',
            'bookingSeats.seat:id,seat_code,type,pair_code,pair_position,row,number,x_position,y_position',
            'ticketDelivery:id,booking_id,status,attempts,sent_at,last_error_code,updated_at',
            'ticketPrint:id,booking_id,status,attempts_count,printed_at',
            'authoritativePayment' => fn ($query) => $query->select([
                'payments.id', 'payments.booking_id', 'payments.provider', 'payments.amount',
                'payments.status', 'payments.verified_at', 'payments.settled_at',
                'payments.settled_by_user_id',
            ]),
        ]);

        if ($sort === 'show_date') {
            $query->orderBy(
                Showtime::query()->select('show_date')->whereColumn('showtimes.id', 'bookings.showtime_id')->limit(1),
                $direction,
            );
        } elseif ($sort === 'paid_at') {
            $query->orderBy('successful_payments.finance_paid_at', $direction);
        } else {
            $query->orderBy('bookings.'.$sort, $direction);
        }

        return $query->orderByDesc('bookings.id')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();
    }

    /** @return array{total:int,revenue:int,seats:int} */
    public function summary(array $filters): array
    {
        $row = $this->successful($filters)
            ->reorder()
            ->select([])
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COALESCE(SUM(successful_payments.amount), 0) AS revenue')
            ->selectRaw('COALESCE(SUM((SELECT COUNT(*) FROM booking_seats WHERE booking_seats.booking_id = bookings.id)), 0) AS seat_count')
            ->toBase()
            ->first();

        return [
            'total' => (int) ($row?->total ?? 0),
            'revenue' => (int) ($row?->revenue ?? 0),
            'seats' => (int) ($row?->seat_count ?? 0),
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
        $query = Booking::query()
            ->select([
                'bookings.*',
                'successful_payments.provider as successful_provider',
                'successful_payments.finance_paid_at as successful_paid_at',
            ])
            ->joinSub(
                $this->authoritativePayments->authoritative(),
                'successful_payments',
                'successful_payments.booking_id',
                '=',
                'bookings.id',
            )
            ->where('bookings.booking_status', 'paid')
            ->where('bookings.payment_status', 'paid');

        $this->cinemaAccess->scope($query, auth()->user(), 'bookings.cinema_id');

        return $query
            ->when($filters['search'] ?? null, function (Builder $query, string $value): void {
                $like = '%'.$this->escapeLike($value).'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query->where('bookings.booking_code', 'like', $like)
                        ->orWhere('bookings.customer_name', 'like', $like)
                        ->orWhere('bookings.customer_email', 'like', $like)
                        ->orWhere('bookings.customer_phone', 'like', $like)
                        ->orWhereHas('user', fn (Builder $user) => $user
                            ->where('name', 'like', $like)->orWhere('email', 'like', $like));
                });
            })
            ->when($filters['cinema_id'] ?? null, fn (Builder $query, int|string $id) => $query
                ->where('bookings.cinema_id', (int) $id))
            ->when($filters['sales_channel'] ?? null, fn (Builder $query, string $channel) => $query
                ->where('bookings.sales_channel', $channel))
            ->when(($filters['ticket_status'] ?? null) === 'none', fn (Builder $query) => $query->whereDoesntHave('ticketDelivery'))
            ->when(($filters['ticket_status'] ?? null) && ($filters['ticket_status'] ?? null) !== 'none', fn (Builder $query) => $query
                ->whereHas('ticketDelivery', fn (Builder $delivery) => $delivery->where('status', $filters['ticket_status'])))
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
