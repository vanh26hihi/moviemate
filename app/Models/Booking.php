<?php

namespace App\Models;

use Carbon\Carbon;
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
        'guest_access_token_hash',
        'checkout_idempotency_key_hash',
        'showtime_id',
        'booking_code',
        'total_amount',
        'payment_status',
        'booking_status',
        'expires_at',
        'paid_at',
        'used_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'ticket_emailed_at' => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    protected $hidden = [
        'guest_access_token_hash',
        'checkout_idempotency_key_hash',
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
        // Backward-compatible singular access now resolves the newest attempt.
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getQrCodeUrlAttribute(): string
    {
        return sprintf(
            'https://api.qrserver.com/v1/create-qr-code/?size=%1$dx%1$d&data=%2$s',
            self::QR_CODE_SIZE,
            rawurlencode($this->booking_code)
        );
    }

    public function getRecipientEmailAttribute(): ?string
    {
        return $this->customer_email ?: $this->user?->email;
    }

    public function getSeatCodesAttribute(): string
    {
        return $this->bookingSeats
            ->pluck('seat.seat_code')
            ->filter()
            ->join(', ');
    }

    public function getShowtimeLabelAttribute(): string
    {
        if (! $this->showtime?->show_date || ! $this->showtime?->show_time) {
            return 'Đang cập nhật';
        }

        return $this->showtime->show_date->format('d/m/Y').' '
            .Carbon::parse($this->showtime->show_time)->format('H:i');
    }
}
