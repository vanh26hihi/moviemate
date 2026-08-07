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
        string $screenPosition = 'top',
        ?string $name = null,
    ): RoomLayout {
        $this->assertOperationalRoom($room);
        $this->validateDimensions($rows, $columns, $screenPosition);

        $name ??= "Sơ đồ phòng {$room->code}";
        $this->assertMeaningfulName($name);

        return DB::transaction(function () use ($room, $userId, $rows, $columns, $screenPosition, $name): RoomLayout {
            Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();
            if (RoomLayout::query()->where('room_id', $room->id)->where('status', 'draft')->exists()) {
                throw ValidationException::withMessages(['layout' => 'Phòng này đã có một bản nháp sơ đồ ghế.']);
            }

            $version = ((int) RoomLayout::query()->where('room_id', $room->id)->max('version')) + 1;

            return RoomLayout::query()->create([
                'room_id' => $room->id,
                'version' => $version,
                'name' => $name,
                'rows' => $rows,
                'columns' => $columns,
                'screen_position' => $screenPosition,
                'status' => 'draft',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        });
    }

    public function clonePublishedToDraft(Room $room, ?int $userId = null, ?string $name = null): RoomLayout
    {
        $published = $this->latestPublishedFor($room);
        if (! $published) {
            return $this->createBlankDraft($room, $userId, name: $name);
        }

        return DB::transaction(function () use ($room, $published, $userId, $name): RoomLayout {
            $draft = $this->createBlankDraft(
                $room,
                $userId,
                $published->rows,
                $published->columns,
                $published->screen_position,
                $name ?? "Điều chỉnh từ {$published->display_name}",
            );

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
        $this->assertMeaningfulName($normalized['name']);

        return DB::transaction(function () use ($layout, $normalized, $userId): RoomLayout {
            $locked = RoomLayout::query()->whereKey($layout->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['layout' => 'Chỉ bản nháp sơ đồ ghế mới được chỉnh sửa.']);
            }
            if ($normalized['expected_updated_at'] !== null
                && $locked->updated_at?->format('Y-m-d H:i:s.u') !== $normalized['expected_updated_at']) {
                throw ValidationException::withMessages([
                    'layout' => 'Hàng ghế đã được thay đổi ở phiên quản trị khác. Hãy tải lại trang trước khi tiếp tục.',
                ]);
            }
            $this->assertBookedSeatSemanticsArePreserved($locked, $normalized['cells']);
            $this->assertShrunkBoundsPreserveCells($locked, $normalized);

            // Update the canvas bounds before creating cells in newly expanded
            // coordinates. The surrounding transaction rolls this back on failure.
            $locked->update([
                'name' => $normalized['name'],
                'rows' => $normalized['rows'],
                'columns' => $normalized['columns'],
                'screen_position' => $normalized['screen_position'],
                'updated_by' => $userId,
            ]);

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

                $attributes = [
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
                ];
                if ($cell['seat_id']) {
                    $seat = Seat::query()
                        ->whereKey($cell['seat_id'])
                        ->where('room_id', $locked->room_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $structural = ['row', 'number', 'type', 'seat_type_id', 'pair_code', 'pair_position', 'row_label', 'seat_number', 'x_position', 'y_position'];
                    $changedStructure = collect($structural)->contains(
                        fn (string $attribute): bool => (string) $seat->{$attribute} !== (string) $attributes[$attribute]
                    );
                    if ($changedStructure && RoomLayoutCell::query()->where('seat_id', $seat->id)
                        ->whereHas('layout', fn ($query) => $query->where('status', 'published'))->exists()) {
                        throw ValidationException::withMessages([
                            'layout' => "Ghế {$seat->seat_code} thuộc sơ đồ đã phát hành nên không thể đổi vị trí, loại hoặc mã. Hãy dùng một mã ghế mới.",
                        ]);
                    }
                    if ($seat->status !== 'active') {
                        $attributes['status'] = $seat->status;
                    }
                    $seat->update($attributes);
                } else {
                    $seat = Seat::query()->create($attributes + [
                        'room_id' => $locked->room_id,
                        'seat_code' => $cell['seat_code'],
                    ]);
                }

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
                ->update(['status' => Seat::STATUS_RETIRED]);

            return $locked->fresh(['cells.seat']);
        });
    }

    public function validateDraft(RoomLayout $layout, array $payload): array
    {
        if ($layout->status !== 'draft') {
            throw ValidationException::withMessages(['layout' => 'Sơ đồ ghế đã phát hành không thể chỉnh sửa.']);
        }

        $rows = filter_var($payload['rows'] ?? null, FILTER_VALIDATE_INT);
        $columns = filter_var($payload['columns'] ?? null, FILTER_VALIDATE_INT);
        $screen = $payload['screen_position'] ?? null;
        $this->validateDimensions($rows ?: 0, $columns ?: 0, (string) $screen);

        if (! is_array($payload['cells'] ?? null) || count($payload['cells']) > self::MAX_CELLS) {
            throw ValidationException::withMessages(['cells' => 'Dữ liệu sơ đồ ghế không hợp lệ hoặc vượt quá 1.200 ô.']);
        }

        $coordinates = [];
        $codes = [];
        $pairs = [];
        $normalized = [];
        $layout->loadMissing('cells.seat');
        $roomSeats = Seat::query()->where('room_id', $layout->room_id)->get()->keyBy('id');
        $roomSeatsByCode = $roomSeats->keyBy('seat_code');
        $layoutSeatIds = $layout->cells->where('cell_type', 'seat')->pluck('seat_id')->filter()->mapWithKeys(
            fn ($id): array => [(int) $id => true]
        );

        foreach ($payload['cells'] as $index => $input) {
            if (! is_array($input)) {
                throw ValidationException::withMessages(["cells.{$index}" => 'Mỗi ô trong sơ đồ phải là một đối tượng dữ liệu hợp lệ.']);
            }

            $kind = strtolower(trim((string) ($input['kind'] ?? $input['cell_type'] ?? '')));
            if ($kind === 'empty') {
                continue;
            }

            $x = filter_var($input['x_position'] ?? $input['x'] ?? null, FILTER_VALIDATE_INT);
            $y = filter_var($input['y_position'] ?? $input['y'] ?? null, FILTER_VALIDATE_INT);
            if (! $x || ! $y || $x > $columns || $y > $rows) {
                throw ValidationException::withMessages(["cells.{$index}" => 'Tọa độ ô nằm ngoài giới hạn sơ đồ ghế.']);
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
            if ($row !== $this->rowLabel($y)) {
                throw ValidationException::withMessages(["cells.{$index}.seat_code" => "Mã ghế {$code} không thuộc hàng {$this->rowLabel($y)}."]);
            }
            if (isset($codes[$code])) {
                throw ValidationException::withMessages(["cells.{$index}.seat_code" => 'Mã ghế bị trùng trong phòng.']);
            }
            if (! in_array($status, ['active', 'maintenance', 'inactive', 'retired'], true)) {
                throw ValidationException::withMessages(["cells.{$index}.status" => 'Trạng thái ghế không hợp lệ.']);
            }
            $codes[$code] = true;

            $seatId = filter_var($input['seat_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
            if ($seatId) {
                $existingSeat = $roomSeats->get($seatId);
                if (! $existingSeat || ! $layoutSeatIds->has($seatId)) {
                    throw ValidationException::withMessages(["cells.{$index}.seat_id" => 'Ghế không thuộc bản nháp đang chỉnh sửa.']);
                }
                if ($existingSeat->seat_code !== $code) {
                    throw ValidationException::withMessages(["cells.{$index}.seat_code" => "Không thể đổi mã ghế {$existingSeat->seat_code} thành {$code}. Mã ghế đã tạo phải được giữ ổn định."]);
                }
            } elseif ($existingSeat = $roomSeatsByCode->get($code)) {
                if (! $layoutSeatIds->has((int) $existingSeat->id)) {
                    throw ValidationException::withMessages(["cells.{$index}.seat_code" => "Mã ghế {$code} đã tồn tại trong phòng."]);
                }
                $seatId = (int) $existingSeat->id;
            }

            $pairCode = $type === 'couple' ? trim((string) ($input['pair_code'] ?? '')) : null;
            $pairPosition = $type === 'couple' ? strtolower(trim((string) ($input['pair_position'] ?? ''))) : null;
            if ($type === 'couple') {
                if ($pairCode === '' || ! in_array($pairPosition, ['left', 'right'], true)) {
                    throw ValidationException::withMessages(["cells.{$index}.pair_code" => 'Ghế đôi phải có mã cặp và vị trí trái/phải.']);
                }
                $pairs[$pairCode][] = compact('row', 'number', 'x', 'y', 'pairPosition', 'status', 'index');
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
                'seat_id' => $seatId,
                'source_index' => $index,
            ];
        }

        foreach ($pairs as $pairCode => $pair) {
            $byPosition = collect($pair)->keyBy('pairPosition');
            $valid = count($pair) === 2
                && $pair[0]['row'] === $pair[1]['row']
                && $pair[0]['y'] === $pair[1]['y']
                && abs($pair[0]['number'] - $pair[1]['number']) === 1
                && abs($pair[0]['x'] - $pair[1]['x']) === 1
                && $byPosition->keys()->sort()->values()->all() === ['left', 'right']
                && $byPosition->get('left')['x'] < $byPosition->get('right')['x']
                && $byPosition->get('left')['number'] < $byPosition->get('right')['number']
                && collect($pair)->pluck('status')->unique()->count() === 1;
            if (! $valid) {
                $message = "Cặp {$pairCode} phải có đúng hai ghế liền nhau, cùng hàng, không bị ngăn bởi lối đi và đủ vị trí trái/phải.";
                $messages = collect($pair)
                    ->mapWithKeys(fn (array $member): array => ["cells.{$member['index']}" => $message])
                    ->all();
                throw ValidationException::withMessages($messages ?: ['cells' => $message]);
            }
        }

        return [
            'name' => trim((string) ($payload['name'] ?? $layout->name)) ?: null,
            'rows' => $rows,
            'columns' => $columns,
            'screen_position' => $screen,
            'expected_updated_at' => isset($payload['expected_updated_at'])
                ? trim((string) $payload['expected_updated_at'])
                : null,
            'cells' => $normalized,
        ];
    }

    public function publish(RoomLayout $layout, ?int $userId = null): RoomLayout
    {
        $this->assertMeaningfulName($layout->name);

        return DB::transaction(function () use ($layout, $userId): RoomLayout {
            $locked = RoomLayout::query()->with('cells.seat')->whereKey($layout->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['layout' => 'Chỉ bản nháp sơ đồ ghế mới được phát hành.']);
            }
            if ($locked->cells->where('cell_type', 'seat')->isEmpty()) {
                throw ValidationException::withMessages(['layout' => 'Sơ đồ phải có ít nhất một ghế.']);
            }

            $this->validateDraft($locked, $this->payloadFromLayout($locked));
            $locked->update([
                'status' => 'published',
                'published_at' => now(),
                'updated_by' => $userId,
            ]);
            $usableCapacity = $locked->cells()
                ->where('cell_type', 'seat')
                ->whereHas('seat', fn ($query) => $query->where('status', 'active'))
                ->count();
            $locked->room()->update(['total_seats' => $usableCapacity]);
            $this->retireSeatsAbsentFromPublishedLayout($locked);

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
            throw ValidationException::withMessages(['showtime' => 'Suất chiếu không có sơ đồ ghế đã phát hành hợp lệ.']);
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
            throw ValidationException::withMessages(['layout' => 'Sơ đồ phải trong giới hạn 30 × 40, tối đa 1.200 ô và màn hình ở phía trên hoặc phía dưới.']);
        }
    }

    private function assertOperationalRoom(Room $room): void
    {
        if ($room->status !== 'active') {
            throw ValidationException::withMessages(['room' => 'Không thể chỉnh sửa sơ đồ ghế của phòng ngừng hoạt động.']);
        }
    }

    /** @param array<int, array<string, mixed>> $cells */
    private function assertBookedSeatSemanticsArePreserved(RoomLayout $layout, array $cells): void
    {
        $submitted = collect($cells)
            ->where('cell_type', 'seat')
            ->filter(fn (array $cell): bool => (int) ($cell['seat_id'] ?? 0) > 0)
            ->keyBy('seat_id');
        $historicalSeats = Seat::query()
            ->whereHas('layoutCells', fn ($query) => $query->where('room_layout_id', $layout->id))
            ->whereHas('bookingSeats')
            ->lockForUpdate()
            ->get();

        foreach ($historicalSeats as $seat) {
            $replacement = $submitted->get($seat->id);
            if (! $replacement) {
                throw ValidationException::withMessages([
                    'cells' => "Không thể xóa ghế {$seat->seat_code} vì ghế đã có lịch sử đặt vé.",
                ]);
            }

            $changedPairSemantics = (string) $replacement['type'] !== (string) $seat->type
                || (string) ($replacement['pair_code'] ?? '') !== (string) ($seat->pair_code ?? '')
                || (string) ($replacement['pair_position'] ?? '') !== (string) ($seat->pair_position ?? '');
            if ($changedPairSemantics) {
                $index = (int) ($replacement['source_index'] ?? 0);
                throw ValidationException::withMessages([
                    "cells.{$index}" => "Ghế {$seat->seat_code} đã có lịch sử đặt vé nên không thể đổi cấu trúc ghế đơn/ghế đôi. Bạn chỉ có thể đổi trạng thái an toàn của ghế.",
                ]);
            }
        }
    }

    /** @param array{name: ?string, rows: int, columns: int, cells: array<int, array<string, mixed>>} $normalized */
    private function assertShrunkBoundsPreserveCells(RoomLayout $layout, array $normalized): void
    {
        if ($normalized['rows'] >= $layout->rows && $normalized['columns'] >= $layout->columns) {
            return;
        }

        $layout->loadMissing('cells');
        $outside = $layout->cells->filter(fn (RoomLayoutCell $cell): bool => (
            $cell->x_position > $normalized['columns'] || $cell->y_position > $normalized['rows']
        ));
        if ($outside->isEmpty()) {
            return;
        }

        $submittedSeatIds = collect($normalized['cells'])->pluck('seat_id')->filter()->map(fn ($id): int => (int) $id);
        $oldAisles = $layout->cells->where('cell_type', 'aisle')->count();
        $submittedAisles = collect($normalized['cells'])->where('cell_type', 'aisle')->count();
        $lostSeat = $outside->where('cell_type', 'seat')->contains(
            fn (RoomLayoutCell $cell): bool => ! $submittedSeatIds->contains((int) $cell->seat_id)
        );
        $lostAisle = $outside->where('cell_type', 'aisle')->isNotEmpty() && $submittedAisles < $oldAisles;

        if ($lostSeat || $lostAisle) {
            throw ValidationException::withMessages([
                'layout' => 'Không thể thu nhỏ vùng thiết kế vì thao tác sẽ làm mất ghế hoặc lối đi. Hãy di chuyển hoặc xóa rõ ràng rồi lưu trước.',
            ]);
        }
    }

    public function seatTypeIds(): array
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

    public function assertMeaningfulName(?string $name): void
    {
        $name = trim((string) $name);
        if (mb_strlen($name) < 5 || preg_match('/^(sơ đồ\s+)?(phiên bản|version)\s*\d+$/iu', $name)) {
            throw ValidationException::withMessages([
                'name' => 'Hãy đặt tên mô tả mục đích của sơ đồ, ví dụ “Tiêu chuẩn 100 ghế – mùa hè”.',
            ]);
        }
    }

    private function retireSeatsAbsentFromPublishedLayout(RoomLayout $layout): void
    {
        $currentSeatIds = $layout->cells()->where('cell_type', 'seat')->pluck('seat_id');
        $now = now();
        $protectedByFutureShowtime = DB::table('room_layout_cells')
            ->join('showtimes', 'showtimes.room_layout_id', '=', 'room_layout_cells.room_layout_id')
            ->where('showtimes.room_id', $layout->room_id)
            ->where('showtimes.status', 'active')
            ->where(function ($query) use ($now): void {
                $query->whereDate('showtimes.show_date', '>', $now->toDateString())
                    ->orWhere(function ($query) use ($now): void {
                        $query->whereDate('showtimes.show_date', $now->toDateString())
                            ->whereTime('showtimes.show_time', '>=', $now->format('H:i:s'));
                    });
            })
            ->whereNotNull('room_layout_cells.seat_id')
            ->select('room_layout_cells.seat_id');

        Seat::query()->where('room_id', $layout->room_id)
            ->where('status', Seat::STATUS_ACTIVE)
            ->whereNotIn('id', $currentSeatIds)
            ->whereNotIn('id', $protectedByFutureShowtime)
            ->update(['status' => Seat::STATUS_RETIRED]);
    }

    private function rowLabel(int $index): string
    {
        return $index <= 26
            ? chr(64 + $index)
            : 'A'.chr(64 + $index - 26);
    }

    private function payloadFromLayout(RoomLayout $layout): array
    {
        return [
            'schema_version' => 3,
            'expected_updated_at' => $layout->updated_at?->format('Y-m-d H:i:s.u'),
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
                    'seat_id' => $cell->seat_id,
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
        return $this->rectangularDefinition('P01 Sơ đồ rộng', 11, 13, [7], function (int $row): string {
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
