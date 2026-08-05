<?php

namespace App\Services;

use App\Models\Movie;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MovieImageService
{
    public const POSTER = 'poster';

    public const BANNER = 'banner';

    public function store(UploadedFile $file, string $kind): string
    {
        $directory = match ($kind) {
            self::POSTER => 'movies/posters',
            self::BANNER => 'movies/banners',
            default => throw new RuntimeException('Unsupported movie image type.'),
        };

        $stored = $file->store($directory, 'public');
        $path = is_string($stored) ? Movie::canonicalImagePath($stored) : null;

        if (! $path) {
            if (is_string($stored)) {
                Storage::disk('public')->delete($stored);
            }

            throw new RuntimeException('The movie image could not be stored safely.');
        }

        return $path;
    }

    /** @param array<int, string|null> $paths */
    public function deleteStored(array $paths): void
    {
        foreach (array_unique(array_filter($paths)) as $path) {
            $canonical = Movie::canonicalImagePath($path);
            if ($canonical) {
                Storage::disk('public')->delete($canonical);
            }
        }
    }

    public function deleteIfUnreferenced(?string $path, ?int $excludingMovieId = null): void
    {
        $canonical = Movie::canonicalImagePath($path);
        if (! $canonical) {
            return;
        }

        $referenced = Movie::query()
            ->when($excludingMovieId, fn ($query) => $query->whereKeyNot($excludingMovieId))
            ->get(['id', 'poster', 'cover_image'])
            ->contains(fn (Movie $movie): bool => $canonical === Movie::canonicalImagePath($movie->poster)
                || $canonical === Movie::canonicalImagePath($movie->cover_image));

        if (! $referenced) {
            Storage::disk('public')->delete($canonical);
        }
    }
}
