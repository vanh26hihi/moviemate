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
        'pricing_unit_key',
        'pricing_unit_label',
        'seat_type_snapshot',
        'base_amount',
        'surcharge_total',
        'final_unit_amount',
        'pricing_breakdown',
        'pricing_fingerprint',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'showtime_id' => 'integer',
        'base_amount' => 'integer',
        'surcharge_total' => 'integer',
        'final_unit_amount' => 'integer',
        'pricing_breakdown' => 'array',
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
