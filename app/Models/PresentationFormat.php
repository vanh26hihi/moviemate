<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class PresentationFormat extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'is_active', 'sort_order',
        'created_by_user_id', 'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function movies(): BelongsToMany
    {
        return $this->belongsToMany(Movie::class, 'movie_presentation_formats')->withTimestamps();
    }

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'room_presentation_capabilities')->withTimestamps();
    }

    public function showtimes(): HasMany
    {
        return $this->hasMany(Showtime::class);
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

    public function getStatusLabelAttribute(): string
    {
        return $this->is_active ? 'Đang sử dụng' : 'Đã lưu trữ';
    }
}
