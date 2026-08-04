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
            'total_seats' => 0,
            'status' => 'active',
        ];
    }
}
