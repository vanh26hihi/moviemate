<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BookingPointRedemption extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['points' => 'integer', 'point_value_vnd_snapshot' => 'integer', 'discount_amount' => 'integer', 'reserved_at' => 'datetime', 'redeemed_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LoyaltyAccount::class, 'loyalty_account_id');
    }
}
