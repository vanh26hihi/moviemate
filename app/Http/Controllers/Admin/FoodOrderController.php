<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class FoodOrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::with('items.food')->orderBy('created_at', 'desc')->paginate(20);
        return view('admin.food-orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        $order->load('items.food');
        return view('admin.food-orders.show', compact('order'));
    }
}