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

    public const TYPE_LABELS = [
        'base' => 'Giá cơ bản',
        'seat_type' => 'Phụ thu theo loại ghế',
        'room_type' => 'Phụ thu theo loại phòng',
        'time_window' => 'Phụ thu theo khung giờ',
        'weekend' => 'Phụ thu cuối tuần',
        'holiday' => 'Phụ thu ngày lễ',
        'cinema_adjustment' => 'Điều chỉnh theo chi nhánh',
        'room_adjustment' => 'Điều chỉnh theo phòng chiếu',
    ];

    public static function typeLabel(string $type): string
    {
        return self::TYPE_LABELS[$type] ?? $type;
    }

    public function getRuleTypeLabelAttribute(): string
    {
        return self::typeLabel((string) $this->rule_type);
    }

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
