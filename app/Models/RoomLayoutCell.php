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
                throw new LogicException('Layout cell must belong to a layout.');
            }
            if ($cell->x_position < 1 || $cell->x_position > $layout->columns
                || $cell->y_position < 1 || $cell->y_position > $layout->rows) {
                throw new LogicException('Layout cell coordinate is out of bounds.');
            }
            if ($cell->cell_type === 'aisle' && $cell->seat_id !== null) {
                throw new LogicException('Aisle cells cannot reference a seat.');
            }
            if ($cell->cell_type === 'seat') {
                $seat = $cell->seat()->first();
                if (! $seat || $seat->room_id !== $layout->room_id) {
                    throw new LogicException('Seat cells must reference a seat in the layout room.');
                }
            }
        });

        $assertMutable = function (RoomLayoutCell $cell): void {
            $layout = $cell->layout()->first();
            if ($layout?->status === 'published') {
                throw new LogicException('Published room layout cells are immutable.');
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
