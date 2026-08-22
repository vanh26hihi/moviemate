<?php

namespace App\Services\Admin;

use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\Seat;
use App\Models\SeatIncident;
use App\Services\CinemaAccessService;
use App\Services\Seats\SeatIncidentImpactClassifier;
use App\Services\Seats\SeatIncidentImpactQuery;
use App\Support\SeatPresentation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

final class AdminSeatMaintenanceQuery
{
    public function __construct(
        private readonly CinemaAccessService $cinemaAccess,
        private readonly SeatIncidentImpactQuery $incidentImpacts,
        private readonly SeatIncidentImpactClassifier $classifier,
    ) {}

    /** @return array<string, mixed> */
    public function get(Room $room, array $filters): array
    {
        $this->cinemaAccess->authorizeCinema(auth()->user(), (int) $room->cinema_id);
        abort_unless($room->status === 'active', 404);
        $layout = RoomLayout::query()->where('room_id', $room->id)->where('status', 'published')
            ->orderByDesc('version')->firstOrFail();
        $seats = Seat::query()->where('room_id', $room->id)
            ->whereHas('layoutCells', fn ($query) => $query->where('room_layout_id', $layout->id)->where('cell_type', 'seat'))
            ->orderBy('row')->orderBy('number')->orderBy('id')->get();
        $impactRows = $this->incidentImpacts->get($room, $seats->pluck('id')->map(fn ($id): int => (int) $id)->all());
        $units = SeatPresentation::groups($seats)->map(function (array $group) use ($layout, $impactRows): array {
            $members = $group['seats'];
            $statuses = $members->pluck('status')->unique()->values();
            $canonical = $group['is_couple']
                ? $members->firstWhere('pair_position', 'left')
                : $members->first();
            $unitImpacts = $impactRows->filter(fn ($row): bool => $members->contains('id', $row->seat_id));
            $bookings = $unitImpacts->groupBy('booking_id')->map(fn ($rows) => $rows->first()->booking);
            $classifications = $bookings->map(fn ($booking): string => $this->classifier->classify($booking));
            $ordinaryCount = $classifications->filter(fn (string $value): bool => $value === 'ordinary_hold')->count();
            $retainedCount = $classifications->filter(fn (string $value): bool => $value === 'retained_payment')->count();
            $paidCount = $classifications->filter(fn (string $value): bool => $value === 'paid')->count();
            $printed = $unitImpacts->contains(fn ($row): bool => (int) ($row->admissionTicket?->print_count ?? 0) > 0);
            $issued = $bookings->contains(fn ($booking): bool => $booking->ticket_emailed_at !== null
                || $booking->ticketDelivery?->status === 'sent');

            return $group + [
                'unit_id' => $canonical?->id,
                'row' => (string) ($members->first()?->row ?? ''),
                'status' => $statuses->count() === 1 ? (string) $statuses->first() : 'inconsistent',
                'updated_at' => $members->max('updated_at'),
                'layout_version' => $layout->version,
                'active_hold' => $ordinaryCount > 0,
                'future_sold' => $paidCount > 0,
                'issued_ticket' => $issued,
                'protected' => $bookings->isNotEmpty(),
                'impact_total' => $bookings->count(),
                'impact_ordinary_hold' => $ordinaryCount,
                'impact_retained_payment' => $retainedCount,
                'impact_paid' => $paidCount,
                'has_printed_ticket' => $printed,
            ];
        })->values();
        $filtered = $this->filter($units, $filters);
        $sort = $filters['sort'] ?? 'seat_code';
        $descending = ($filters['direction'] ?? 'asc') === 'desc';
        $filtered = $filtered->sortBy(fn (array $unit): mixed => match ($sort) {
            'row' => $unit['row'],
            'type' => $unit['type'],
            'status' => $unit['status'],
            'updated_at' => $unit['updated_at'],
            default => $unit['seat_code'],
        }, SORT_NATURAL, $descending)->values();
        $perPage = (int) ($filters['per_page'] ?? 25);
        $page = Paginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'query' => request()->query()],
        );

        return [
            'room' => $room->loadMissing('cinema'),
            'layout' => $layout,
            'units' => $paginator,
            'filters' => $filters,
            'summary' => [
                'total' => $seats->count(),
                'active' => $seats->where('status', Seat::STATUS_ACTIVE)->count(),
                'maintenance' => $seats->where('status', Seat::STATUS_MAINTENANCE)->count(),
                'inactive' => $seats->where('status', Seat::STATUS_INACTIVE)->count(),
                'protected' => $impactRows->pluck('seat_id')->unique()->count(),
            ],
            'historicalOnlyCount' => Seat::query()->where('room_id', $room->id)
                ->whereHas('layoutCells.layout', fn ($query) => $query->where('status', 'published')->whereKeyNot($layout->id))
                ->whereDoesntHave('layoutCells', fn ($query) => $query->where('room_layout_id', $layout->id))
                ->count(),
            'incidents' => SeatIncident::query()->where('room_id', $room->id)
                ->with(['incidentSeats.seat:id,seat_code'])
                ->withCount(['impacts', 'impacts as unresolved_impacts_count' => fn ($query) => $query->where('resolution_status', 'unresolved')])
                ->latest('id')->limit(10)->get(),
        ];
    }

    /** @param Collection<int, array<string, mixed>> $units */
    private function filter(Collection $units, array $filters): Collection
    {
        return $units
            ->when($filters['seat_code'] ?? null, fn (Collection $items, string $value) => $items->filter(
                fn (array $unit): bool => str_contains(mb_strtolower($unit['seat_code']), mb_strtolower(trim($value)))
            ))
            ->when($filters['row'] ?? null, fn (Collection $items, string $value) => $items->where('row', strtoupper(trim($value))))
            ->when($filters['type'] ?? null, fn (Collection $items, string $value) => $items->where('type', $value))
            ->when($filters['status'] ?? null, fn (Collection $items, string $value) => $items->where('status', $value))
            ->when(($filters['couple'] ?? null) === 'yes', fn (Collection $items) => $items->where('is_couple', true))
            ->when(($filters['couple'] ?? null) === 'no', fn (Collection $items) => $items->where('is_couple', false))
            ->when(($filters['active_hold'] ?? null) === 'yes', fn (Collection $items) => $items->where('active_hold', true))
            ->when(($filters['active_hold'] ?? null) === 'no', fn (Collection $items) => $items->where('active_hold', false))
            ->when(($filters['future_ticket'] ?? null) === 'yes', fn (Collection $items) => $items->where('future_sold', true))
            ->when(($filters['future_ticket'] ?? null) === 'no', fn (Collection $items) => $items->where('future_sold', false));
    }
}
