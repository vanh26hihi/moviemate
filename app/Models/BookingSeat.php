<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingSeat extends Model
{
    public const ACTIVE_LOCK_KEY = 'ACTIVE';

    protected $fillable = [
        'booking_id',
        'showtime_id',
        'seat_id',
        'active_lock_key',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'showtime_id' => 'integer',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    public function showtime(): BelongsTo
    {
        return $this->belongsTo(Showtime::class);
    }
}
