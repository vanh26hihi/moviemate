<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    public const QR_CODE_SIZE = 200;

    protected $fillable = [
        'user_id',
        'customer_email',
        'showtime_id',
        'booking_code',
        'total_amount',
        'payment_status',
        'booking_status',
        'used_at',
    ];

    protected $casts = [
        'used_at'      => 'datetime',
        'total_amount' => 'decimal:2',
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

    public function getQrCodeUrlAttribute(): string
    {
        return sprintf(
            'https://api.qrserver.com/v1/create-qr-code/?size=%1$dx%1$d&data=%2$s',
            self::QR_CODE_SIZE,
            rawurlencode($this->booking_code)
        );
    }
}
