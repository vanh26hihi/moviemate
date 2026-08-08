<?php

namespace Database\Seeders;

use App\Models\Genre;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GenreSeeder extends Seeder
{
    public function run(): void
    {
        $genres = [
            'Action',
            'Adventure',
            'Animation',
            'Comedy',
            'Crime',
            'Drama',
            'Family',
            'Fantasy',
            'Horror',
            'Mystery',
            'Romance',
            'Science Fiction',
            'Thriller',
            'War',
        ];

        foreach ($genres as $name) {
            Genre::query()->updateOrCreate(['slug' => Str::slug($name)], [
                'name' => $name,
                'description' => null,
            ]);
        }
    }
}
