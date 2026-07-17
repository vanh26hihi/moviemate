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
}