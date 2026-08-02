<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $fillable = [
        'cinema_id',
        'name',
        'room_type',
        'layout_style',
        'total_seats',
        'status',
    ];

    public function seatRowOffset(int $rowIndex, int $rowCount): int
    {
        return match ($this->layout_style) {
            'staggered' => $rowIndex % 2 === 0 ? 0 : 18,
            'curved' => (int) round(abs(($rowCount - 1) / 2 - $rowIndex) * 12),
            default => 0,
        };
    }

    protected $casts = [
        'total_seats' => 'integer',
    ];

    public function cinema(): BelongsTo
    {
        return $this->belongsTo(Cinema::class);
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }

    public function showtimes(): HasMany
    {
        return $this->hasMany(Showtime::class);
    }
}
