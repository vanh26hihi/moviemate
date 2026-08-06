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

    protected $fillable = ['user_id', 'movie_id', 'rating', 'comment', 'sentiment', 'status'];

    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
