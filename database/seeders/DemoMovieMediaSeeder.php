<?php

namespace Database\Seeders;

use App\Models\Movie;
use Database\Seeders\Support\RealMovieCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class DemoMovieMediaSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        foreach (RealMovieCatalog::movies() as $definition) {
            $providerId = (int) $definition['provider_id'];
            $posterSource = $this->sourcePath($providerId, 'poster');
            $coverSource = $this->sourcePath($providerId, 'cover');

            $this->assertImage($posterSource);
            $this->assertImage($coverSource);

            $posterPath = "movies/posters/tmdb-{$providerId}.jpg";
            $coverPath = "movies/covers/tmdb-{$providerId}.jpg";
            $posterWritten = Storage::disk('public')->put($posterPath, file_get_contents($posterSource));
            $coverWritten = Storage::disk('public')->put($coverPath, file_get_contents($coverSource));
            if (! $posterWritten || ! $coverWritten) {
                throw new RuntimeException("Không thể sao chép tài sản demo cho phim TMDB {$providerId}.");
            }

            $updated = Movie::query()->where('slug', Str::slug($definition['title']))->update([
                'poster' => $posterPath,
                'cover_image' => $coverPath,
            ]);

            if ($updated !== 1) {
                throw new RuntimeException("Không thể gắn tài sản demo cho phim TMDB {$providerId}.");
            }
        }
    }

    private function sourcePath(int $providerId, string $kind): string
    {
        return database_path("seeders/assets/movie-media/tmdb-{$providerId}-{$kind}.jpg");
    }

    private function assertImage(string $path): void
    {
        if (! is_file($path) || @getimagesize($path) === false) {
            throw new RuntimeException("Tài sản ảnh demo không hợp lệ: {$path}");
        }
    }
}
