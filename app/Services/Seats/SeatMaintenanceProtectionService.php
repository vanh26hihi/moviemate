<?php

namespace App\Services\Seats;

use App\Models\BookingSeat;
use Illuminate\Support\Facades\DB;

final class SeatMaintenanceProtectionService
{
    /**
     * @param  list<int>  $seatIds
     * @return array<int, array{active_hold: bool, future_sold: bool, issued_ticket: bool, protected: bool}>
     */
    public function summaries(array $seatIds): array
    {
        $ids = collect($seatIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values();
        $empty = $ids->mapWithKeys(fn (int $id): array => [$id => [
            'active_hold' => false,
            'future_sold' => false,
            'issued_ticket' => false,
            'protected' => false,
        ]])->all();
        if ($ids->isEmpty()) {
            return $empty;
        }

        $now = now();
        $date = $now->toDateString();
        $time = $now->format('H:i:s');
        $futureSql = '(showtimes.show_date > ? OR (showtimes.show_date = ? AND showtimes.show_time >= ?))';
        $rows = DB::table('booking_seats')
            ->join('bookings', 'bookings.id', '=', 'booking_seats.booking_id')
            ->join('showtimes', 'showtimes.id', '=', 'booking_seats.showtime_id')
            ->leftJoin('booking_ticket_deliveries', 'booking_ticket_deliveries.booking_id', '=', 'bookings.id')
            ->whereIn('booking_seats.seat_id', $ids)
            ->groupBy('booking_seats.seat_id')
            ->select('booking_seats.seat_id')
            ->selectRaw(
                "MAX(CASE WHEN booking_seats.active_lock_key = ? AND bookings.booking_status = 'pending_payment' AND bookings.expires_at > ? THEN 1 ELSE 0 END) AS active_hold",
                [BookingSeat::ACTIVE_LOCK_KEY, $now],
            )
            ->selectRaw(
                "MAX(CASE WHEN bookings.booking_status IN ('paid', 'used') AND {$futureSql} THEN 1 ELSE 0 END) AS future_sold",
                [$date, $date, $time],
            )
            ->selectRaw(
                "MAX(CASE WHEN bookings.booking_status IN ('paid', 'used') AND {$futureSql} AND (bookings.ticket_emailed_at IS NOT NULL OR booking_ticket_deliveries.status = 'sent') THEN 1 ELSE 0 END) AS issued_ticket",
                [$date, $date, $time],
            )
            ->get();

        foreach ($rows as $row) {
            $summary = [
                'active_hold' => (bool) $row->active_hold,
                'future_sold' => (bool) $row->future_sold,
                'issued_ticket' => (bool) $row->issued_ticket,
            ];
            $empty[(int) $row->seat_id] = $summary + [
                'protected' => $summary['active_hold'] || $summary['future_sold'] || $summary['issued_ticket'],
            ];
        }

        return $empty;
    }
}
