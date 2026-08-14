<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BookingPromotion extends Model
{
    public const STATUS_RESERVED = 'reserved';

    public const STATUS_REDEEMED = 'redeemed';

    public const STATUS_RELEASED = 'released';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'discount_amount_vnd_snapshot' => 'integer', 'discount_percent_snapshot' => 'integer',
            'maximum_discount_vnd_snapshot' => 'integer', 'minimum_order_vnd_snapshot' => 'integer',
            'eligible_cinemas_snapshot' => 'array', 'registered_users_only_snapshot' => 'boolean',
            'first_order_only_snapshot' => 'boolean', 'global_usage_limit_snapshot' => 'integer',
            'per_user_usage_limit_snapshot' => 'integer', 'applied_discount_vnd' => 'integer',
            'gross_before_vnd' => 'integer', 'final_after_vnd' => 'integer',
            'reserved_at' => 'datetime', 'redeemed_at' => 'datetime', 'released_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
