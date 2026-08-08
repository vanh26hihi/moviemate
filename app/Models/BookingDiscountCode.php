<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BookingDiscountCode extends Model
{
    public const STATUS_RESERVED = 'reserved';

    public const STATUS_REDEEMED = 'redeemed';

    public const STATUS_RELEASED = 'released';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['discount_amount' => 'integer', 'subtotal_before' => 'integer', 'subtotal_after' => 'integer', 'reserved_at' => 'datetime', 'redeemed_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class);
    }
}
