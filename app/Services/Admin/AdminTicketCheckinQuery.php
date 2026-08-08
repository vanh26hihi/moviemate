<?php

namespace App\Services\Admin;

use App\Models\TicketCheckinEvent;
use App\Services\CinemaAccessService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class AdminTicketCheckinQuery
{
    public function __construct(private readonly CinemaAccessService $cinemaAccess) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = TicketCheckinEvent::query()
            ->select([
                'id', 'booking_id', 'showtime_id', 'actor_user_id', 'actor_role_snapshot',
                'result', 'reason_code', 'scanned_at', 'request_id', 'route_name',
                'safe_ip_hash', 'user_agent_summary', 'context', 'created_at',
            ])
            ->with([
                'actor:id,name',
                'booking:id,booking_code,customer_email,user_id,showtime_id,booking_status,payment_status,used_at',
                'booking.bookingSeats.seat',
                'showtime:id,movie_id,room_id,show_date,show_time',
                'showtime.movie:id,title',
                'showtime.room:id,code,name',
            ])
            ->whereHas('booking', function (Builder $query): void {
                $this->cinemaAccess->scope($query, auth()->user(), 'bookings.cinema_id');
            })
            ->when($filters['booking_code'] ?? null, fn (Builder $query, string $value) => $query
                ->whereHas('booking', fn (Builder $booking) => $booking->where('booking_code', 'like', '%'.$this->escapeLike($value).'%')))
            ->when($filters['movie'] ?? null, fn (Builder $query, string $value) => $query
                ->whereHas('showtime.movie', fn (Builder $movie) => $movie->where('title', 'like', '%'.$this->escapeLike($value).'%')))
            ->when($filters['room'] ?? null, fn (Builder $query, string $value) => $query
                ->whereHas('showtime.room', fn (Builder $room) => $room->where(fn (Builder $room) => $room
                    ->where('code', 'like', '%'.$this->escapeLike($value).'%')
                    ->orWhere('name', 'like', '%'.$this->escapeLike($value).'%'))))
            ->when($filters['showtime_id'] ?? null, fn (Builder $query, int $id) => $query->where('showtime_id', $id))
            ->when($filters['actor'] ?? null, fn (Builder $query, string $value) => $query
                ->whereHas('actor', fn (Builder $actor) => $actor->where('name', 'like', '%'.$this->escapeLike($value).'%')))
            ->when($filters['result'] ?? null, fn (Builder $query, string $result) => $query->where('result', $result))
            ->when($filters['reason'] ?? null, fn (Builder $query, string $reason) => $query->where('reason_code', $reason))
            ->when($filters['scanned_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('scanned_at', '>=', $date))
            ->when($filters['scanned_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('scanned_at', '<=', $date))
            ->when(($filters['duplicates_only'] ?? null) === 'yes', fn (Builder $query) => $query->where('result', TicketCheckinEvent::RESULT_ALREADY_USED))
            ->when(($filters['rejected_only'] ?? null) === 'yes', fn (Builder $query) => $query->whereNotIn('result', [
                TicketCheckinEvent::RESULT_ACCEPTED,
                TicketCheckinEvent::RESULT_ALREADY_USED,
            ]));

        return $query->orderBy($filters['sort'] ?? 'scanned_at', $filters['direction'] ?? 'desc')
            ->orderByDesc('id')->paginate((int) ($filters['per_page'] ?? 25))->withQueryString();
    }

    private function escapeLike(string $value): string
    {
        return addcslashes(trim($value), '%_\\');
    }
}
