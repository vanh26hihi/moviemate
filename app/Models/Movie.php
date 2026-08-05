<?php

namespace App\Models;

use App\Support\StatusLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

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
        $path = static::canonicalImagePath($path);

        return $path && Storage::disk('public')->exists($path)
            ? '/storage/'.$path
            : null;
    }

    public static function storageDiskPath(?string $path): ?string
    {
        return static::canonicalImagePath($path);
    }

    public static function canonicalImagePath(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = trim(str_replace('\\', '/', $path));

        if (preg_match('/^https?:\/\//i', $path)) {
            $parts = parse_url($path);

            if (! is_array($parts) || isset($parts['query']) || isset($parts['fragment'])) {
                return null;
            }

            $path = (string) ($parts['path'] ?? '');
        } elseif (preg_match('/^[a-z][a-z0-9+.-]*:/i', $path)
            || preg_match('/^[a-z]:\//i', $path)
            || str_starts_with($path, '//')) {
            return null;
        }

        if (str_contains($path, "\0") || str_contains($path, '?') || str_contains($path, '#') || str_contains($path, '%')) {
            return null;
        }

        $path = ltrim($path, '/');
        $prefixes = ['storage/app/public/', 'public/storage/', 'public/', 'storage/'];

        do {
            $stripped = false;
            foreach ($prefixes as $prefix) {
                if (str_starts_with(strtolower($path), $prefix)) {
                    $path = substr($path, strlen($prefix));
                    $stripped = true;
                    break;
                }
            }
        } while ($stripped);

        if (! preg_match('#^movies/(posters|banners|covers)/([^/]+)$#', $path, $matches)) {
            return null;
        }

        $filename = $matches[2];
        if ($filename === '.' || $filename === '..' || str_contains($filename, '..')
            || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $filename)) {
            return null;
        }

        return 'movies/'.$matches[1].'/'.$filename;
    }

    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class, 'movie_genre');
    }

    public function showtimes(): HasMany
    {
        return $this->hasMany(Showtime::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return StatusLabel::for('movie', $this->status);
    }
}
