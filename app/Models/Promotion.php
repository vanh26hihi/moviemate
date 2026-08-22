<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class Promotion extends Model
{
    public const TYPE_FIXED = 'fixed';

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPES = [self::TYPE_FIXED, self::TYPE_PERCENTAGE];

    protected $fillable = [
        'code', 'name', 'description', 'type', 'discount_amount_vnd', 'discount_percent',
        'maximum_discount_vnd', 'minimum_order_vnd', 'starts_at', 'ends_at', 'is_active',
        'global_usage_limit', 'per_user_usage_limit', 'registered_users_only', 'first_order_only',
        'created_by_user_id', 'updated_by_user_id', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'discount_amount_vnd' => 'integer', 'discount_percent' => 'integer',
            'maximum_discount_vnd' => 'integer', 'minimum_order_vnd' => 'integer',
            'global_usage_limit' => 'integer', 'per_user_usage_limit' => 'integer',
            'is_active' => 'boolean', 'registered_users_only' => 'boolean', 'first_order_only' => 'boolean',
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::saving(function (Promotion $promotion): void {
            $promotion->code = mb_strtoupper(trim((string) $promotion->code));
            if ($promotion->exists && $promotion->isDirty() && $promotion->usages()->exists()) {
                $allowed = ['is_active', 'archived_at', 'updated_by_user_id', 'updated_at'];
                if (array_diff(array_keys($promotion->getDirty()), $allowed) !== []) {
                    throw new LogicException('Used Promotion business definition is immutable.');
                }
            }
        });
        self::deleting(fn () => throw new LogicException('Promotions must be archived, not deleted.'));
    }

    public function cinemas(): BelongsToMany
    {
        return $this->belongsToMany(Cinema::class, 'promotion_cinema');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(BookingPromotion::class);
    }
}
