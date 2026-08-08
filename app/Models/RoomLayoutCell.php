<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class RoomLayoutCell extends Model
{
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
            $layout = $cell->layout()->first();
            if (! $layout) {
                throw new LogicException('Ô sơ đồ phải thuộc một sơ đồ ghế.');
            }
            if ($cell->x_position < 1 || $cell->x_position > $layout->columns
                || $cell->y_position < 1 || $cell->y_position > $layout->rows) {
                throw new LogicException('Tọa độ ô nằm ngoài giới hạn sơ đồ ghế.');
            }
            if ($cell->cell_type === 'aisle' && $cell->seat_id !== null) {
                throw new LogicException('Ô lối đi không thể tham chiếu đến ghế.');
            }
            if ($cell->cell_type === 'seat') {
                $seat = $cell->seat()->first();
                if (! $seat || $seat->room_id !== $layout->room_id) {
                    throw new LogicException('Ô ghế phải tham chiếu đến một ghế trong đúng phòng.');
                }
            }
        });

        $assertMutable = function (RoomLayoutCell $cell): void {
            $layout = $cell->layout()->first();
            if ($layout?->status === 'published') {
                throw new LogicException('Không thể chỉnh sửa ô thuộc sơ đồ ghế đã phát hành.');
            }
        };

        static::updating($assertMutable);
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
