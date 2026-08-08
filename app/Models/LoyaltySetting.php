<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class LoyaltySetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['review_reward_points' => 'integer', 'point_value_vnd' => 'integer', 'max_points_discount_percent' => 'integer', 'minimum_points_redemption' => 'integer', 'max_discount_codes_per_booking' => 'integer'];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate([], ['review_reward_points' => 100, 'point_value_vnd' => 100, 'max_points_discount_percent' => 50, 'minimum_points_redemption' => 1, 'max_discount_codes_per_booking' => 3]);
    }
}
