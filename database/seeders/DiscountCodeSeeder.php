<?php

namespace Database\Seeders;

use App\Models\DiscountCode;
use Illuminate\Database\Seeder;

final class DiscountCodeSeeder extends Seeder
{
    public function run(): void
    {
        DiscountCode::query()->updateOrCreate(['code' => 'MOVIEMATE10'], [
            'name' => 'Ưu đãi MovieMate 10%', 'description' => 'Mã demo dùng để kiểm thử checkout.',
            'discount_type' => 'percent', 'discount_value' => 10, 'maximum_discount_amount' => 50000,
            'minimum_order_amount' => 100000, 'is_active' => true, 'can_combine' => false, 'priority' => 10,
        ]);
        DiscountCode::query()->updateOrCreate(['code' => 'WELCOME20K'], [
            'name' => 'Chào mừng khách hàng mới', 'description' => 'Mã demo cho đơn đầu tiên của tài khoản.',
            'discount_type' => 'fixed', 'discount_value' => 20000, 'minimum_order_amount' => 80000,
            'is_active' => true, 'registered_users_only' => true, 'first_order_only' => true, 'can_combine' => false, 'priority' => 20,
        ]);
    }
}
