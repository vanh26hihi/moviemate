<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DiscountCode extends Model
{
    protected $fillable = ['code', 'name', 'description', 'discount_type', 'discount_value', 'maximum_discount_amount',
        'minimum_order_amount', 'starts_at', 'ends_at', 'is_active', 'total_quota', 'per_user_quota',
        'registered_users_only', 'first_order_only', 'can_combine', 'priority', 'created_by_user_id', 'updated_by_user_id', 'archived_at'];

    protected function casts(): array
    {
        return ['discount_value' => 'integer', 'maximum_discount_amount' => 'integer', 'minimum_order_amount' => 'integer',
            'total_quota' => 'integer', 'per_user_quota' => 'integer', 'is_active' => 'boolean', 'registered_users_only' => 'boolean',
            'first_order_only' => 'boolean', 'can_combine' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'archived_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        self::saving(fn (DiscountCode $code) => $code->code = mb_strtoupper(trim($code->code)));
        self::deleting(fn () => throw new \LogicException('Mã giảm giá chỉ có thể được lưu trữ.'));
    }

    public function cinemas(): BelongsToMany
    {
        return $this->belongsToMany(Cinema::class, 'discount_code_cinema');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(BookingDiscountCode::class);
    }
}
