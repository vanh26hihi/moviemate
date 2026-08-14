<?php

namespace App\Models;

use App\Support\StatusLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Seat extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_MAINTENANCE = 'maintenance';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_RETIRED = 'retired';

    public const OPERATIONAL_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_MAINTENANCE,
        self::STATUS_INACTIVE,
    ];

    public const TYPES = ['normal', 'vip', 'couple'];

    protected $fillable = [
        'room_id',
        'row',
        'number',
        'seat_code',
        'type',
        'seat_type_id',
        'pair_code',
        'pair_position',
        'row_label',
        'seat_number',
        'x_position',
        'y_position',
        'is_center',
        'status',
    ];

    protected $casts = [
        'number' => 'integer',
        'seat_number' => 'integer',
        'x_position' => 'integer',
        'y_position' => 'integer',
        'is_center' => 'boolean',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function seatType(): BelongsTo
    {
        return $this->belongsTo(SeatType::class);
    }

    public function bookingSeats(): HasMany
    {
        return $this->hasMany(BookingSeat::class);
    }

    public function layoutCells(): HasMany
    {
        return $this->hasMany(RoomLayoutCell::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return StatusLabel::for('seat', $this->status);
    }

    public function getTypeLabelAttribute(): string
    {
        return StatusLabel::for('seat_type', $this->type);
    }
}
