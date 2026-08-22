<?php

namespace App\Models;

use App\Services\Tickets\TicketArtifactProvisioner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class BookingSeat extends Model
{
    public const ACTIVE_LOCK_KEY = 'ACTIVE';

    protected $fillable = [
        'booking_id',
        'showtime_id',
        'seat_id',
        'showtime_ticket_price_id',
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
        'showtime_ticket_price_id' => 'integer',
        'base_amount' => 'integer',
        'surcharge_total' => 'integer',
        'final_unit_amount' => 'integer',
        'pricing_breakdown' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (BookingSeat $bookingSeat): void {
            $seatTypeId = Seat::query()->whereKey($bookingSeat->seat_id)->value('seat_type_id');
            $source = ShowtimeTicketPrice::query()
                ->where('showtime_id', $bookingSeat->showtime_id)
                ->where('seat_type_id', $seatTypeId)
                ->first();
            if ($bookingSeat->showtime_ticket_price_id !== null
                && (! $source || (int) $bookingSeat->showtime_ticket_price_id !== (int) $source->id)) {
                throw new LogicException('BookingSeat price source must match its Showtime and logical SeatType.');
            }
            if ($source) {
                $bookingSeat->showtime_ticket_price_id = $source->id;
            }
        });
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

    public function showtimeTicketPrice(): BelongsTo
    {
        return $this->belongsTo(ShowtimeTicketPrice::class);
    }

    public function admissionTicket(): HasOne
    {
        return $this->hasOne(AdmissionTicket::class);
    }
}
