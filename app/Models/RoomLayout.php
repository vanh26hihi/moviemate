<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class RoomLayout extends Model
{
    protected $fillable = [
        'room_id',
        'version',
        'name',
        'rows',
        'columns',
        'screen_position',
        'status',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'rows' => 'integer',
            'columns' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (RoomLayout $layout): void {
            if ($layout->getOriginal('status') === 'published') {
                throw new LogicException('Published room layouts are immutable.');
            }
        });

        static::deleting(function (RoomLayout $layout): void {
            if ($layout->showtimes()->exists()) {
                throw new LogicException('A room layout used by a showtime cannot be deleted.');
            }
        });
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function cells(): HasMany
    {
        return $this->hasMany(RoomLayoutCell::class);
    }

    public function showtimes(): HasMany
    {
        return $this->hasMany(Showtime::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }
}
