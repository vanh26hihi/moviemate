<?php

namespace App\Console\Commands;

use App\Models\Movie;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MovieImageDiagnostics extends Command
{
    protected $signature = 'movies:image-diagnostics {--movie= : Inspect one movie ID}';

    protected $description = 'Check movie image paths, files, and the public storage link without changing data';

    public function handle(): int
    {
        $publicStorage = realpath(public_path('storage'));
        $diskRoot = realpath(Storage::disk('public')->path(''));
        $linkValid = $publicStorage !== false && $diskRoot !== false
            && rtrim($publicStorage, '\\/') === rtrim($diskRoot, '\\/');

        $this->components->twoColumnDetail('Public storage path exists', $publicStorage !== false ? 'yes' : 'no');
        $this->components->twoColumnDetail('Public storage target is valid', $linkValid ? 'yes' : 'no');

        $query = Movie::query()->orderBy('id');
        if (filled($this->option('movie'))) {
            $query->whereKey((int) $this->option('movie'));
        }

        $movies = $query->get(['id', 'title', 'poster', 'cover_image']);
        if ($movies->isEmpty()) {
            $this->components->error('No matching movie was found.');

            return self::FAILURE;
        }

        $failed = ! $linkValid;
        foreach ($movies as $movie) {
            foreach (['poster' => $movie->poster, 'banner' => $movie->cover_image] as $kind => $value) {
                $normalized = Movie::canonicalImagePath($value);
                $exists = $normalized && Storage::disk('public')->exists($normalized);
                $state = blank($value) ? 'not configured' : (! $normalized ? 'invalid DB path' : ($exists ? 'okay' : 'missing file'));
                $failed = $failed || (filled($value) && (! $normalized || ! $exists));

                $this->newLine();
                $this->line("Movie #{$movie->id}: {$movie->title} ({$kind})");
                $this->components->twoColumnDetail('DB value', filled($value) ? (string) $value : 'unset');
                $this->components->twoColumnDetail('Normalized path', $normalized ?? 'invalid/unset');
                $this->components->twoColumnDetail('File exists', $exists ? 'yes' : 'no');
                $this->components->twoColumnDetail('Public URL', $exists ? '/storage/'.$normalized : 'unavailable');
                $this->components->twoColumnDetail('State', $state);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
