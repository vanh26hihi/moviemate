<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    protected $fillable = [
        'user_id',
        'customer_email',
        'guest_access_token_hash',
        'guest_access_expires_at',
        'checkout_idempotency_key_hash',
        'checkout_request_fingerprint_hash',
        'showtime_id',
        'booking_code',
        'total_amount',
        'seat_subtotal',
        'food_subtotal',
        'currency',
        'payment_status',
        'booking_status',
        'expires_at',
        'paid_at',
        'used_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
        'guest_access_expires_at' => 'datetime',
        'ticket_email_token_expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'ticket_emailed_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'seat_subtotal' => 'integer',
        'food_subtotal' => 'integer',
    ];

    protected $hidden = [
        'guest_access_token_hash',
        'ticket_email_token_nonce',
        'ticket_email_token_hash',
        'checkout_idempotency_key_hash',
        'checkout_request_fingerprint_hash',
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

    public function foodOrder(): HasOne
    {
        return $this->hasOne(Order::class);
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

    public function getStatusLabelAttribute(): string
    {
        return match ($this->booking_status) {
            'paid' => 'Chưa sử dụng',
            'used' => 'Đã sử dụng',
            'cancelled' => 'Đã hủy',
            'expired' => 'Hết hạn',
            default => 'Đang xử lý',
        };
    }

    public function getFormattedTotalAttribute(): string
    {
        return number_format((float) $this->total_amount, 0, ',', '.').'đ';
    }

    public function getMovieTitleAttribute(): string
    {
        return $this->showtime?->movie?->title ?: 'Phim đang cập nhật';
    }
}
