<?php

namespace App\Services\Admin;

use App\Models\Booking;
use App\Models\Movie;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Showtime;
use App\Services\CinemaAccessService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminBookingQuery
{
    /**
     * Booking states that always represent real business activity and therefore stay in the
     * default operational list.
     *
     * @var list<string>
     */
    private const OPERATIONAL_BOOKING_STATUSES = ['paid', 'used'];

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
                'ticketPrint:id,booking_id,status,attempts_count,printed_by_user_id,printed_at',
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

        $this->applyDraftVisibility($query, $filters);

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

    /**
     * Hide purely technical checkout drafts from the default operational list.
     *
     * Nothing is deleted: the rows remain for concurrency and payment idempotency and can be
     * shown again with the explicit "Hiển thị đơn tạm và đơn hết hạn" filter. A draft is only
     * hidden when it never reached a meaningful payment state, so unresolved/review cases that
     * need action are always visible.
     */
    private function applyDraftVisibility(Builder $query, array $filters): void
    {
        if (filter_var($filters['include_drafts'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        // An explicit status filter is an intentional operator choice; do not override it.
        if (($filters['booking_status'] ?? null) !== null || ($filters['payment_status'] ?? null) !== null) {
            return;
        }

        $query->where(function (Builder $query): void {
            $query->whereIn('booking_status', self::OPERATIONAL_BOOKING_STATUSES)
                ->orWhereIn('payment_status', ['paid', 'refunded'])
                ->orWhereHas('payments', fn (Builder $payments) => $payments->whereIn('status', [
                    Payment::STATUS_SUCCESS,
                    Payment::STATUS_REVIEW,
                    Payment::STATUS_UNRESOLVED,
                    Payment::STATUS_PROCESSING,
                ]))
                ->orWhere(function (Builder $cancelled): void {
                    $cancelled->where('booking_status', 'cancelled')
                        ->whereHas('payments', function (Builder $payments): void {
                            $payments->where(function (Builder $evidence): void {
                                $evidence->whereNotNull('verified_at')
                                    ->orWhereNotNull('callback_received_at')
                                    ->orWhereNotNull('transaction_id')
                                    ->orWhereNotNull('zp_trans_id')
                                    ->orWhereNotNull('provider_return_code')
                                    ->orWhereNotNull('query_response_hash');
                            });
                        });
                });
        });
    }
}
