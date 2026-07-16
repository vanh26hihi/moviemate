<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Movie extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'poster',
        'cover_image',
        'trailer_url',
        'country',
        'duration',
        'age_rating',
        'release_date',
        'status',
    ];

    protected $casts = [
        'duration' => 'integer',
        'release_date' => 'date',
    ];

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'movie_genre');
    }

    public function showtimes(): HasMany
    {
        return $this->hasMany(Showtime::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
