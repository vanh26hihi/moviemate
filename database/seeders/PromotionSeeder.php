<?php

namespace Database\Seeders;

use App\Models\Promotion;
use Illuminate\Database\Seeder;

final class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        Promotion::query()->updateOrCreate(['code' => 'MOVIEMATE10'], [
            'name' => 'Ưu đãi MovieMate 10%',
            'description' => 'Mã demo dùng để kiểm thử checkout.',
            'type' => Promotion::TYPE_PERCENTAGE,
            'discount_amount_vnd' => null,
            'discount_percent' => 10,
            'maximum_discount_vnd' => 50_000,
            'minimum_order_vnd' => 100_000,
            'is_active' => true,
            'registered_users_only' => false,
            'first_order_only' => false,
        ]);
        Promotion::query()->updateOrCreate(['code' => 'WELCOME20K'], [
            'name' => 'Chào mừng khách hàng mới',
            'description' => 'Mã demo cho đơn đầu tiên của tài khoản.',
            'type' => Promotion::TYPE_FIXED,
            'discount_amount_vnd' => 20_000,
            'discount_percent' => null,
            'maximum_discount_vnd' => null,
            'minimum_order_vnd' => 80_000,
            'is_active' => true,
            'registered_users_only' => true,
            'first_order_only' => true,
        ]);
    }
}
