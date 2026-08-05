<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Money\VndAmount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveFoodRequest;
use App\Models\FoodItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        return view('admin.foods.form', ['food' => new FoodItem]);
    }

    public function store(SaveFoodRequest $request)
    {
        $data = $request->validated();
        $data['price'] = VndAmount::fromInput($data['price'], FoodItem::MAX_PRICE)->value();

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

    public function update(SaveFoodRequest $request, FoodItem $food)
    {
        $data = $request->validated();
        $data['price'] = VndAmount::fromInput($data['price'], FoodItem::MAX_PRICE)->value();

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
