<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class FoodPickupVoucherPrintEvent extends Model
{
    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['printed_at' => 'immutable_datetime', 'print_number' => 'integer'];
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Food pickup voucher print events are append-only.'));
        self::deleting(fn () => throw new LogicException('Food pickup voucher print events are append-only.'));
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(FoodPickupVoucher::class, 'food_pickup_voucher_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
