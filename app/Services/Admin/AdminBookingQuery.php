<?php

namespace App\Services\Admin;

use App\Models\Booking;
use App\Models\Movie;
use App\Models\Room;
use App\Models\Showtime;
use App\Services\CinemaAccessService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminBookingQuery
{
    public function __construct(private readonly CinemaAccessService $cinemaAccess) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        $query = Booking::query()
            ->with([
                'user:id,name,email',
                'showtime:id,movie_id,room_id,show_date,show_time',
                'showtime.movie:id,title',
                'showtime.room:id,code,name',
                'bookingSeats:id,booking_id,seat_id,price',
                'bookingSeats.seat:id,seat_code,type,pair_code,pair_position,row,number,x_position,y_position',
                'ticketDelivery:id,booking_id,status,attempts,sent_at',
                'authoritativePayment' => fn ($query) => $query->select([
                    'payments.id', 'payments.booking_id', 'payments.provider', 'payments.amount',
                    'payments.status', 'payments.verified_at', 'payments.paid_at',
                ]),
            ]);
        $this->cinemaAccess->scope($query, auth()->user(), 'bookings.cinema_id');
        $query
            ->when($filters['booking_code'] ?? null, fn (Builder $query, string $value) => $query
                ->where('booking_code', 'like', '%'.$this->escapeLike($value).'%'))
            ->when($filters['customer_name'] ?? null, fn (Builder $query, string $value) => $query
                ->where(function (Builder $query) use ($value): void {
                    $like = '%'.$this->escapeLike($value).'%';
                    $query->whereHas('user', fn (Builder $query) => $query->where('name', 'like', $like))
                        ->orWhereHas('foodOrder', fn (Builder $query) => $query->where('customer_name', 'like', $like));
                }))
            ->when($filters['customer_email'] ?? null, fn (Builder $query, string $value) => $query
                ->where(function (Builder $query) use ($value): void {
                    $like = '%'.$this->escapeLike($value).'%';
                    $query->where('customer_email', 'like', $like)
                        ->orWhereHas('user', fn (Builder $query) => $query->where('email', 'like', $like))
                        ->orWhereHas('foodOrder', fn (Builder $query) => $query->where('customer_email', 'like', $like));
                }))
            ->when($filters['movie_id'] ?? null, fn (Builder $query, int|string $id) => $query
                ->whereHas('showtime', fn (Builder $query) => $query->where('movie_id', (int) $id)))
            ->when($filters['room_id'] ?? null, fn (Builder $query, int|string $id) => $query
                ->whereHas('showtime', fn (Builder $query) => $query->where('room_id', (int) $id)))
            ->when($filters['show_date'] ?? null, fn (Builder $query, string $date) => $query
                ->whereHas('showtime', fn (Builder $query) => $query->whereDate('show_date', $date)))
            ->when($filters['created_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['created_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters['booking_status'] ?? null, fn (Builder $query, string $status) => $query->where('booking_status', $status))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, string $status) => $query->where('payment_status', $status))
            ->when($filters['ticket_status'] ?? null, fn (Builder $query, string $status) => $query
                ->whereHas('ticketDelivery', fn (Builder $query) => $query->where('status', $status)))
            ->when(($filters['checkin_status'] ?? null) === 'used', fn (Builder $query) => $query->where('booking_status', 'used'))
            ->when(($filters['checkin_status'] ?? null) === 'not_used', fn (Builder $query) => $query->where('booking_status', '!=', 'used'));

        if ($sort === 'show_date') {
            $query->orderBy(
                Showtime::query()->select('show_date')->whereColumn('showtimes.id', 'bookings.showtime_id')->limit(1),
                $direction,
            );
        } else {
            $query->orderBy($sort, $direction);
        }

        return $query->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();
    }

    public function filterOptions(): array
    {
        return [
            'movies' => Movie::query()->orderBy('title')->get(['id', 'title']),
            'rooms' => tap(Room::query(), fn (Builder $query) => $this->cinemaAccess->scope($query, auth()->user(), 'rooms.cinema_id'))
                ->orderBy('code')->get(['id', 'code', 'name', 'cinema_id']),
        ];
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
