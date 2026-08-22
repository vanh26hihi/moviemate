<?php

namespace App\Services\Seats;

use App\Models\Room;
use App\Models\Seat;
use App\Models\SeatIncident;
use App\Models\SeatIncidentImpact;
use App\Models\SeatIncidentSeat;
use App\Services\ActivityLogger;
use App\Services\BookingCancellationService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use LogicException;

final class SeatIncidentService
{
    public function __construct(
        private readonly SeatIncidentImpactQuery $impacts,
        private readonly SeatIncidentImpactClassifier $classifier,
        private readonly BookingCancellationService $cancellations,
        private readonly ActivityLogger $activities,
    ) {}

    public function lockUpcomingShowtimes(Room $room): void
    {
        $this->impacts->lockUpcomingShowtimes($room);
    }

    /** @param Collection<int, Seat> $seats */
    public function hasLockedImpacts(Room $room, Collection $seats): bool
    {
        return $this->impacts->get(
            $room,
            $seats->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            true,
        )->isNotEmpty();
    }

    /** @param Collection<int, Seat> $seats */
    public function createIfImpacted(
        Room $room,
        Collection $seats,
        string $reason,
        ?string $note,
    ): ?SeatIncident {
        if (! in_array($reason, SeatIncident::REASONS, true)
            || ($reason === SeatIncident::REASON_OTHER && trim((string) $note) === '')) {
            throw ValidationException::withMessages(['reason' => 'Lý do sự cố không hợp lệ.']);
        }

        $bookingSeats = $this->impacts->get($room, $seats->pluck('id')->map(fn ($id): int => (int) $id)->all(), true);
        if ($bookingSeats->isEmpty()) {
            return null;
        }

        $existing = SeatIncidentSeat::query()
            ->whereIn('seat_id', $seats->pluck('id'))
            ->where('active_lock_key', SeatIncidentSeat::ACTIVE_LOCK_KEY)
            ->exists();
        if ($existing) {
            throw ValidationException::withMessages(['status' => 'Ghế đã thuộc một sự cố đang mở.']);
        }

        $incident = SeatIncident::query()->create([
            'cinema_id' => $room->cinema_id,
            'room_id' => $room->id,
            'reported_by_user_id' => auth()->id(),
            'status' => SeatIncident::STATUS_OPEN,
            'reason' => $reason,
            'note' => $note,
        ]);
        foreach ($seats->sortBy('id') as $seat) {
            SeatIncidentSeat::query()->create([
                'seat_incident_id' => $incident->id,
                'seat_id' => $seat->id,
                'active_lock_key' => SeatIncidentSeat::ACTIVE_LOCK_KEY,
            ]);
        }

        $ordinaryBookingIds = [];
        foreach ($bookingSeats as $bookingSeat) {
            $classification = $this->classifier->classify($bookingSeat->booking);
            SeatIncidentImpact::query()->create([
                'seat_incident_id' => $incident->id,
                'booking_seat_id' => $bookingSeat->id,
                'detected_classification' => $classification,
                'resolution_status' => SeatIncidentImpact::RESOLUTION_UNRESOLVED,
                'detected_at' => now(),
            ]);
            if ($classification === SeatIncidentImpact::ORDINARY_HOLD) {
                $ordinaryBookingIds[(int) $bookingSeat->booking_id] = true;
            }
        }

        Seat::query()->whereIn('id', $seats->pluck('id'))->update([
            'status' => Seat::STATUS_MAINTENANCE,
            'updated_at' => now(),
        ]);

        foreach (array_keys($ordinaryBookingIds) as $bookingId) {
            $result = $this->cancellations->cancelForSeatIncident($bookingId, $incident->id);
            if (! $result->cancelled && ! $result->alreadyCancelled) {
                throw new LogicException('Ordinary incident hold changed classification while locked.');
            }

            SeatIncidentImpact::query()
                ->where('seat_incident_id', $incident->id)
                ->where('detected_classification', SeatIncidentImpact::ORDINARY_HOLD)
                ->whereHas('bookingSeat', fn ($query) => $query->where('booking_id', $bookingId))
                ->update([
                    'resolution_status' => SeatIncidentImpact::RESOLUTION_RESOLVED,
                    'resolved_at' => now(),
                    'resolution_reason' => 'whole_booking_cancelled',
                    'updated_at' => now(),
                ]);
        }

        $unresolved = SeatIncidentImpact::query()
            ->where('seat_incident_id', $incident->id)
            ->where('resolution_status', SeatIncidentImpact::RESOLUTION_UNRESOLVED)
            ->exists();
        if (! $unresolved) {
            $incident->forceFill(['status' => SeatIncident::STATUS_RESOLVED, 'resolved_at' => now()])->save();
            SeatIncidentSeat::query()->where('seat_incident_id', $incident->id)->update([
                'active_lock_key' => null,
                'updated_at' => now(),
            ]);
        }

        $this->activities->log('seat.incident_created', $incident, [], [
            'status' => $incident->status,
            'reason' => $incident->reason,
        ], [
            'cinema_id' => $room->cinema_id,
            'room_id' => $room->id,
            'seat_ids' => $seats->pluck('id')->all(),
            'impact_count' => $bookingSeats->count(),
            'ordinary_booking_count' => count($ordinaryBookingIds),
        ]);
        if (! $unresolved) {
            $this->activities->log('seat.incident_auto_resolved', $incident, ['status' => 'open'], ['status' => 'resolved']);
        }

        return $incident;
    }
}
