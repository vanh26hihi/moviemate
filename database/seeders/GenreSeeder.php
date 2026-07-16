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
            ['name' => 'Action'],
            ['name' => 'Comedy'],
            ['name' => 'Drama'],
            ['name' => 'Horror'],
            ['name' => 'Science Fiction'],
            ['name' => 'Romance'],
        ];

        foreach ($genres as $data) {
            Genre::create([
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'description' => null,
            ]);
        }
    }
}