<?php

namespace Database\Seeders;

use App\Models\Cinema;
use App\Services\CinemaContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class CinemaSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $definitions = [
                'CG' => [
                    'canonical_key' => CinemaContext::CANONICAL_KEY,
                    'name' => 'MovieMate Cầu Giấy',
                    'address' => 'Số 8 Tôn Thất Thuyết, Cầu Giấy, Hà Nội',
                    'city' => 'Hà Nội',
                    'latitude' => '21.0292140',
                    'longitude' => '105.7828830',
                    'is_primary' => true,
                ],
                'HD' => [
                    'canonical_key' => 'moviemate-ha-dong',
                    'name' => 'MovieMate Hà Đông',
                    'address' => 'Số 10 Trần Phú, Hà Đông, Hà Nội',
                    'city' => 'Hà Nội',
                    'latitude' => '20.9802260',
                    'longitude' => '105.7877750',
                    'is_primary' => false,
                ],
                'NTL' => [
                    'canonical_key' => 'moviemate-nam-tu-liem',
                    'name' => 'MovieMate Nam Từ Liêm',
                    'address' => 'Số 1 Trịnh Văn Bô, Nam Từ Liêm, Hà Nội',
                    'city' => 'Hà Nội',
                    'latitude' => CinemaContext::LATITUDE,
                    'longitude' => CinemaContext::LONGITUDE,
                    'is_primary' => false,
                ],
            ];

            foreach ($definitions as $code => $attributes) {
                $cinema = Cinema::query()->where('code', $code)->first();
                if (! $cinema && $code === 'CG') {
                    $cinema = Cinema::query()->where('is_primary', true)->orderBy('id')->first();
                }
                $cinema ??= new Cinema;
                $cinema->fill([
                    ...$attributes,
                    'code' => $code,
                    'school_name' => null,
                    'country' => 'Việt Nam',
                    'phone' => null,
                    'timezone' => 'Asia/Ho_Chi_Minh',
                    'status' => 'active',
                    'archived_at' => null,
                ])->save();
            }

            Cinema::query()->where('code', '!=', 'CG')->update(['is_primary' => false]);
        });
    }
}
