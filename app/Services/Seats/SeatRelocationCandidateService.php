<?php

namespace App\Services\Seats;

use App\Models\BookingSeat;
use App\Models\Seat;
use App\Models\SeatIncident;
use App\Models\SeatIncidentImpact;
use App\Models\SeatIncidentSeat;
use App\Support\SeatPresentation;
use Illuminate\Support\Collection;
use LogicException;

final class SeatRelocationCandidateService
{
    public function __construct(
        private readonly SeatIncidentImpactClassifier $classifier,
    ) {}

    /**
     * @return array<int, array{
     *   impact_ids:list<int>, booking_seat_ids:list<int>, original_seat_ids:list<int>,
     *   original_label:string, original_type:string, original_amount:int,
     *   equivalent:Collection<int,array<string,mixed>>, upgrade:Collection<int,array<string,mixed>>
     * }>
     */
    public function forIncident(SeatIncident $incident): array
    {
        if ($incident->status !== SeatIncident::STATUS_OPEN) {
            return [];
        }

        $incident->loadMissing([
            'impacts.resolution', 'impacts.bookingSeat.seat', 'impacts.bookingSeat.booking.payments',
        ]);
        $eligible = $incident->impacts->filter(fn (SeatIncidentImpact $impact): bool => $impact->resolution_status === SeatIncidentImpact::RESOLUTION_UNRESOLVED
            && $impact->resolution === null
            && $this->classifier->classify($impact->bookingSeat->booking) === SeatIncidentImpact::PAID
        )->sortBy('id')->values();
        if ($eligible->isEmpty()) {
            return [];
        }
        $eligible->loadMissing([
            'bookingSeat.showtime.room', 'bookingSeat.showtime.cinema',
            'bookingSeat.showtime.ticketPrices.seatType',
        ]);

        $units = $this->logicalUnits($eligible);
        $showtimes = $units->pluck('showtime')->unique('id')->values();
        $layoutIds = $showtimes->pluck('room_layout_id')->map(fn ($id): int => (int) $id)->unique()->values();
        $showtimeIds = $showtimes->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $seats = Seat::query()->where('room_id', $incident->room_id)
            ->where('status', Seat::STATUS_ACTIVE)
            ->whereHas('layoutCells', fn ($query) => $query->whereIn('room_layout_id', $layoutIds)->where('cell_type', 'seat'))
            ->with([
                'seatType',
                'layoutCells' => fn ($query) => $query->whereIn('room_layout_id', $layoutIds)->where('cell_type', 'seat'),
            ])
            ->orderBy('id')->get();
        $occupied = BookingSeat::query()->whereIn('showtime_id', $showtimeIds)
            ->where('active_lock_key', BookingSeat::ACTIVE_LOCK_KEY)
            ->get(['showtime_id', 'seat_id'])
            ->mapWithKeys(fn (BookingSeat $row): array => [$row->showtime_id.':'.$row->seat_id => true]);
        $incidentSeatIds = SeatIncidentSeat::query()->where('active_lock_key', SeatIncidentSeat::ACTIVE_LOCK_KEY)
            ->whereIn('seat_id', $seats->pluck('id'))->pluck('seat_id')->map(fn ($id): int => (int) $id)->flip();

        $result = [];
        foreach ($units as $unit) {
            $showtime = $unit['showtime'];
            $layoutId = (int) $showtime->room_layout_id;
            $available = $seats->filter(function (Seat $seat) use ($layoutId, $showtime, $occupied, $incidentSeatIds): bool {
                return $seat->layoutCells->contains('room_layout_id', $layoutId)
                    && ! $occupied->has($showtime->id.':'.$seat->id)
                    && ! $incidentSeatIds->has($seat->id);
            })->values();
            $candidateUnits = $this->candidateUnits($available, $layoutId);
            $originalType = $unit['original_type'];
            $originalAmount = $unit['original_amount'];
            $equivalent = collect();
            $upgrades = collect();

            foreach ($candidateUnits as $candidate) {
                if ($candidate['type'] === $originalType) {
                    $candidate['hypothetical_amount'] = $this->currentAmount($showtime, $candidate['seat_type_id']);
                    if ($candidate['hypothetical_amount'] === null || $candidate['hypothetical_amount'] < $originalAmount) {
                        continue;
                    }
                    $equivalent->push($candidate);
                } elseif ($originalType !== 'couple' && $candidate['type'] !== 'couple') {
                    $candidate['hypothetical_amount'] = $this->currentAmount($showtime, $candidate['seat_type_id']);
                    if ($candidate['hypothetical_amount'] !== null && $candidate['hypothetical_amount'] >= $originalAmount) {
                        $upgrades->push($candidate);
                    }
                }
            }

            $primaryImpactId = min($unit['impact_ids']);
            $result[$primaryImpactId] = [
                'impact_ids' => $unit['impact_ids'],
                'booking_seat_ids' => $unit['booking_seat_ids'],
                'original_seat_ids' => $unit['original_seat_ids'],
                'original_label' => $unit['original_label'],
                'original_type' => $originalType,
                'original_amount' => $originalAmount,
                'equivalent' => $equivalent->sortBy('label', SORT_NATURAL)->values(),
                'upgrade' => $upgrades->sortBy('label', SORT_NATURAL)->values(),
            ];
        }

        return $result;
    }

    /** @param Collection<int, SeatIncidentImpact> $impacts */
    private function logicalUnits(Collection $impacts): Collection
    {
        $resolved = [];
        $units = collect();
        foreach ($impacts as $impact) {
            if (isset($resolved[$impact->id])) {
                continue;
            }
            $bookingSeat = $impact->bookingSeat;
            $seat = $bookingSeat->seat;
            if ($seat->type !== 'couple') {
                $resolved[$impact->id] = true;
                $units->push($this->unit(collect([$impact])));

                continue;
            }

            $pair = $impacts->filter(fn (SeatIncidentImpact $candidate): bool => (int) $candidate->bookingSeat->booking_id === (int) $bookingSeat->booking_id
                && $candidate->bookingSeat->seat->type === 'couple'
                && $candidate->bookingSeat->seat->pair_code === $seat->pair_code
            )->values();
            if ($pair->count() !== 2 || ! SeatPresentation::isValidCouple($pair->pluck('bookingSeat.seat'))) {
                continue;
            }
            foreach ($pair as $member) {
                $resolved[$member->id] = true;
            }
            $units->push($this->unit($pair));
        }

        return $units;
    }

    /** @param Collection<int, SeatIncidentImpact> $impacts @return array<string,mixed> */
    private function unit(Collection $impacts): array
    {
        $bookingSeats = $impacts->pluck('bookingSeat')->sortBy(fn (BookingSeat $row): int => $row->seat->id)->values();
        $seats = $bookingSeats->pluck('seat');
        $type = (string) $seats->first()->type;
        $amount = $this->historicalAmount($bookingSeats, $type);

        return [
            'impact_ids' => $impacts->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all(),
            'booking_seat_ids' => $bookingSeats->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'original_seat_ids' => $seats->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'original_label' => $type === 'couple' ? SeatPresentation::groups($seats)->first()['label'] : $seats->first()->seat_code,
            'original_type' => $type,
            'original_amount' => $amount,
            'showtime' => $bookingSeats->first()->showtime,
        ];
    }

    /** @param Collection<int, BookingSeat> $bookingSeats */
    private function historicalAmount(Collection $bookingSeats, string $type): int
    {
        $snapshots = $bookingSeats->map(fn (BookingSeat $row) => $row->getRawOriginal('final_unit_amount'));
        if ($snapshots->every(fn ($value): bool => $value !== null)) {
            return $type === 'couple' ? (int) $snapshots->first() : (int) $snapshots->sum();
        }

        throw new LogicException('Relocation requires the immutable BookingSeat logical-unit amount snapshot.');
    }

    /** @param Collection<int, Seat> $seats @return Collection<int,array<string,mixed>> */
    private function candidateUnits(Collection $seats, int $layoutId): Collection
    {
        $units = collect();
        foreach ($seats->where('type', '!=', 'couple') as $seat) {
            if ($seat->pair_code === null && $seat->pair_position === null) {
                $units->push([
                    'seat_id' => (int) $seat->id,
                    'seat_ids' => [(int) $seat->id],
                    'label' => (string) $seat->seat_code,
                    'type' => (string) $seat->type,
                    'seat_type_id' => (int) $seat->seat_type_id,
                ]);
            }
        }
        foreach ($seats->where('type', 'couple')->groupBy('pair_code') as $pairCode => $pair) {
            if ($pairCode && SeatPresentation::isValidCouple($pair)
                && $pair->every(fn (Seat $seat): bool => $seat->layoutCells->contains('room_layout_id', $layoutId))) {
                $left = $pair->firstWhere('pair_position', 'left');
                $units->push([
                    'seat_id' => (int) $left->id,
                    'seat_ids' => $pair->sortBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                    'label' => SeatPresentation::groups($pair)->first()['label'],
                    'type' => 'couple',
                    'seat_type_id' => (int) $left->seat_type_id,
                ]);
            }
        }

        return $units;
    }

    private function currentAmount($showtime, int $seatTypeId): ?int
    {
        $snapshot = $showtime->ticketPrices->firstWhere('seat_type_id', $seatTypeId);

        return $snapshot ? (int) $snapshot->final_unit_amount_vnd : null;
    }
}
