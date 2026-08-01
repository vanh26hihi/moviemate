<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FoodItem;
use App\Models\Order;
use App\Models\OrderItem;

class OrderController extends Controller
{
       public function cart()
    {
        $cart = session()->get('food_cart', []);
        $items = FoodItem::whereIn('id', array_keys($cart))->get()->keyBy('id');
        return view('foods.cart', compact('cart', 'items'));
    }
     public function checkout()
    {
        $cart = session()->get('food_cart', []);
        if (empty($cart)) {
            return redirect()->route('foods.index')->with('error', 'Giỏ hàng trống');
        }
        $items = FoodItem::whereIn('id', array_keys($cart))->get()->keyBy('id');
        return view('foods.checkout', compact('cart', 'items'));
    } public function store(Request $request)
    {
        $data = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'customer_email' => 'nullable|email',
            'pickup_cinema_id' => 'nullable|integer|exists:cinemas,id'
        ]);

        $cart = session()->get('food_cart', []);
        if (empty($cart)) {
            return redirect()->route('foods.index')->with('error', 'Giỏ hàng trống');
        }

        $items = FoodItem::whereIn('id', array_keys($cart))->get()->keyBy('id');

        $total = 0;
        foreach ($cart as $id => $qty) {
            $fi = $items[$id];
            $total += $fi->price * $qty;
        }

        $order = Order::create([
            'user_id' => auth()->id(),
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'pickup_cinema_id' => $data['pickup_cinema_id'] ?? null,
            'total_amount' => $total,
            'status' => 'paid', // For now mark as paid to reuse existing payment flow
        ]);

        foreach ($cart as $id => $qty) {
            $fi = $items[$id];
            OrderItem::create([
                'order_id' => $order->id,
                'food_item_id' => $fi->id,
                'quantity' => $qty,
                'price' => $fi->price,
                'total' => $fi->price * $qty,
            ]);
        }

        // clear cart
        session()->forget('food_cart');

        return redirect()->route('foods.success', $order->id)->with('success', 'Order placed');
    }

    public function success(Order $order)
    {
        return view('foods.success', compact('order'));
    }
}