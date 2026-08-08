<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The reviews table has existed since the initial schema, but no model or relation was ever
 * added. The merged movie-list feature aggregates ratings via withAvg/withCount('reviews'),
 * so the relation is introduced here rather than dropping the incoming feature.
 */
class Review extends Model
{
    public const STATUS_VISIBLE = 'visible';

    public const STATUS_HIDDEN = 'hidden';

    public const MODERATION_PUBLISHED = 'published';

    public const MODERATION_PENDING = 'pending';

    public const MODERATION_HIDDEN = 'hidden';

    public const MODERATION_REJECTED = 'rejected';

    protected $fillable = ['user_id', 'movie_id', 'booking_id', 'rating', 'comment', 'sentiment', 'status', 'moderation_status', 'moderation_flags', 'moderation_reason', 'is_verified', 'first_published_at', 'reward_awarded_at', 'moderated_by_user_id', 'moderated_at'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'moderation_flags' => 'array', 'is_verified' => 'boolean', 'first_published_at' => 'datetime', 'reward_awarded_at' => 'datetime', 'moderated_at' => 'datetime'];
    }

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by_user_id');
    }

    public function getModerationStatusLabelAttribute(): string
    {
        return match ($this->moderation_status) {
            self::MODERATION_PENDING => 'Chờ kiểm duyệt',
            self::MODERATION_PUBLISHED => 'Đã đăng',
            self::MODERATION_HIDDEN => 'Đã ẩn',
            self::MODERATION_REJECTED => 'Đã từ chối',
            default => 'Không xác định',
        };
    }

    public function getModerationFlagLabelsAttribute(): array
    {
        return collect($this->moderation_flags ?? [])->map(fn (string $flag): string => match ($flag) {
            'url' => 'Có liên kết',
            'profanity' => 'Ngôn từ cần kiểm tra',
            'spam' => 'Lặp ký tự bất thường',
            'excessive' => 'Nội dung quá dài',
            default => 'Cần kiểm tra',
        })->all();
    }
}
