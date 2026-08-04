<?php

namespace Database\Seeders;

use App\Models\Cinema;
use App\Services\CinemaContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CinemaSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            Cinema::query()
                ->where(fn ($query) => $query->whereNull('canonical_key')
                    ->orWhere('canonical_key', '!=', CinemaContext::CANONICAL_KEY))
                ->update(['status' => 'inactive', 'is_primary' => false, 'archived_at' => now()]);

            Cinema::query()->updateOrCreate(
                ['canonical_key' => CinemaContext::CANONICAL_KEY],
                [
                    'name' => 'MovieMate Cinema – FPT Polytechnic',
                    'school_name' => CinemaContext::SCHOOL_NAME,
                    'address' => CinemaContext::ADDRESS,
                    'city' => CinemaContext::CITY,
                    'country' => CinemaContext::COUNTRY,
                    'phone' => null,
                    'latitude' => CinemaContext::LATITUDE,
                    'longitude' => CinemaContext::LONGITUDE,
                    'status' => 'active',
                    'is_primary' => true,
                    'archived_at' => null,
                ],
            );
        });
    }
}
