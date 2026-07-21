<?php

namespace App\Services;

use App\Models\Order;
use App\Models\FoodItem;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function createOrder($data)
    {
        return DB::transaction(function () use ($data) {

            $order = Order::create([
                'user_name' => $data['user_name'],
                'total_price' => 0,
                'status' => 'pending'
            ]);

            $total = 0;

            foreach ($data['items'] as $item) {
                $food = FoodItem::findOrFail($item['id']);
                $qty = $item['quantity'];

                $order->foodItems()->attach($food->id, [
                    'quantity' => $qty
                ]);

                $total += $food->price * $qty;
            }

            $order->update(['total_price' => $total]);

            return $order->load('foodItems');
        });
    }
}