<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\RoomLayoutTemplate;
use App\Models\Seat;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplyRoomLayoutTemplateService
{
    public function __construct(
        private readonly CinemaAccessService $cinemaAccess,
        private readonly RoomLayoutService $layouts,
        private readonly RoomLayoutTemplateGeometry $geometry,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function apply(Room $room, RoomLayoutTemplate $template, string $name, ?string $changeNote, User $actor, bool $publish = false): RoomLayout
    {
        abort_unless($actor->hasPermission('room_layouts.apply_template'), 403);
        $this->cinemaAccess->authorizeCinema($actor, (int) $room->cinema_id);
        $this->layouts->assertMeaningfulName($name);
        if ($room->status !== 'active' || $template->status !== RoomLayoutTemplate::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['template_id' => 'Chỉ có thể áp dụng mẫu đang sử dụng cho phòng đang hoạt động.']);
        }
        if ($template->room_type && strcasecmp((string) $template->room_type, (string) $room->room_type) !== 0) {
            throw ValidationException::withMessages(['template_id' => 'Loại phòng không tương thích với mẫu đã chọn.']);
        }

        return DB::transaction(function () use ($room, $template, $name, $changeNote, $actor, $publish): RoomLayout {
            $lockedRoom = Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();
            $lockedTemplate = RoomLayoutTemplate::query()->with('cells')->whereKey($template->id)->lockForUpdate()->firstOrFail();
            if ($lockedTemplate->status !== RoomLayoutTemplate::STATUS_ACTIVE
                || RoomLayout::query()->where('room_id', $lockedRoom->id)->where('status', 'draft')->exists()) {
                throw ValidationException::withMessages(['template_id' => 'Mẫu không còn khả dụng hoặc phòng đã có một sơ đồ nháp.']);
            }

            $normalized = $this->geometry->normalize([
                'rows' => $lockedTemplate->rows, 'columns' => $lockedTemplate->columns,
                'screen_position' => $lockedTemplate->screen_position,
                'cells' => $lockedTemplate->cells->map(fn ($cell) => $cell->toArray())->all(),
            ]);
            $seatTypeIds = $this->layouts->seatTypeIds();
            $existing = Seat::query()->where('room_id', $lockedRoom->id)->lockForUpdate()->get()->keyBy('seat_code');
            $seatIds = [];
            $newSeats = [];
            $reactivateSeatIds = [];
            $now = now();
            foreach ($normalized['cells'] as $cell) {
                if ($cell['cell_type'] !== 'seat') {
                    continue;
                }
                $metadata = $cell['metadata'];
                $attributes = [
                    'room_id' => $lockedRoom->id, 'row' => $metadata['row'], 'number' => $metadata['number'],
                    'seat_code' => $cell['seat_label'], 'type' => $cell['seat_type'], 'seat_type_id' => $seatTypeIds[$cell['seat_type']],
                    'pair_code' => $cell['pair_key'], 'pair_position' => $metadata['pair_position'] ?? null,
                    'row_label' => $metadata['row'], 'seat_number' => $metadata['number'],
                    'x_position' => $cell['x_position'], 'y_position' => $cell['y_position'], 'is_center' => false,
                ];
                $seat = $existing->get($cell['seat_label']);
                if ($seat) {
                    $keys = ['row', 'number', 'type', 'seat_type_id', 'pair_code', 'pair_position', 'row_label', 'seat_number', 'x_position', 'y_position'];
                    $matches = collect($keys)->every(fn (string $key): bool => (string) $seat->{$key} === (string) $attributes[$key]);
                    if (! $matches) {
                        throw ValidationException::withMessages([
                            'template_id' => "Mã {$cell['seat_label']} đã thuộc một ghế lịch sử có cấu trúc khác. Hãy đổi nhãn ghế trong mẫu để bảo toàn lịch sử.",
                        ]);
                    }
                    if ($seat->status === Seat::STATUS_RETIRED) {
                        $reactivateSeatIds[] = $seat->id;
                    }
                    $seatIds[$cell['seat_label']] = $seat->id;
                } else {
                    $newSeats[] = $attributes + [
                        'status' => Seat::STATUS_ACTIVE, 'created_at' => $now, 'updated_at' => $now,
                    ];
                }
            }
            if ($reactivateSeatIds !== []) {
                Seat::query()->whereIn('id', $reactivateSeatIds)->update(['status' => Seat::STATUS_ACTIVE]);
            }
            if ($newSeats !== []) {
                Seat::query()->insert($newSeats);
                foreach (Seat::query()->where('room_id', $lockedRoom->id)
                    ->whereIn('seat_code', collect($newSeats)->pluck('seat_code'))->get(['id', 'seat_code']) as $seat) {
                    $seatIds[$seat->seat_code] = $seat->id;
                }
            }

            $layout = RoomLayout::query()->create([
                'room_id' => $lockedRoom->id,
                'version' => ((int) RoomLayout::query()->where('room_id', $lockedRoom->id)->max('version')) + 1,
                'name' => trim($name), 'change_note' => $changeNote,
                'source_template_id' => $lockedTemplate->id, 'source_template_name_snapshot' => $lockedTemplate->name,
                'rows' => $normalized['rows'], 'columns' => $normalized['columns'],
                'screen_position' => $normalized['screen_position'], 'status' => 'draft',
                'created_by' => $actor->id, 'updated_by' => $actor->id,
            ]);
            DB::table('room_layout_cells')->insert(array_map(fn (array $cell): array => [
                'room_layout_id' => $layout->id, 'x_position' => $cell['x_position'], 'y_position' => $cell['y_position'],
                'cell_type' => $cell['cell_type'], 'seat_id' => $cell['cell_type'] === 'seat' ? $seatIds[$cell['seat_label']] : null,
                'created_at' => $now, 'updated_at' => $now,
            ], $normalized['cells']));

            $this->activityLogger->log('room_layout.template_applied', $layout, [], [
                'template_id' => $lockedTemplate->id, 'template_name' => $lockedTemplate->name, 'published' => $publish,
            ]);

            return $publish ? $this->layouts->publish($layout, (int) $actor->id) : $layout->fresh('cells.seat');
        });
    }
}
