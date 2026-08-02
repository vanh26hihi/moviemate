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
        'voucher_id',
        'booking_code',
        'total_amount',
        'loyalty_points_earned',
        'loyalty_points_redeemed',
        'voucher_code',
        'discount_amount',
        'point_discount_amount',
        'payment_status',
        'booking_status',
        'hold_expires_at',
        'used_at',
    ];

    protected $casts = [
        'used_at'      => 'datetime',
        'total_amount' => 'decimal:2',
        'loyalty_points_earned' => 'integer',
        'loyalty_points_redeemed' => 'integer',
        'discount_amount' => 'decimal:2',
        'point_discount_amount' => 'decimal:2',
        'hold_expires_at' => 'datetime',
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

    public function foodOrder(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function loyaltyPointTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyPointTransaction::class);
    }
}
