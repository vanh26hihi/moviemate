<?php

namespace Database\Factories;

use App\Models\Room;
use App\Services\CinemaContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Room> */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'cinema_id' => fn () => app(CinemaContext::class)->id(),
            'code' => fake()->unique()->bothify('T##'),
            'name' => 'Phòng '.fake()->unique()->numberBetween(10, 99),
            'room_type' => '2D',
            'width_mm' => 8_000,
            'length_mm' => 12_000,
            'status' => 'active',
        ];
    }

    public function inactiveIncomplete(): static
    {
        return $this->state(fn (): array => [
            'width_mm' => null,
            'length_mm' => null,
            'status' => 'inactive',
        ]);
    }
}
