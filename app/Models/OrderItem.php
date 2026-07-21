<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MovieFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'slug' => Str::slug($this->faker->unique()->sentence(3)),
            'description' => $this->faker->paragraph(),
            'poster' => $this->faker->imageUrl(),
            'cover_image' => $this->faker->imageUrl(),
            'trailer_url' => 'https://youtube.com/watch?v=' . $this->faker->uuid(),
            'country' => $this->faker->country(),
            'duration' => rand(80, 180),
            'age_rating' => 'P13',
            'release_date' => $this->faker->date(),
            'status' => true,
        ];
    }
}