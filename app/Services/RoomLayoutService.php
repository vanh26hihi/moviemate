<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomLayout;
use App\Models\RoomLayoutCell;
use App\Models\Seat;
use App\Models\Showtime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RoomLayoutService
{
    public const MAX_ROWS = 30;

    public const MAX_COLUMNS = 40;

    public const MAX_CELLS = 1200;

    public function createBlankDraft(
        Room $room,
        ?int $userId = null,
        int $rows = 10,
        int $columns = 12,
        string $screenPosition = 'top'
    ): RoomLayout {
        $this->assertOperationalRoom($room);
        $this->validateDimensions($rows, $columns, $screenPosition);

        return DB::transaction(function () use ($room, $userId, $rows, $columns, $screenPosition): RoomLayout {
            Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();
            if (RoomLayout::query()->where('room_id', $room->id)->where('status', 'draft')->exists()) {
                throw ValidationException::withMessages(['layout' => 'Phòng này đã có một layout nháp.']);
            }

            $version = ((int) RoomLayout::query()->where('room_id', $room->id)->max('version')) + 1;

            return RoomLayout::query()->create([
                'room_id' => $room->id,
                'version' => $version,
                'name' => "Layout v{$version}",
                'rows' => $rows,
                'columns' => $columns,
                'screen_position' => $screenPosition,
                'status' => 'draft',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        });
    }

    public function clonePublishedToDraft(Room $room, ?int $userId = null): RoomLayout
    {
        $published = $this->latestPublishedFor($room);
        if (! $published) {
            return $this->createBlankDraft($room, $userId);
        }

        return DB::transaction(function () use ($room, $published, $userId): RoomLayout {
            $draft = $this->createBlankDraft(
                $room,
                $userId,
                $published->rows,
                $published->columns,
                $published->screen_position
            );
            $draft->update(['name' => "Bản nháp từ v{$published->version}"]);

            $published->loadMissing('cells');
            foreach ($published->cells as $cell) {
                $draft->cells()->create($cell->only(['x_position', 'y_position', 'cell_type', 'seat_id']));
            }

            return $draft->load('cells.seat');
        });
    }

    public function saveDraft(RoomLayout $layout, array $payload, ?int $userId = null): RoomLayout
    {
        $normalized = $this->validateDraft($layout, $payload);

        return DB::transaction(function () use ($layout, $normalized, $userId): RoomLayout {
            $locked = RoomLayout::query()->whereKey($layout->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['layout' => 'Chỉ layout nháp mới được chỉnh sửa.']);
            }

            $locked->cells()->delete();
            $seatTypeIds = $this->seatTypeIds();

            foreach ($normalized['cells'] as $cell) {
                if ($cell['cell_type'] === 'aisle') {
                    $locked->cells()->create([
                        'x_position' => $cell['x_position'],
                        'y_position' => $cell['y_position'],
                        'cell_type' => 'aisle',
                        'seat_id' => null,
                    ]);

                    continue;
                }

                $seat = Seat::query()->updateOrCreate(
                    ['room_id' => $locked->room_id, 'seat_code' => $cell['seat_code']],
                    [
                        'row' => $cell['row'],
                        'number' => $cell['number'],
                        'type' => $cell['type'],
                        'seat_type_id' => $seatTypeIds[$cell['type']],
                        'pair_code' => $cell['pair_code'],
                        'pair_position' => $cell['pair_position'],
                        'row_label' => $cell['row'],
                        'seat_number' => $cell['number'],
                        'x_position' => $cell['x_position'],
                        'y_position' => $cell['y_position'],
                        'is_center' => false,
                        'status' => $cell['status'],
                    ]
                );

                $locked->cells()->create([
                    'x_position' => $cell['x_position'],
                    'y_position' => $cell['y_position'],
                    'cell_type' => 'seat',
                    'seat_id' => $seat->id,
                ]);
            }

            Seat::query()->where('room_id', $locked->room_id)
                ->whereDoesntHave('layoutCells')
                ->whereDoesntHave('bookingSeats')
                ->delete();

            $locked->update([
                'name' => $normalized['name'],
                'rows' => $normalized['rows'],
                'columns' => $normalized['columns'],
                'screen_position' => $normalized['screen_position'],
                'updated_by' => $userId,
            ]);

            return $locked->fresh(['cells.seat']);
        });
    }

    public function validateDraft(RoomLayout $layout, array $payload): array
    {
        if ($layout->status !== 'draft') {
            throw ValidationException::withMessages(['layout' => 'Published layout là bất biến.']);
        }

        $rows = filter_var($payload['rows'] ?? null, FILTER_VALIDATE_INT);
        $columns = filter_var($payload['columns'] ?? null, FILTER_VALIDATE_INT);
        $screen = $payload['screen_position'] ?? null;
        $this->validateDimensions($rows ?: 0, $columns ?: 0, (string) $screen);

        if (! is_array($payload['cells'] ?? null) || count($payload['cells']) > self::MAX_CELLS) {
            throw ValidationException::withMessages(['cells' => 'Payload layout không hợp lệ hoặc vượt quá 1200 ô.']);
        }

        $coordinates = [];
        $codes = [];
        $pairs = [];
        $normalized = [];

        foreach ($payload['cells'] as $index => $input) {
            if (! is_array($input)) {
                throw ValidationException::withMessages(["cells.{$index}" => 'Ô layout phải là object.']);
            }

            $kind = strtolower(trim((string) ($input['kind'] ?? $input['cell_type'] ?? '')));
            if ($kind === 'empty') {
                continue;
            }

            $x = filter_var($input['x_position'] ?? $input['x'] ?? null, FILTER_VALIDATE_INT);
            $y = filter_var($input['y_position'] ?? $input['y'] ?? null, FILTER_VALIDATE_INT);
            if (! $x || ! $y || $x > $columns || $y > $rows) {
                throw ValidationException::withMessages(["cells.{$index}" => 'Tọa độ ô nằm ngoài giới hạn layout.']);
            }
            $coordinate = "{$x}:{$y}";
            if (isset($coordinates[$coordinate])) {
                throw ValidationException::withMessages(["cells.{$index}" => 'Tọa độ ô bị trùng.']);
            }
            $coordinates[$coordinate] = true;

            if ($kind === 'aisle') {
                $normalized[] = [
                    'x_position' => $x,
                    'y_position' => $y,
                    'cell_type' => 'aisle',
                ];

                continue;
            }

            $type = in_array($kind, ['normal', 'vip', 'couple'], true)
                ? $kind
                : strtolower(trim((string) ($input['type'] ?? '')));
            if (! in_array($type, ['normal', 'vip', 'couple'], true)) {
                throw ValidationException::withMessages(["cells.{$index}.type" => 'Loại ghế không hợp lệ.']);
            }

            $row = strtoupper(trim((string) ($input['row'] ?? '')));
            $number = filter_var($input['number'] ?? null, FILTER_VALIDATE_INT);
            $code = strtoupper(trim((string) ($input['seat_code'] ?? '')));
            $status = strtolower(trim((string) ($input['status'] ?? ($kind === 'maintenance' ? 'maintenance' : 'active'))));
            if (! preg_match('/^[A-Z]{1,2}$/', $row) || ! $number || $number > 99 || $code !== $row.$number) {
                throw ValidationException::withMessages(["cells.{$index}.seat_code" => 'Mã ghế phải khớp hàng và số ghế, ví dụ A1.']);
            }
            if (isset($codes[$code])) {
                throw ValidationException::withMessages(["cells.{$index}.seat_code" => 'Mã ghế bị trùng trong phòng.']);
            }
            if (! in_array($status, ['active', 'maintenance', 'inactive', 'retired'], true)) {
                throw ValidationException::withMessages(["cells.{$index}.status" => 'Trạng thái ghế không hợp lệ.']);
            }
            $codes[$code] = true;

            $pairCode = $type === 'couple' ? trim((string) ($input['pair_code'] ?? '')) : null;
            $pairPosition = $type === 'couple' ? strtolower(trim((string) ($input['pair_position'] ?? ''))) : null;
            if ($type === 'couple') {
                if ($pairCode === '' || ! in_array($pairPosition, ['left', 'right'], true)) {
                    throw ValidationException::withMessages(["cells.{$index}.pair_code" => 'Ghế đôi phải có pair_code và vị trí left/right.']);
                }
                $pairs[$pairCode][] = compact('row', 'number', 'x', 'y', 'pairPosition');
            }

            $normalized[] = [
                'x_position' => $x,
                'y_position' => $y,
                'cell_type' => 'seat',
                'seat_code' => $code,
                'row' => $row,
                'number' => $number,
                'type' => $type,
                'status' => $status,
                'pair_code' => $pairCode,
                'pair_position' => $pairPosition,
            ];
        }

        foreach ($pairs as $pairCode => $pair) {
            $valid = count($pair) === 2
                && $pair[0]['row'] === $pair[1]['row']
                && $pair[0]['y'] === $pair[1]['y']
                && abs($pair[0]['number'] - $pair[1]['number']) === 1
                && abs($pair[0]['x'] - $pair[1]['x']) === 1
                && collect($pair)->pluck('pairPosition')->sort()->values()->all() === ['left', 'right'];
            if (! $valid) {
                throw ValidationException::withMessages(['cells' => "Cặp {$pairCode} phải có đúng hai ghế liền nhau, cùng hàng và đủ left/right."]);
            }
        }

        return [
            'name' => trim((string) ($payload['name'] ?? $layout->name)) ?: null,
            'rows' => $rows,
            'columns' => $columns,
            'screen_position' => $screen,
            'cells' => $normalized,
        ];
    }

    public function publish(RoomLayout $layout, ?int $userId = null): RoomLayout
    {
        return DB::transaction(function () use ($layout, $userId): RoomLayout {
            $locked = RoomLayout::query()->with('cells.seat')->whereKey($layout->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['layout' => 'Chỉ layout nháp mới được publish.']);
            }
            if ($locked->cells->where('cell_type', 'seat')->isEmpty()) {
                throw ValidationException::withMessages(['layout' => 'Layout phải có ít nhất một ghế.']);
            }

            $this->validateDraft($locked, $this->payloadFromLayout($locked));
            $locked->update([
                'status' => 'published',
                'published_at' => now(),
                'updated_by' => $userId,
            ]);
            $locked->room()->update(['total_seats' => $locked->cells()->where('cell_type', 'seat')->count()]);

            return $locked->fresh(['cells.seat']);
        });
    }

    public function latestPublishedFor(Room $room): ?RoomLayout
    {
        return RoomLayout::query()->where('room_id', $room->id)
            ->published()->orderByDesc('version')->first();
    }

    public function resolveForShowtime(Showtime $showtime): RoomLayout
    {
        $layout = $showtime->roomLayout()->with('cells.seat')->first();
        if (! $layout || $layout->status !== 'published' || $layout->room_id !== $showtime->room_id) {
            throw ValidationException::withMessages(['showtime' => 'Suất chiếu không có layout published hợp lệ.']);
        }

        return $layout;
    }

    /** @param Collection<int, Room> $rooms */
    public function rebuildDefaultLayouts(Collection $rooms): Collection
    {
        $byCode = $rooms->keyBy('code');
        $definitions = [
            'P01' => $this->wideLayoutDefinition(),
            'P02' => $this->twoAisleLayoutDefinition(),
            'P03' => $this->irregularLayoutDefinition(),
        ];

        return collect($definitions)->map(function (array $definition, string $code) use ($byCode): RoomLayout {
            $room = $byCode->get($code);
            if (! $room) {
                throw new RuntimeException("Không tìm thấy phòng {$code} để tạo layout.");
            }
            $draft = $this->createBlankDraft(
                $room,
                null,
                $definition['rows'],
                $definition['columns'],
                $definition['screen_position']
            );
            $this->saveDraft($draft, $definition);

            return $this->publish($draft);
        });
    }

    private function validateDimensions(int $rows, int $columns, string $screenPosition): void
    {
        if ($rows < 1 || $rows > self::MAX_ROWS || $columns < 1 || $columns > self::MAX_COLUMNS
            || $rows * $columns > self::MAX_CELLS || ! in_array($screenPosition, ['top', 'bottom'], true)) {
            throw ValidationException::withMessages(['layout' => 'Layout phải trong giới hạn 30 × 40, tối đa 1200 ô và screen top/bottom.']);
        }
    }

    private function assertOperationalRoom(Room $room): void
    {
        if ($room->status !== 'active') {
            throw ValidationException::withMessages(['room' => 'Không thể quản lý layout của phòng ngừng hoạt động.']);
        }
    }

    private function seatTypeIds(): array
    {
        $rows = DB::table('seat_types')->where('status', true)->get();
        $aliases = [
            'normal' => ['normal', 'regular', 'standard', 'thường'],
            'vip' => ['vip'],
            'couple' => ['couple', 'sweetbox', 'double'],
        ];
        $resolved = [];
        foreach ($aliases as $type => $candidates) {
            $match = $rows->first(function (object $row) use ($candidates): bool {
                $values = array_map(fn ($value) => mb_strtolower(trim((string) $value)), [$row->code, $row->slug, $row->name]);

                return collect($values)->contains(fn ($value) => in_array($value, $candidates, true));
            });
            if (! $match) {
                throw new RuntimeException("Thiếu seat_type cho loại {$type}.");
            }
            $resolved[$type] = $match->id;
        }

        return $resolved;
    }

    private function payloadFromLayout(RoomLayout $layout): array
    {
        return [
            'name' => $layout->name,
            'rows' => $layout->rows,
            'columns' => $layout->columns,
            'screen_position' => $layout->screen_position,
            'cells' => $layout->cells->map(function (RoomLayoutCell $cell): array {
                if ($cell->cell_type === 'aisle') {
                    return ['kind' => 'aisle', 'x' => $cell->x_position, 'y' => $cell->y_position];
                }

                return [
                    'kind' => $cell->seat->type,
                    'x' => $cell->x_position,
                    'y' => $cell->y_position,
                    'seat_code' => $cell->seat->seat_code,
                    'row' => $cell->seat->row,
                    'number' => $cell->seat->number,
                    'type' => $cell->seat->type,
                    'status' => $cell->seat->status,
                    'pair_code' => $cell->seat->pair_code,
                    'pair_position' => $cell->seat->pair_position,
                ];
            })->all(),
        ];
    }

    private function wideLayoutDefinition(): array
    {
        return $this->rectangularDefinition('P01 Layout rộng', 11, 13, [7], function (int $row): string {
            return $row <= 3 ? 'normal' : ($row <= 10 ? 'vip' : 'couple');
        });
    }

    private function twoAisleLayoutDefinition(): array
    {
        return $this->rectangularDefinition('P02 Hai lối đi', 8, 14, [5, 10], function (int $row): string {
            return $row <= 2 ? 'normal' : ($row <= 7 ? 'vip' : 'couple');
        }, 'F6');
    }

    private function irregularLayoutDefinition(): array
    {
        $definition = $this->rectangularDefinition('P03 Bất quy tắc', 9, 13, [7], function (int $row): string {
            return $row <= 3 ? 'normal' : ($row <= 8 ? 'vip' : 'couple');
        }, 'F6');
        $definition['cells'] = array_values(array_filter($definition['cells'], function (array $cell): bool {
            if (($cell['kind'] ?? null) === 'aisle' || ($cell['y'] ?? 0) > 2) {
                return true;
            }

            return ! in_array($cell['x'], [1, 13], true);
        }));
        foreach ($definition['cells'] as &$cell) {
            if (($cell['kind'] ?? null) !== 'aisle' && $cell['y'] <= 2) {
                $cell['number'] = $cell['x'] < 7 ? $cell['x'] - 1 : $cell['x'] - 2;
                $cell['seat_code'] = $cell['row'].$cell['number'];
            }
        }
        unset($cell);

        return $definition;
    }

    private function rectangularDefinition(
        string $name,
        int $rows,
        int $columns,
        array $aisles,
        callable $typeForRow,
        ?string $maintenanceCode = null
    ): array {
        $cells = [];
        for ($y = 1; $y <= $rows; $y++) {
            $row = chr(64 + $y);
            $number = 0;
            for ($x = 1; $x <= $columns; $x++) {
                if (in_array($x, $aisles, true)) {
                    $cells[] = ['kind' => 'aisle', 'x' => $x, 'y' => $y];

                    continue;
                }
                $number++;
                $type = $typeForRow($y);
                $code = $row.$number;
                $pairNumber = (int) ceil($number / 2);
                $cells[] = [
                    'kind' => $type,
                    'type' => $type,
                    'x' => $x,
                    'y' => $y,
                    'row' => $row,
                    'number' => $number,
                    'seat_code' => $code,
                    'status' => $code === $maintenanceCode ? 'maintenance' : 'active',
                    'pair_code' => $type === 'couple' ? "{$row}-PAIR-{$pairNumber}" : null,
                    'pair_position' => $type === 'couple' ? ($number % 2 === 1 ? 'left' : 'right') : null,
                ];
            }
        }

        return compact('name', 'rows', 'columns', 'cells') + ['screen_position' => 'top'];
    }
}
