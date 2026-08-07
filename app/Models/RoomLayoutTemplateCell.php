<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomLayoutTemplateCell extends Model
{
    public const TYPE_SEAT = 'seat';

    public const TYPE_AISLE = 'aisle';

    public const CELL_TYPES = [self::TYPE_SEAT, self::TYPE_AISLE];

    protected $fillable = [
        'room_layout_template_id', 'x_position', 'y_position', 'cell_type', 'seat_type',
        'seat_label', 'seat_unit_key', 'pair_key', 'metadata',
    ];

    protected function casts(): array
    {
        return ['x_position' => 'integer', 'y_position' => 'integer', 'metadata' => 'array'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(RoomLayoutTemplate::class, 'room_layout_template_id');
    }
}
