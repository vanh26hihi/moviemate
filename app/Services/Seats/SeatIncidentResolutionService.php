<?php

namespace App\Services\Seats;

use App\Mail\SeatRelocationMail;
use App\Models\Booking;
use App\Models\BookingSeat;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Seat;
use App\Models\SeatIncident;
use App\Models\SeatIncidentImpact;
use App\Models\SeatIncidentResolution;
use App\Models\SeatIncidentSeat;
use App\Models\Showtime;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CinemaAccessService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class SeatIncidentResolutionService
{
    public function __construct(
        private readonly CinemaAccessService $cinemaAccess,
        private readonly SeatIncidentImpactClassifier $classifier,
        private readonly SeatRelocationCandidateService $candidates,
        private readonly ActivityLogger $activities,
    ) {}

    /** @return Collection<int, SeatIncidentResolution> */
    public function relocate(SeatIncident $incident, SeatIncidentImpact $impact, int $replacementSeatId, User $actor): Collection
    {
        try {
            $notification = DB::transaction(function () use ($incident, $impact, $replacementSeatId, $actor): array {
                $initial = SeatIncidentImpact::query()->with('bookingSeat.seat')->findOrFail($impact->id);
                abort_unless((int) $initial->seat_incident_id === (int) $incident->id, 404);
                $room = Room::query()->whereKey($incident->room_id)->lockForUpdate()->firstOrFail();
                $this->cinemaAccess->authorizeCinema($actor, (int) $room->cinema_id);

                $showtime = Showtime::query()->whereKey($initial->bookingSeat->showtime_id)->lockForUpdate()->firstOrFail();
                $requested = Seat::query()->whereKey($replacementSeatId)->where('room_id', $room->id)->firstOrFail();
                $targetIds = $requested->type === 'couple'
                    ? Seat::query()->where('room_id', $room->id)->where('type', 'couple')->where('pair_code', $requested->pair_code)->pluck('id')
                    : collect([$requested->id]);
                $originalIds = $this->originalSeatIds($incident, $initial);
                $lockedSeats = Seat::query()->whereIn('id', $targetIds->merge($originalIds)->unique()->sort()->values())
                    ->orderBy('id')->lockForUpdate()->get();

                $booking = Booking::query()->whereKey($initial->bookingSeat->booking_id)->lockForUpdate()->firstOrFail();
                $payments = Payment::query()->where('booking_id', $booking->id)->orderBy('id')->lockForUpdate()->get();
                $bookingSeats = BookingSeat::query()->where('booking_id', $booking->id)->orderBy('id')->lockForUpdate()->get();
                $lockedIncident = SeatIncident::query()->whereKey($incident->id)->lockForUpdate()->firstOrFail();
                $impacts = SeatIncidentImpact::query()->where('seat_incident_id', $lockedIncident->id)
                    ->orderBy('id')->lockForUpdate()->get();
                $this->loadIncidentContext($lockedIncident, $impacts, $booking, $payments, $bookingSeats);

                $options = $this->candidates->forIncident($lockedIncident);
                $unit = $options[$impact->id] ?? null;
                if (! $unit) {
                    throw ValidationException::withMessages(['impact' => 'Ảnh hưởng này không còn đủ điều kiện đổi ghế.']);
                }
                $equivalent = $unit['equivalent']->firstWhere('seat_id', $replacementSeatId);
                $upgrade = $unit['upgrade']->firstWhere('seat_id', $replacementSeatId);
                if ($equivalent) {
                    $candidate = $equivalent;
                    $type = SeatIncidentResolution::TYPE_EQUIVALENT;
                } elseif ($upgrade && $unit['equivalent']->isEmpty()) {
                    $candidate = $upgrade;
                    $type = SeatIncidentResolution::TYPE_UPGRADE;
                } else {
                    throw ValidationException::withMessages([
                        'replacement_seat_id' => $upgrade
                            ? 'Vẫn còn ghế tương đương; chỉ được nâng hạng khi không còn ghế tương đương phù hợp.'
                            : 'Ghế vừa được người khác chọn. Vui lòng chọn ghế khác.',
                    ]);
                }
                if (collect($candidate['seat_ids'])->sort()->values()->all() !== $targetIds->map(fn ($id): int => (int) $id)->sort()->values()->all()) {
                    throw ValidationException::withMessages(['replacement_seat_id' => 'Cặp ghế thay thế không còn hợp lệ.']);
                }

                $unitImpacts = $impacts->whereIn('id', $unit['impact_ids'])->sortBy('id')->values();
                $unitBookingSeats = $bookingSeats->whereIn('id', $unit['booking_seat_ids'])->values();
                $mapping = $this->replacementMapping($unitBookingSeats, $lockedSeats->whereIn('id', $candidate['seat_ids'])->values());
                $operationId = (string) Str::uuid();
                $resolutions = collect();
                $mailRows = [];

                foreach ($unitBookingSeats->sortBy('id') as $bookingSeat) {
                    $memberImpact = $unitImpacts->firstWhere('booking_seat_id', $bookingSeat->id);
                    if (! $memberImpact || $memberImpact->resolution_status !== SeatIncidentImpact::RESOLUTION_UNRESOLVED
                        || $memberImpact->resolution()->exists()
                        || $this->classifier->classify($booking, $payments) !== SeatIncidentImpact::PAID) {
                        throw ValidationException::withMessages(['impact' => 'Ảnh hưởng thanh toán đã thay đổi; chưa thể đổi ghế.']);
                    }
                    $oldSeat = $lockedSeats->firstWhere('id', $bookingSeat->seat_id);
                    $newSeat = $lockedSeats->firstWhere('id', $mapping[$bookingSeat->id]);
                    $ticket = $bookingSeat->admissionTicket()->lockForUpdate()->firstOrFail();
                    $reprintRequired = (int) $ticket->print_count > 0;
                    $resolution = SeatIncidentResolution::query()->create([
                        'seat_incident_impact_id' => $memberImpact->id,
                        'operation_id' => $operationId,
                        'resolution_type' => $type,
                        'original_seat_id' => $oldSeat->id,
                        'replacement_seat_id' => $newSeat->id,
                        'resolved_by_user_id' => $actor->id,
                        'original_pre_promotion_amount' => $unit['original_amount'],
                        'replacement_hypothetical_amount' => $candidate['hypothetical_amount'],
                        'reprint_required' => $reprintRequired,
                    ]);
                    $bookingSeat->forceFill(['seat_id' => $newSeat->id])->save();
                    $memberImpact->forceFill([
                        'resolution_status' => $reprintRequired ? SeatIncidentImpact::RESOLUTION_UNRESOLVED : SeatIncidentImpact::RESOLUTION_RESOLVED,
                        'resolved_at' => $reprintRequired ? null : now(),
                        'resolution_reason' => $reprintRequired ? 'reprint_required' : 'seat_relocated',
                    ])->save();
                    $resolutions->push($resolution);
                    $mailRows[] = ['original' => $oldSeat->seat_code, 'replacement' => $newSeat->seat_code, 'reprint_required' => $reprintRequired];
                }

                $this->recalculateIncident($lockedIncident);
                $this->activities->log('seat.incident_relocated', $lockedIncident, [], ['status' => $lockedIncident->fresh()->status], [
                    'operation_id' => $operationId,
                    'resolution_type' => $type,
                    'booking_id' => $booking->id,
                    'booking_seat_ids' => $unit['booking_seat_ids'],
                    'original_seat_ids' => $unit['original_seat_ids'],
                    'replacement_seat_ids' => $candidate['seat_ids'],
                    'actor_id' => $actor->id,
                ]);

                return ['resolutions' => $resolutions, 'booking' => $booking, 'rows' => $mailRows];
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'replacement_seat_id' => 'Ghế vừa được người khác chọn. Vui lòng chọn ghế khác.',
            ]);
        }

        $this->notifyCustomer($notification['booking'], $notification['rows']);

        return $notification['resolutions'];
    }

    /** @return Collection<int, SeatIncidentResolution> */
    public function requireRefund(SeatIncident $incident, SeatIncidentImpact $impact, User $actor): Collection
    {
        return DB::transaction(function () use ($incident, $impact, $actor): Collection {
            $initial = SeatIncidentImpact::query()->with('bookingSeat.seat')->findOrFail($impact->id);
            abort_unless((int) $initial->seat_incident_id === (int) $incident->id, 404);
            $room = Room::query()->whereKey($incident->room_id)->lockForUpdate()->firstOrFail();
            $this->cinemaAccess->authorizeCinema($actor, (int) $room->cinema_id);
            Showtime::query()->whereKey($initial->bookingSeat->showtime_id)->lockForUpdate()->firstOrFail();
            $originalIds = $this->originalSeatIds($incident, $initial);
            Seat::query()->whereIn('id', $originalIds)->orderBy('id')->lockForUpdate()->get();
            $booking = Booking::query()->whereKey($initial->bookingSeat->booking_id)->lockForUpdate()->firstOrFail();
            $payments = Payment::query()->where('booking_id', $booking->id)->orderBy('id')->lockForUpdate()->get();
            $bookingSeats = BookingSeat::query()->where('booking_id', $booking->id)->orderBy('id')->lockForUpdate()->get();
            $lockedIncident = SeatIncident::query()->whereKey($incident->id)->lockForUpdate()->firstOrFail();
            $impacts = SeatIncidentImpact::query()->where('seat_incident_id', $lockedIncident->id)->orderBy('id')->lockForUpdate()->get();
            $this->loadIncidentContext($lockedIncident, $impacts, $booking, $payments, $bookingSeats);

            $unit = $this->candidates->forIncident($lockedIncident)[$impact->id] ?? null;
            if (! $unit || $this->classifier->classify($booking, $payments) !== SeatIncidentImpact::PAID) {
                throw ValidationException::withMessages(['impact' => 'Ảnh hưởng này không còn đủ điều kiện xử lý.']);
            }
            if ($unit['equivalent']->isNotEmpty() || $unit['upgrade']->isNotEmpty()) {
                throw ValidationException::withMessages(['impact' => 'Đã có ghế thay thế phù hợp. Vui lòng xem lại danh sách ghế.']);
            }

            $operationId = (string) Str::uuid();
            $resolutions = collect();
            foreach ($impacts->whereIn('id', $unit['impact_ids']) as $memberImpact) {
                if ($memberImpact->resolution()->exists()) {
                    throw ValidationException::withMessages(['impact' => 'Ảnh hưởng đã được ghi nhận xử lý trước đó.']);
                }
                $bookingSeat = $bookingSeats->firstWhere('id', $memberImpact->booking_seat_id);
                $resolutions->push(SeatIncidentResolution::query()->create([
                    'seat_incident_impact_id' => $memberImpact->id,
                    'operation_id' => $operationId,
                    'resolution_type' => SeatIncidentResolution::TYPE_REQUIRES_REFUND,
                    'original_seat_id' => $bookingSeat->seat_id,
                    'resolved_by_user_id' => $actor->id,
                    'original_pre_promotion_amount' => $unit['original_amount'],
                    'reprint_required' => false,
                ]));
                $memberImpact->forceFill(['resolution_reason' => 'requires_refund'])->save();
            }
            $this->activities->log('seat.incident_requires_refund', $lockedIncident, [], [], [
                'operation_id' => $operationId, 'booking_id' => $booking->id,
                'impact_ids' => $unit['impact_ids'], 'actor_id' => $actor->id,
            ]);

            return $resolutions;
        }, 3);
    }

    public function completeRequiredReprint(SeatIncidentResolution $resolution, int $admissionTicketId): void
    {
        $resolution = SeatIncidentResolution::query()->lockForUpdate()->findOrFail($resolution->id);
        $impact = SeatIncidentImpact::query()->lockForUpdate()->findOrFail($resolution->seat_incident_impact_id);
        $ticketBookingSeatId = DB::table('admission_tickets')->where('id', $admissionTicketId)->value('booking_seat_id');
        abort_unless((int) $impact->booking_seat_id === (int) $ticketBookingSeatId
            && $resolution->resolution_type !== SeatIncidentResolution::TYPE_REQUIRES_REFUND
            && $resolution->reprint_required && $resolution->reprint_satisfied_at === null
            && $impact->resolution_status === SeatIncidentImpact::RESOLUTION_UNRESOLVED, 409);

        $resolution->forceFill(['reprint_satisfied_at' => now()])->save();
        $impact->forceFill([
            'resolution_status' => SeatIncidentImpact::RESOLUTION_RESOLVED,
            'resolved_at' => now(),
            'resolution_reason' => 'replacement_ticket_printed',
        ])->save();
        $incident = SeatIncident::query()->lockForUpdate()->findOrFail($impact->seat_incident_id);
        $this->recalculateIncident($incident);
        $this->activities->log('seat.incident_reprint_completed', $incident, [], ['status' => $incident->fresh()->status], [
            'seat_incident_resolution_id' => $resolution->id,
            'admission_ticket_id' => $admissionTicketId,
        ]);
    }

    private function originalSeatIds(SeatIncident $incident, SeatIncidentImpact $impact): Collection
    {
        $seat = $impact->bookingSeat->seat;
        if ($seat->type !== 'couple') {
            return collect([(int) $seat->id]);
        }

        return SeatIncidentImpact::query()->where('seat_incident_id', $incident->id)
            ->whereHas('bookingSeat', fn ($query) => $query->where('booking_id', $impact->bookingSeat->booking_id)
                ->whereHas('seat', fn ($seatQuery) => $seatQuery->where('type', 'couple')->where('pair_code', $seat->pair_code)))
            ->with('bookingSeat:id,seat_id')->get()->pluck('bookingSeat.seat_id')->map(fn ($id): int => (int) $id);
    }

    private function loadIncidentContext(SeatIncident $incident, Collection $impacts, Booking $booking, Collection $payments, Collection $bookingSeats): void
    {
        $booking->setRelation('payments', $payments);
        foreach ($bookingSeats as $bookingSeat) {
            $bookingSeat->setRelation('booking', $booking);
            $bookingSeat->loadMissing(['seat', 'showtime.room', 'showtime.cinema']);
        }
        foreach ($impacts as $memberImpact) {
            $memberImpact->setRelation('bookingSeat', $bookingSeats->firstWhere('id', $memberImpact->booking_seat_id)
                ?? $memberImpact->bookingSeat()->with(['seat', 'showtime.room', 'showtime.cinema', 'booking.payments'])->firstOrFail());
            $memberImpact->loadMissing('resolution');
        }
        $incident->setRelation('impacts', $impacts);
    }

    /** @return array<int,int> */
    private function replacementMapping(Collection $bookingSeats, Collection $targets): array
    {
        if ($bookingSeats->count() === 1 && $targets->count() === 1) {
            return [(int) $bookingSeats->first()->id => (int) $targets->first()->id];
        }
        if ($bookingSeats->count() !== 2 || $targets->count() !== 2) {
            throw ValidationException::withMessages(['replacement_seat_id' => 'Ghế đôi phải được đổi nguyên cặp.']);
        }
        $targetByPosition = $targets->keyBy('pair_position');
        if (! $targetByPosition->has('left') || ! $targetByPosition->has('right')) {
            throw ValidationException::withMessages(['replacement_seat_id' => 'Cặp ghế thay thế không hợp lệ.']);
        }

        return $bookingSeats->mapWithKeys(fn (BookingSeat $row): array => [
            (int) $row->id => (int) $targetByPosition->get($row->seat->pair_position)->id,
        ])->all();
    }

    private function recalculateIncident(SeatIncident $incident): void
    {
        $unresolved = SeatIncidentImpact::query()->where('seat_incident_id', $incident->id)
            ->where('resolution_status', SeatIncidentImpact::RESOLUTION_UNRESOLVED)->exists();
        if ($unresolved || $incident->status !== SeatIncident::STATUS_OPEN) {
            return;
        }
        $incident->forceFill(['status' => SeatIncident::STATUS_RESOLVED, 'resolved_at' => now()])->save();
        SeatIncidentSeat::query()->where('seat_incident_id', $incident->id)->update([
            'active_lock_key' => null, 'updated_at' => now(),
        ]);
        $this->activities->log('seat.incident_auto_resolved', $incident, ['status' => 'open'], ['status' => 'resolved']);
    }

    /** @param list<array{original:string,replacement:string,reprint_required:bool}> $rows */
    private function notifyCustomer(Booking $booking, array $rows): void
    {
        if (! $booking->recipient_email) {
            return;
        }
        try {
            Mail::to($booking->recipient_email)->send(new SeatRelocationMail($booking->fresh(), $rows));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
