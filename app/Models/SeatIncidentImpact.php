<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SeatIncidentImpact extends Model
{
    public const ORDINARY_HOLD = 'ordinary_hold';

    public const RETAINED_PAYMENT = 'retained_payment';

    public const PAID = 'paid';

    public const RELEASED = 'released';

    public const RESOLUTION_UNRESOLVED = 'unresolved';

    public const RESOLUTION_RESOLVED = 'resolved';

    protected $fillable = [
        'seat_incident_id', 'booking_seat_id', 'detected_classification', 'resolution_status',
        'detected_at', 'resolved_at', 'resolution_reason',
    ];

    protected $casts = ['detected_at' => 'datetime', 'resolved_at' => 'datetime'];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(SeatIncident::class, 'seat_incident_id');
    }

    public function bookingSeat(): BelongsTo
    {
        return $this->belongsTo(BookingSeat::class);
    }
}
