<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class RoomType extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'is_active', 'sort_order',
        'created_by_user_id', 'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'status' => 'boolean', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        self::saving(function (RoomType $roomType): void {
            $roomType->slug = $roomType->code;
            $roomType->status = $roomType->is_active;
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class, 'room_type_id');
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(CinemaPricingRule::class, 'room_type', 'code');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public static function normalizeCode(string $value): string
    {
        $code = preg_replace('/[^A-Z0-9]+/', '_', strtoupper(Str::ascii(trim($value)))) ?? '';

        return Str::limit(trim($code, '_'), 40, '');
    }

    public static function options(?string $currentCode = null, bool $includeInactive = false): Collection
    {
        return self::query()
            ->when(! $includeInactive, fn (Builder $query) => $query->where(function (Builder $query) use ($currentCode): void {
                $query->where('is_active', true)
                    ->when($currentCode, fn (Builder $query, string $code) => $query->orWhere('code', $code));
            }))
            ->orderBy('sort_order')->orderBy('name')->get();
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->is_active ? 'Đang sử dụng' : 'Ngừng sử dụng';
    }
}
