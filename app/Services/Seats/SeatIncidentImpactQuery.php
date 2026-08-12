<?php

namespace App\Services\Seats;

use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Showtime;
use App\Services\ShowtimeLifecycleService;
use Illuminate\Support\Collection;

final class SeatIncidentImpactQuery
{
    public function __construct(
        private readonly ShowtimeLifecycleService $lifecycle,
    ) {}

    /** @param list<int> $seatIds @return Collection<int, BookingSeat> */
    public function get(Room $room, array $seatIds, bool $lock = false): Collection
    {
        $showtimeIds = $this->upcomingShowtimeIds($room, $lock);
        if ($showtimeIds->isEmpty()) {
            return collect();
        }

        $base = BookingSeat::query()
            ->whereIn('showtime_id', $showtimeIds)
            ->whereIn('seat_id', $seatIds)
            ->where('active_lock_key', BookingSeat::ACTIVE_LOCK_KEY)
            ->whereExists(fn ($query) => $query->selectRaw('1')
                ->from('room_layout_cells')
                ->whereColumn('room_layout_cells.room_layout_id', 'showtimes_layout.room_layout_id')
                ->whereColumn('room_layout_cells.seat_id', 'booking_seats.seat_id'));

        // Bind the showtime layout explicitly so an old published layout remains authoritative.
        $base->join('showtimes as showtimes_layout', 'showtimes_layout.id', '=', 'booking_seats.showtime_id')
            ->select('booking_seats.*');

        if (! $lock) {
            return $base->with($this->relations())->orderBy('booking_seats.id')->get();
        }

        $bookingIds = (clone $base)->orderBy('booking_seats.booking_id')
            ->pluck('booking_seats.booking_id')->unique()->values();
        if ($bookingIds->isEmpty()) {
            return collect();
        }

        Booking::query()->whereIn('id', $bookingIds)->orderBy('id')->lockForUpdate()->get(['id']);
        Payment::query()->whereIn('booking_id', $bookingIds)->orderBy('booking_id')->orderBy('id')->lockForUpdate()->get(['id']);

        return $base->orderBy('booking_seats.id')->lockForUpdate()->with($this->relations())->get();
    }

    public function lockUpcomingShowtimes(Room $room): void
    {
        $this->upcomingShowtimeIds($room, true);
    }

    /** @return Collection<int, int> */
    private function upcomingShowtimeIds(Room $room, bool $lock): Collection
    {
        $query = Showtime::query()->where('showtimes.room_id', $room->id);
        $this->lifecycle->applyFilter($query, ShowtimeLifecycleService::UPCOMING);
        $ids = $query->orderBy('showtimes.id')->pluck('showtimes.id');

        if ($lock && $ids->isNotEmpty()) {
            Showtime::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get(['id']);
        }

        return $ids->map(fn ($id): int => (int) $id)->values();
    }

    /** @return array<string, mixed> */
    private function relations(): array
    {
        return [
            'seat:id,seat_code,type,pair_code,pair_position,row,number',
            'showtime.movie', 'showtime.room.cinema',
            'booking.user', 'booking.payments', 'booking.ticketDelivery',
            'admissionTicket.printState',
        ];
    }
}
