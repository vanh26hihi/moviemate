<?php

namespace App\Models;

use App\Services\Tickets\TicketArtifactProvisioner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    protected static function booted(): void
    {
        static::created(function (BookingSeat $bookingSeat): void {
            app(TicketArtifactProvisioner::class)->provisionSeat($bookingSeat);
        });
    }

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

    public function admissionTicket(): HasOne
    {
        return $this->hasOne(AdmissionTicket::class);
    }
}
