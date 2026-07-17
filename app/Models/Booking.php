<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    protected $fillable = [
        'user_id',
        'showtime_id',
        'booking_code',
        'total_amount',
        'payment_status',
        'booking_status',
        'used_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function showtime(): BelongsTo
    {
        return $this->belongsTo(Showtime::class);
    }

    public function bookingSeats(): HasMany
    {
        return $this->hasMany(BookingSeat::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
    public function getFormattedTotalAttribute(): string
{
    return number_format($this->total_amount, 0, ',', '.') . ' VND';
}

public function isPaid(): bool
{
    return $this->payment_status === 'paid';
}

public function isUsed(): bool
{
    return !is_null($this->used_at);
}
}