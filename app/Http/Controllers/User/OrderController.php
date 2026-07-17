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
}