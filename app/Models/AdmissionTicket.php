<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class AdmissionTicket extends Model
{
    protected $fillable = ['booking_id', 'booking_seat_id', 'ticket_code'];

    protected function casts(): array
    {
        return [
            'print_count' => 'integer',
            'last_printed_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function bookingSeat(): BelongsTo
    {
        return $this->belongsTo(BookingSeat::class);
    }

    public function printState(): HasOne
    {
        return $this->hasOne(BookingTicketPrint::class);
    }

    public function lastPrintedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_printed_by_user_id');
    }

    public function getSeatCodeAttribute(): string
    {
        $seat = $this->bookingSeat?->seat;

        return $seat ? $seat->row.$seat->number : '—';
    }
}
