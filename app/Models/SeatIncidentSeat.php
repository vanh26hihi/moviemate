<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SeatIncidentSeat extends Model
{
    public const ACTIVE_LOCK_KEY = 'ACTIVE';

    protected $fillable = ['seat_incident_id', 'seat_id', 'active_lock_key'];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(SeatIncident::class, 'seat_incident_id');
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }
}
