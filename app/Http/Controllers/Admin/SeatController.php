<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class FoodOrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::with(['items.food', 'pickupCinema', 'booking'])->orderBy('created_at', 'desc')->paginate(20);
        return view('admin.food-orders.index', compact('orders'));
    }

    public function show(Order $food_order)
    {
        $order = $food_order->load(['items.food', 'pickupCinema', 'booking.showtime.movie']);

        return view('admin.food-orders.show', compact('order'));
    }
}
