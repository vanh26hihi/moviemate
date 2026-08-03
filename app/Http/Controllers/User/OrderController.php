<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\FoodItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\CinemaContext;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly CinemaContext $cinemaContext) {}

    public function cart()
    {
        $cart = session()->get('food_cart', []);
        $items = FoodItem::query()->whereIn('id', array_keys($cart))->get()->keyBy('id');

        return view('foods.cart', compact('cart', 'items'));
    }

    public function checkout()
    {
        $cart = session()->get('food_cart', []);
        if (empty($cart)) {
            return redirect()->route('foods.index')->with('error', 'Giỏ hàng trống');
        }
        $items = FoodItem::query()->whereIn('id', array_keys($cart))->get()->keyBy('id');
        $cinema = $this->cinemaContext->current();

        return view('foods.checkout', compact('cart', 'items', 'cinema'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email'],
        ]);
        $cart = session()->get('food_cart', []);
        if (empty($cart)) {
            return redirect()->route('foods.index')->with('error', 'Giỏ hàng trống');
        }

        $items = FoodItem::query()->whereIn('id', array_keys($cart))->get()->keyBy('id');
        $total = collect($cart)->sum(fn ($qty, $id) => $items[$id]->price * $qty);
        $order = Order::query()->create([
            'user_id' => auth()->id(), 'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'pickup_cinema_id' => $this->cinemaContext->id(),
            'total_amount' => $total, 'status' => 'paid',
        ]);

        foreach ($cart as $id => $qty) {
            $food = $items[$id];
            OrderItem::query()->create([
                'order_id' => $order->id, 'food_item_id' => $food->id,
                'quantity' => $qty, 'price' => $food->price, 'total' => $food->price * $qty,
            ]);
        }
        session()->forget('food_cart');

        return redirect()->route('foods.success', $order)->with('success', 'Order placed');
    }

    public function success(Order $order)
    {
        $order->load('pickupCinema');

        return view('foods.success', compact('order'));
    }
}
