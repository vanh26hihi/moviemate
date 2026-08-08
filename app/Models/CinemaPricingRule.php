<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CinemaPricingRule extends Model
{
    public const TYPES = [
        'base', 'seat_type', 'room_type', 'time_window', 'weekend', 'holiday',
        'cinema_adjustment', 'room_adjustment',
    ];

    protected $fillable = [
        'name', 'rule_type', 'cinema_id', 'room_id', 'seat_type', 'room_type',
        'days_of_week', 'date_start', 'date_end', 'time_start', 'time_end',
        'amount_vnd', 'priority', 'stacks_with_weekend', 'starts_at', 'ends_at',
        'status', 'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'days_of_week' => 'array', 'date_start' => 'date', 'date_end' => 'date',
            'amount_vnd' => 'integer', 'priority' => 'integer',
            'stacks_with_weekend' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime',
        ];
    }

    public function cinema(): BelongsTo
    {
        return $this->belongsTo(Cinema::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
