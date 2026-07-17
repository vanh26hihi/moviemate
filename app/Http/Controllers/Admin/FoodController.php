<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\FoodItem;

class FoodController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('search');

        $foods = FoodItem::orderBy('name')
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
            })
            ->paginate(20)
            ->withQueryString();

        return view('admin.foods.index', compact('foods', 'search'));
    }
}