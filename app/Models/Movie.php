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

    public function getPosterUrlAttribute(): ?string
    {
        return static::imageUrl($this->poster);
    }

    public function getCoverUrlAttribute(): ?string
    {
        return static::imageUrl($this->cover_image);
    }

    public static function imageUrl(?string $path): ?string
    {
        $path = static::normalizeImagePath($path);

        if (! $path) {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        if (str_starts_with($path, 'storage/')) {
            return asset($path);
        }

        return asset('storage/'.$path);
    }

    public static function storageDiskPath(?string $path): ?string
    {
        $path = static::normalizeImagePath($path);

        if (! $path || preg_match('/^https?:\/\//i', $path)) {
            return null;
        }

        return str_starts_with($path, 'storage/')
            ? substr($path, strlen('storage/'))
            : $path;
    }

    protected static function normalizeImagePath(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = trim(str_replace('\\', '/', $path));

        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        $path = ltrim($path, '/');

        foreach (['storage/app/public/', 'public/storage/'] as $prefix) {
            $position = stripos($path, $prefix);

            if ($position !== false) {
                $path = substr($path, $position + strlen($prefix));
                break;
            }
        }

        if (str_starts_with($path, 'storage/')) {
            return $path;
        }

        return ltrim($path, '/');
    }

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
