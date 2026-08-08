<?php

namespace Database\Seeders;

use App\Models\Genre;
use App\Models\Movie;
use Database\Seeders\Support\RealMovieCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MovieSeeder extends Seeder
{
    public function run(): void
    {
        $fictionalMovies = [
            [
                'title' => 'The Great Adventure',
                'description' => 'An epic journey.',
                'duration' => 120,
                'age_rating' => 'PG-13',
                'release_date' => '2025-01-15',
                'status' => Movie::STATUS_INACTIVE,
                'genres' => ['Action'],
            ],
            [
                'title' => 'Love in Spring',
                'description' => 'A romantic story.',
                'duration' => 100,
                'age_rating' => 'PG',
                'release_date' => '2025-02-20',
                'status' => Movie::STATUS_INACTIVE,
                'genres' => ['Romance', 'Drama'],
            ],
            [
                'title' => 'Laugh Out Loud',
                'description' => 'Comedy for everyone.',
                'duration' => 95,
                'age_rating' => 'PG',
                'release_date' => '2024-12-01',
                'status' => Movie::STATUS_INACTIVE,
                'genres' => ['Comedy'],
            ],
            [
                'title' => 'Space Odyssey',
                'description' => 'Sci‑fi exploration.',
                'duration' => 130,
                'age_rating' => 'PG-13',
                'release_date' => '2025-03-10',
                'status' => Movie::STATUS_INACTIVE,
                'genres' => ['Science Fiction', 'Action'],
            ],
            [
                'title' => 'Haunted Night',
                'description' => 'Horror thriller.',
                'duration' => 105,
                'age_rating' => 'R',
                'release_date' => '2024-10-31',
                'status' => Movie::STATUS_INACTIVE,
                'genres' => ['Horror'],
            ],
            [
                'title' => 'Family Tales',
                'description' => 'Heartwarming drama.',
                'duration' => 110,
                'age_rating' => 'PG',
                'release_date' => '2025-04-05',
                'status' => Movie::STATUS_INACTIVE,
                'genres' => ['Drama'],
            ],
            [
                'title' => 'Future Tech',
                'description' => 'Tech‑driven sci‑fi.',
                'duration' => 115,
                'age_rating' => 'PG-13',
                'release_date' => '2025-05-01',
                'status' => Movie::STATUS_INACTIVE,
                'genres' => ['Science Fiction'],
            ],
            [
                'title' => 'Mystery Manor',
                'description' => 'Mystery and suspense.',
                'duration' => 100,
                'age_rating' => 'PG',
                'release_date' => '2025-06-12',
                'status' => Movie::STATUS_INACTIVE,
                'genres' => ['Horror', 'Drama'],
            ],
        ];

        foreach ($fictionalMovies as $data) {
            $movie = Movie::query()->updateOrCreate(['slug' => Str::slug($data['title'])], [
                'title' => $data['title'],
                'description' => $data['description'],
                'poster' => null,
                'cover_image' => null,
                'duration' => $data['duration'],
                'age_rating' => $data['age_rating'],
                'release_date' => $data['release_date'],
                'status' => $data['status'],
            ]);

            $genreIds = Genre::whereIn('name', $data['genres'])->pluck('id')->toArray();
            $movie->genres()->sync($genreIds);
        }

        foreach (RealMovieCatalog::movies() as $data) {
            $movie = Movie::query()->updateOrCreate(['slug' => Str::slug($data['title'])], [
                'title' => $data['title'],
                'description' => $data['description'],
                'poster' => $data['poster'],
                'cover_image' => $data['cover_image'],
                'duration' => $data['duration'],
                'age_rating' => $data['age_rating'],
                'release_date' => $data['release_date'],
                'status' => $data['status'],
            ]);

            $movie->genres()->sync(Genre::query()->whereIn('name', $data['genres'])->pluck('id'));
        }
    }
}
