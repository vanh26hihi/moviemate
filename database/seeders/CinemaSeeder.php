<?php

namespace Database\Seeders;

use App\Models\Cinema;
use Illuminate\Database\Seeder;

class CinemaSeeder extends Seeder
{
    public function run(): void
    {
        $cinemas = [
            [
                'name' => 'Cinema One',
                'address' => '123 Main St',
                'city' => 'Hanoi',
                'phone' => '0123456789',
                'image' => null,
                'description' => 'Main downtown cinema',
                'status' => 'active',
            ],
            [
                'name' => 'Cinema Two',
                'address' => '456 Second Ave',
                'city' => 'Ho Chi Minh City',
                'phone' => '0987654321',
                'image' => null,
                'description' => 'City center cinema',
                'status' => 'active',
            ],
            [
                'name' => 'Cinema Three',
                'address' => '789 Third Blvd',
                'city' => 'Da Nang',
                'phone' => null,
                'image' => null,
                'description' => 'Coastal cinema',
                'status' => 'active',
            ],
        ];

        foreach ($cinemas as $data) {
            Cinema::create($data);
        }
    }
}