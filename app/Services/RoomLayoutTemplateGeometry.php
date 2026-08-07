<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class RoomLayoutTemplateGeometry
{
    public function normalize(array $payload): array
    {
        $rows = filter_var($payload['rows'] ?? null, FILTER_VALIDATE_INT);
        $columns = filter_var($payload['columns'] ?? null, FILTER_VALIDATE_INT);
        $screen = (string) ($payload['screen_position'] ?? 'top');
        if (! $rows || ! $columns || $rows > RoomLayoutService::MAX_ROWS || $columns > RoomLayoutService::MAX_COLUMNS
            || $rows * $columns > RoomLayoutService::MAX_CELLS || ! in_array($screen, ['top', 'bottom'], true)) {
            throw ValidationException::withMessages(['layout' => 'Kích thước hoặc vị trí màn hình không hợp lệ.']);
        }

        $input = $payload['cells'] ?? null;
        if (! is_array($input) || count($input) > RoomLayoutService::MAX_CELLS) {
            throw ValidationException::withMessages(['layout' => 'Danh sách ô sơ đồ không hợp lệ.']);
        }

        $cells = [];
        $coordinates = [];
        $labels = [];
        $pairs = [];
        foreach ($input as $index => $cell) {
            if (! is_array($cell)) {
                throw ValidationException::withMessages(['layout' => "Ô thứ {$index} không hợp lệ."]);
            }
            $x = filter_var($cell['x_position'] ?? $cell['x'] ?? null, FILTER_VALIDATE_INT);
            $y = filter_var($cell['y_position'] ?? $cell['y'] ?? null, FILTER_VALIDATE_INT);
            $type = (string) ($cell['cell_type'] ?? 'seat');
            if (! $x || ! $y || $x > $columns || $y > $rows || ! in_array($type, ['seat', 'aisle'], true)) {
                throw ValidationException::withMessages(['layout' => "Tọa độ ô thứ {$index} không hợp lệ."]);
            }
            $coordinate = "{$x}:{$y}";
            if (isset($coordinates[$coordinate])) {
                throw ValidationException::withMessages(['layout' => "Tọa độ {$coordinate} bị trùng."]);
            }
            $coordinates[$coordinate] = true;

            if ($type === 'aisle') {
                $cells[] = ['x_position' => $x, 'y_position' => $y, 'cell_type' => 'aisle', 'seat_type' => null,
                    'seat_label' => null, 'seat_unit_key' => null, 'pair_key' => null, 'metadata' => null];

                continue;
            }

            $seatType = (string) ($cell['seat_type'] ?? $cell['type'] ?? 'normal');
            $label = strtoupper(trim((string) ($cell['seat_label'] ?? $cell['seat_code'] ?? '')));
            if (! in_array($seatType, ['normal', 'vip', 'couple'], true)
                || ! preg_match('/^[A-Z]{1,2}[1-9][0-9]?$/', $label)) {
                throw ValidationException::withMessages(['layout' => "Loại hoặc nhãn ghế tại {$coordinate} không hợp lệ."]);
            }
            if (isset($labels[$label])) {
                throw ValidationException::withMessages(['layout' => "Nhãn ghế {$label} bị trùng."]);
            }
            $labels[$label] = true;
            preg_match('/^([A-Z]{1,2})([1-9][0-9]?)$/', $label, $parts);
            if ($parts[1] !== $this->rowLabel($y)) {
                throw ValidationException::withMessages(['layout' => "Nhãn ghế {$label} không thuộc hàng ".$this->rowLabel($y).'.']);
            }
            $pairKey = $seatType === 'couple' ? trim((string) ($cell['pair_key'] ?? '')) : null;
            if ($seatType === 'couple' && $pairKey === '') {
                throw ValidationException::withMessages(['layout' => "Ghế đôi {$label} thiếu mã cặp."]);
            }
            if ($pairKey) {
                $pairs[$pairKey][] = ['x' => $x, 'y' => $y, 'index' => count($cells)];
            }
            $cells[] = ['x_position' => $x, 'y_position' => $y, 'cell_type' => 'seat', 'seat_type' => $seatType,
                'seat_label' => $label, 'seat_unit_key' => $pairKey ?: $label, 'pair_key' => $pairKey,
                'metadata' => ['row' => $parts[1], 'number' => (int) $parts[2]]];
        }

        foreach ($pairs as $key => $members) {
            if (count($members) !== 2 || $members[0]['y'] !== $members[1]['y'] || abs($members[0]['x'] - $members[1]['x']) !== 1) {
                throw ValidationException::withMessages(['layout' => "Cặp ghế {$key} phải gồm đúng hai ghế liền kề."]);
            }
            usort($members, fn (array $a, array $b): int => $a['x'] <=> $b['x']);
            $cells[$members[0]['index']]['metadata']['pair_position'] = 'left';
            $cells[$members[1]['index']]['metadata']['pair_position'] = 'right';
        }

        return ['rows' => $rows, 'columns' => $columns, 'screen_position' => $screen, 'cells' => $cells];
    }

    private function rowLabel(int $index): string
    {
        $label = '';
        while ($index > 0) {
            $index--;
            $label = chr(65 + ($index % 26)).$label;
            $index = intdiv($index, 26);
        }

        return $label;
    }
}
