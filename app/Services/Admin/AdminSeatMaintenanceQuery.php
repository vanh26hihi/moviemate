<?php

namespace App\Services\Admin;

use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\Seat;
use App\Services\CinemaContext;
use App\Services\Seats\SeatMaintenanceProtectionService;
use App\Support\SeatPresentation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

final class AdminSeatMaintenanceQuery
{
    public function __construct(
        private readonly CinemaContext $cinemaContext,
        private readonly SeatMaintenanceProtectionService $protections,
    ) {}

    /** @return array<string, mixed> */
    public function get(Room $room, array $filters): array
    {
        abort_unless($room->cinema_id === $this->cinemaContext->id() && $room->status === 'active', 404);
        $layout = RoomLayout::query()->where('room_id', $room->id)->where('status', 'published')
            ->orderByDesc('version')->firstOrFail();
        $seats = Seat::query()->where('room_id', $room->id)
            ->whereHas('layoutCells', fn ($query) => $query->where('room_layout_id', $layout->id)->where('cell_type', 'seat'))
            ->orderBy('row')->orderBy('number')->orderBy('id')->get();
        $protectionMap = $this->protections->summaries($seats->pluck('id')->all());
        $units = SeatPresentation::groups($seats)->map(function (array $group) use ($layout, $protectionMap): array {
            $members = $group['seats'];
            $memberProtections = $members->map(fn (Seat $seat): array => $protectionMap[$seat->id]);
            $statuses = $members->pluck('status')->unique()->values();
            $canonical = $group['is_couple']
                ? $members->firstWhere('pair_position', 'left')
                : $members->first();

            return $group + [
                'unit_id' => $canonical?->id,
                'row' => (string) ($members->first()?->row ?? ''),
                'status' => $statuses->count() === 1 ? (string) $statuses->first() : 'inconsistent',
                'updated_at' => $members->max('updated_at'),
                'layout_version' => $layout->version,
                'active_hold' => $memberProtections->contains('active_hold', true),
                'future_sold' => $memberProtections->contains('future_sold', true),
                'issued_ticket' => $memberProtections->contains('issued_ticket', true),
                'protected' => $memberProtections->contains('protected', true),
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
                'protected' => $seats->filter(fn (Seat $seat): bool => $protectionMap[$seat->id]['protected'])->count(),
            ],
            'historicalOnlyCount' => Seat::query()->where('room_id', $room->id)
                ->whereHas('layoutCells.layout', fn ($query) => $query->where('status', 'published')->whereKeyNot($layout->id))
                ->whereDoesntHave('layoutCells', fn ($query) => $query->where('room_layout_id', $layout->id))
                ->count(),
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
