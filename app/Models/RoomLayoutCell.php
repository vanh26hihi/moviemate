<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class RoomLayoutCell extends Model
{
    public const TYPE_SEAT = 'seat';

    public const TYPE_AISLE = 'aisle';

    public const TYPE_BLOCKED = 'blocked';

    public const CELL_TYPES = [self::TYPE_SEAT, self::TYPE_AISLE, self::TYPE_BLOCKED];

    protected $fillable = [
        'room_layout_id',
        'x_position',
        'y_position',
        'cell_type',
        'seat_id',
    ];

    protected function casts(): array
    {
        return [
            'x_position' => 'integer',
            'y_position' => 'integer',
            'seat_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (RoomLayoutCell $cell): void {
            $layout = $cell->relationLoaded('layout') && $cell->getRelation('layout')?->id === $cell->room_layout_id
                ? $cell->getRelation('layout')
                : $cell->layout()->first();
            if (! $layout) {
                throw new LogicException('Ô sơ đồ phải thuộc một sơ đồ ghế.');
            }
            if ($layout->status !== RoomLayout::STATUS_DRAFT) {
                throw new LogicException('Chỉ ô thuộc bản nháp sơ đồ ghế mới được chỉnh sửa.');
            }
            if ($cell->exists && $cell->isDirty('room_layout_id')) {
                $originalLayout = RoomLayout::query()->find($cell->getOriginal('room_layout_id'));
                if ($originalLayout?->status !== RoomLayout::STATUS_DRAFT) {
                    throw new LogicException('Chỉ ô thuộc bản nháp sơ đồ ghế mới được chỉnh sửa.');
                }
            }
            if ($cell->x_position < 1 || $cell->x_position > $layout->columns
                || $cell->y_position < 1 || $cell->y_position > $layout->rows) {
                throw new LogicException('Tọa độ ô nằm ngoài giới hạn sơ đồ ghế.');
            }
            if (! in_array($cell->cell_type, self::CELL_TYPES, true)) {
                throw new LogicException('Loại ô sơ đồ không hợp lệ.');
            }
            if (in_array($cell->cell_type, [self::TYPE_AISLE, self::TYPE_BLOCKED], true) && $cell->seat_id !== null) {
                throw new LogicException('Ô cấu trúc không thể tham chiếu đến ghế.');
            }
            if ($cell->cell_type === self::TYPE_SEAT) {
                $seat = $cell->relationLoaded('seat') && $cell->getRelation('seat')?->id === $cell->seat_id
                    ? $cell->getRelation('seat')
                    : $cell->seat()->first();
                if (! $seat || $seat->room_id !== $layout->room_id) {
                    throw new LogicException('Ô ghế phải tham chiếu đến một ghế trong đúng phòng.');
                }
            }
        });

        $assertMutable = function (RoomLayoutCell $cell): void {
            $layout = $cell->relationLoaded('layout') ? $cell->getRelation('layout') : $cell->layout()->first();
            if ($layout?->status !== RoomLayout::STATUS_DRAFT) {
                throw new LogicException('Chỉ ô thuộc bản nháp sơ đồ ghế mới được chỉnh sửa.');
            }
        };

        static::deleting($assertMutable);
    }

    public function layout(): BelongsTo
    {
        return $this->belongsTo(RoomLayout::class, 'room_layout_id');
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }
}
