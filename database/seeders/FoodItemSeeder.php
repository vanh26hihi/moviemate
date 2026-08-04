<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FoodItem;

class FoodItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'name' => 'Bắp rang bơ',
                'description' => 'Bắp rang giòn rụm, vị muối nhẹ nhàng.',
                'price' => 45000,
                'image' => 'https://images.unsplash.com/photo-1498654896293-37aacf113fd9?auto=format&fit=crop&w=600&q=80',
                'active' => true,
            ],
            [
                'name' => 'Kem ốc quế',
                'description' => 'Kem vani mềm mịn với ốc quế giòn tan.',
                'price' => 39000,
                'image' => 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=600&q=80',
                'active' => true,
            ],
            [
                'name' => 'Nước ngọt',
                'description' => 'Có thể chọn Coca-Cola, Pepsi hoặc nước khoáng.',
                'price' => 32000,
                'image' => 'https://images.unsplash.com/photo-1571091718767-18d9d78801d8?auto=format&fit=crop&w=600&q=80',
                'active' => true,
            ],
            [
                'name' => 'Hot dog',
                'description' => 'Bánh mì nóng với xúc xích và sốt đặc trưng.',
                'price' => 55000,
                'image' => 'https://images.unsplash.com/photo-1550317138-10000687a72b?auto=format&fit=crop&w=600&q=80',
                'active' => true,
            ],
        ];

        foreach ($items as $item) {
            FoodItem::updateOrCreate(['name' => $item['name']], $item);
        }
    }
}
