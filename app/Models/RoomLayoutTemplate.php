<?php

namespace App\Models;

use App\Support\StatusLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomLayoutTemplate extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_ARCHIVED];

    protected $fillable = [
        'code', 'name', 'description', 'room_type', 'rows', 'columns', 'screen_position',
        'status', 'created_by_user_id', 'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return ['rows' => 'integer', 'columns' => 'integer'];
    }

    public function cells(): HasMany
    {
        return $this->hasMany(RoomLayoutTemplateCell::class);
    }

    public function roomLayouts(): HasMany
    {
        return $this->hasMany(RoomLayout::class, 'source_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function getStatusLabelAttribute(): string
    {
        return StatusLabel::for('layout_template', $this->status);
    }
}
