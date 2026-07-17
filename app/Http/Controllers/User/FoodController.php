<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FoodItem;

class FoodController extends Controller
{
    public function index()
    {
        $foods = FoodItem::where('active', true)->orderBy('name')->paginate(12);
        return view('foods.index', compact('foods'));
    }
     public function addToCart(Request $request)
    {
        $data = $request->validate([
            'food_id' => 'required|integer|exists:food_items,id',
            'quantity' => 'required|integer|min:1'
        ]);

        $cart = session()->get('food_cart', []);
        $id = $data['food_id'];
        $qty = $data['quantity'];

        if (isset($cart[$id])) {
            $cart[$id] += $qty;
        } else {
            $cart[$id] = $qty;
        }

        session()->put('food_cart', $cart);

        return redirect()->back()->with('success', 'Added to cart');
    }
}