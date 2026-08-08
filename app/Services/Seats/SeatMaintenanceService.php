<?php

namespace App\Services\Seats;

use App\Domain\Seats\SeatMaintenanceResult;
use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\Seat;
use App\Services\ActivityLogger;
use App\Services\CinemaAccessService;
use App\Support\SeatPresentation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SeatMaintenanceService
{
    private const MAX_BULK_UNITS = 50;

    public function __construct(
        private readonly CinemaAccessService $cinemaAccess,
        private readonly SeatMaintenanceProtectionService $protections,
        private readonly ActivityLogger $activities,
    ) {}

    public function update(Room $room, Seat $seat, string $status): SeatMaintenanceResult
    {
        return $this->transition($room, [$seat->id], $status, false);
    }

    /** @param list<int> $seatIds */
    public function bulk(Room $room, array $seatIds, string $status): SeatMaintenanceResult
    {
        $ids = collect($seatIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty() || $ids->count() > self::MAX_BULK_UNITS) {
            throw ValidationException::withMessages([
                'seat_ids' => 'Chọn từ 1 đến 50 đơn vị ghế cho mỗi lần cập nhật.',
            ]);
        }

        return $this->transition($room, $ids->all(), $status, true);
    }

    /** @param list<int> $inputIds */
    private function transition(Room $room, array $inputIds, string $targetStatus, bool $bulk): SeatMaintenanceResult
    {
        if (! in_array($targetStatus, Seat::OPERATIONAL_STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Trạng thái vận hành ghế không hợp lệ.']);
        }

        return DB::transaction(function () use ($room, $inputIds, $targetStatus, $bulk): SeatMaintenanceResult {
            $lockedRoom = Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();
            $this->cinemaAccess->authorizeCinema(auth()->user(), (int) $lockedRoom->cinema_id);
            abort_unless($lockedRoom->status === 'active', 404);
            $layout = RoomLayout::query()->where('room_id', $lockedRoom->id)->where('status', 'published')
                ->orderByDesc('version')->lockForUpdate()->firstOrFail();
            $requested = Seat::query()->whereIn('id', $inputIds)->where('room_id', $lockedRoom->id)->get();
            abort_unless($requested->count() === count($inputIds), 404);
            $pairCodes = $requested->where('type', 'couple')->pluck('pair_code')->filter()->unique();
            $allIds = $requested->pluck('id');
            if ($pairCodes->isNotEmpty()) {
                $allIds = $allIds->merge(Seat::query()->where('room_id', $lockedRoom->id)
                    ->whereIn('pair_code', $pairCodes)->pluck('id'));
            }
            $lockedSeats = Seat::query()->whereIn('id', $allIds->unique())->orderBy('id')->lockForUpdate()->get();
            $lockedRequested = $lockedSeats->whereIn('id', $inputIds)->values();
            abort_unless($lockedRequested->count() === count($inputIds), 404);
            $currentIds = DB::table('room_layout_cells')->where('room_layout_id', $layout->id)
                ->where('cell_type', 'seat')->pluck('seat_id')->map(fn ($id): int => (int) $id)->all();
            $currentLookup = array_fill_keys($currentIds, true);
            $inputLookup = array_fill_keys($inputIds, true);
            $units = $this->resolveUnits($lockedRequested, $lockedSeats, $inputLookup, $currentLookup);
            $protectionMap = $this->protections->summaries($units->flatMap(fn (array $unit) => $unit['seat_ids'])->all());

            if ($targetStatus !== Seat::STATUS_ACTIVE) {
                $refusals = $units->map(function (array $unit) use ($protectionMap): ?string {
                    $summaries = collect($unit['seat_ids'])->map(fn (int $id): array => $protectionMap[$id]);
                    if ($summaries->contains('active_hold', true)) {
                        return $unit['label'].': đang được giữ trong một phiên đặt vé';
                    }
                    if ($summaries->contains('issued_ticket', true) || $summaries->contains('future_sold', true)) {
                        return $unit['label'].': đã có vé được phát hành cho suất chiếu sắp tới';
                    }

                    return null;
                })->filter()->values();
                if ($refusals->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        $bulk ? 'seat_ids' : 'status' => 'Không thể cập nhật: '.$refusals->join('; ').'.',
                    ]);
                }
            }

            $changed = $units->filter(fn (array $unit): bool => $unit['status'] !== $targetStatus)->values();
            if ($changed->isEmpty()) {
                return new SeatMaintenanceResult(false, $units->pluck('label')->all(), $targetStatus);
            }

            $changedSeatIds = $changed->flatMap(fn (array $unit) => $unit['seat_ids'])->unique()->values();
            Seat::query()->whereIn('id', $changedSeatIds)->update([
                'status' => $targetStatus,
                'updated_at' => now(),
            ]);
            $labels = $changed->pluck('label')->all();
            $previous = $changed->pluck('status')->unique()->sort()->values()->join(',');
            $context = [
                'room_id' => $lockedRoom->id,
                'room_code' => $lockedRoom->code,
                'seat_ids' => $changedSeatIds->all(),
                'seat_units' => $labels,
                'count' => $changed->count(),
                'source' => $bulk ? 'bulk' : 'single',
                'action_scope' => 'current_published_layout',
                'protection' => 'unprotected',
            ];
            if ($bulk) {
                $this->activities->log(
                    'seat.maintenance_bulk_updated',
                    $lockedRoom,
                    ['previous_status' => $previous],
                    ['status' => $targetStatus],
                    $context,
                );
            } else {
                $subject = $lockedSeats->firstWhere('id', $changed->first()['unit_id']);
                $this->activities->log(
                    'seat.maintenance_updated',
                    $subject,
                    ['status' => $changed->first()['status']],
                    ['status' => $targetStatus],
                    $context + ['seat_code' => $changed->first()['label']],
                );
            }

            return new SeatMaintenanceResult(true, $labels, $targetStatus);
        });
    }

    /**
     * @param  Collection<int, Seat>  $requested
     * @param  Collection<int, Seat>  $lockedSeats
     * @param  array<int, bool>  $inputLookup
     * @param  array<int, bool>  $currentLookup
     * @return Collection<int, array{unit_id: int, seat_ids: list<int>, label: string, status: string}>
     */
    private function resolveUnits(Collection $requested, Collection $lockedSeats, array $inputLookup, array $currentLookup): Collection
    {
        $units = collect();
        $resolved = [];
        foreach ($requested->sortBy('id') as $seat) {
            if (isset($resolved[$seat->id])) {
                continue;
            }
            if (! isset($currentLookup[$seat->id]) || $seat->status === Seat::STATUS_RETIRED
                || ! in_array($seat->status, Seat::OPERATIONAL_STATUSES, true)) {
                throw ValidationException::withMessages(['seat' => "Ghế {$seat->seat_code} không thuộc phạm vi bảo trì của sơ đồ hiện hành."]);
            }
            if ($seat->type !== 'couple') {
                if ($seat->pair_code !== null || $seat->pair_position !== null) {
                    throw ValidationException::withMessages(['seat' => "Dữ liệu ghế {$seat->seat_code} không đồng nhất. Hãy sửa trong trình thiết kế sơ đồ ghế."]);
                }
                $resolved[$seat->id] = true;
                $units->push([
                    'unit_id' => $seat->id,
                    'seat_ids' => [$seat->id],
                    'label' => $seat->seat_code,
                    'status' => $seat->status,
                ]);

                continue;
            }

            $pair = $lockedSeats->where('type', 'couple')->where('pair_code', $seat->pair_code)->values();
            if (! SeatPresentation::isValidCouple($pair)
                || $pair->contains(fn (Seat $member): bool => ! isset($currentLookup[$member->id]))) {
                throw ValidationException::withMessages(['seat' => "Dữ liệu cặp ghế {$seat->pair_code} không đồng nhất. Hãy sửa trong trình thiết kế sơ đồ ghế."]);
            }
            $left = $pair->firstWhere('pair_position', 'left');
            if ($seat->id !== $left->id && ! isset($inputLookup[$left->id])) {
                throw ValidationException::withMessages(['seat' => 'Không thể cập nhật riêng một nửa của ghế đôi.']);
            }
            $statuses = $pair->pluck('status')->unique();
            if ($statuses->count() !== 1 || ! in_array((string) $statuses->first(), Seat::OPERATIONAL_STATUSES, true)) {
                throw ValidationException::withMessages(['seat' => "Trạng thái cặp ghế {$seat->pair_code} không đồng nhất. Hãy sửa dữ liệu trước khi bảo trì."]);
            }
            $ids = $pair->pluck('id')->map(fn ($id): int => (int) $id)->all();
            foreach ($ids as $id) {
                $resolved[$id] = true;
            }
            $units->push([
                'unit_id' => $left->id,
                'seat_ids' => $ids,
                'label' => SeatPresentation::groups($pair)->first()['label'],
                'status' => (string) $statuses->first(),
            ]);
        }

        return $units->values();
    }
}
