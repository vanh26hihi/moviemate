<?php

namespace Database\Factories;

use App\Models\Cinema;
use App\Services\CinemaContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Cinema> */
class CinemaFactory extends Factory
{
    protected $model = Cinema::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('TST-###'),
            'timezone' => 'Asia/Ho_Chi_Minh',
            'name' => fake()->company().' Cinema',
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => 'Việt Nam',
            'phone' => null,
            'status' => 'inactive',
            'is_primary' => false,
            'archived_at' => now(),
        ];
    }

    public function primaryFpt(): static
    {
        return $this->state(fn () => [
            'canonical_key' => CinemaContext::CANONICAL_KEY,
            'code' => 'CG',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'name' => 'MovieMate Cinema – FPT Polytechnic',
            'school_name' => CinemaContext::SCHOOL_NAME,
            'address' => CinemaContext::ADDRESS,
            'city' => CinemaContext::CITY,
            'country' => CinemaContext::COUNTRY,
            'latitude' => CinemaContext::LATITUDE,
            'longitude' => CinemaContext::LONGITUDE,
            'status' => 'active',
            'is_primary' => true,
            'archived_at' => null,
        ]);
    }

    public function legacy(): static
    {
        return $this->state(fn () => ['canonical_key' => null, 'status' => 'inactive', 'is_primary' => false]);
    }
}
