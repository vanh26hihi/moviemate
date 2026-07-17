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
    public function create()
    {
        return view('admin.foods.form', ['food' => new FoodItem()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'image' => 'nullable|image|max:4096',
            'active' => 'sometimes|boolean',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('food_images', 'public');
        }

        $data['active'] = $request->has('active');

        FoodItem::create($data);

        return redirect()->route('admin.foods.index')->with('success', 'Food created');
    }

    public function edit(FoodItem $food)
    {
        return view('admin.foods.form', compact('food'));
    }

    public function update(Request $request, FoodItem $food)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
            'image' => 'nullable|image|max:4096',
            'active' => 'sometimes|boolean',
        ]);

        if ($request->hasFile('image')) {
            if ($food->image && Storage::disk('public')->exists($food->image)) {
                Storage::disk('public')->delete($food->image);
            }
            $data['image'] = $request->file('image')->store('food_images', 'public');
        }

        $data['active'] = $request->has('active');

        $food->update($data);

        return redirect()->route('admin.foods.index')->with('success', 'Food updated');
    }

    public function destroy(FoodItem $food)
    {
        $food->delete();
        return redirect()->route('admin.foods.index')->with('success', 'Food deleted');
    }
}