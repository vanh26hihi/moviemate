<?php

namespace App\Models;

use App\Support\StatusLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class RoomLayout extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_RETIRED = 'retired';

    public const STRUCTURAL_FIELDS = ['room_id', 'version', 'rows', 'columns', 'screen_position'];

    protected $fillable = [
        'room_id',
        'version',
        'name',
        'rows',
        'columns',
        'screen_position',
        'status',
        'change_note',
        'source_template_id',
        'source_template_name_snapshot',
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
            $originalStatus = $layout->getOriginal('status');
            $isRetirement = $originalStatus === self::STATUS_PUBLISHED
                && $layout->status === self::STATUS_RETIRED
                && ! $layout->isDirty(self::STRUCTURAL_FIELDS);

            if ($originalStatus === self::STATUS_DRAFT) {
                return;
            }

            if ($layout->isDirty(self::STRUCTURAL_FIELDS)
                || ($layout->isDirty('status') && ! $isRetirement)
                || ($originalStatus === self::STATUS_PUBLISHED && ! $isRetirement)) {
                throw new LogicException('Không thể chỉnh sửa sơ đồ ghế đã phát hành.');
            }
        });

        static::deleting(function (RoomLayout $layout): void {
            if ($layout->showtimes()->exists()) {
                throw new LogicException('Không thể xóa sơ đồ ghế đang được một suất chiếu sử dụng.');
            }
        });
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function sourceTemplate(): BelongsTo
    {
        return $this->belongsTo(RoomLayoutTemplate::class, 'source_template_id');
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

    public function getStatusLabelAttribute(): string
    {
        return StatusLabel::for('layout', $this->status);
    }

    public function getDisplayNameAttribute(): string
    {
        $name = trim((string) $this->name);
        if ($name === '') {
            return 'Sơ đồ lịch sử v'.$this->version;
        }

        return (string) preg_replace('/\blayout\b/iu', 'sơ đồ', $name);
    }
}
