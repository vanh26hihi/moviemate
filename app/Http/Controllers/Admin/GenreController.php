<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CinemaController extends Controller
{
    public function index(Request $request)
    {
        $query = Cinema::query();

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $cinemas = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('admin.cinemas.index', compact('cinemas', 'search'));
    }

    public function create()
    {
        return view('admin.cinemas.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'phone' => 'nullable|string|max:30',
            'image' => 'nullable|image|max:4096',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('cinema_images', 'public');
        }

        Cinema::create($validated);

        return redirect()->route('admin.cinemas.index')->with('success', 'Cinema created successfully.');
    }

    public function edit(Cinema $cinema)
    {
        return view('admin.cinemas.edit', compact('cinema'));
    }

    public function update(Request $request, Cinema $cinema)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'phone' => 'nullable|string|max:30',
            'image' => 'nullable|image|max:4096',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        if ($request->hasFile('image')) {
            if ($cinema->image && Storage::disk('public')->exists($cinema->image)) {
                Storage::disk('public')->delete($cinema->image);
            }

            $validated['image'] = $request->file('image')->store('cinema_images', 'public');
        }

        $cinema->update($validated);

        return redirect()->route('admin.cinemas.index')->with('success', 'Cinema updated successfully.');
    }

    public function destroy(Cinema $cinema)
    {
        if ($cinema->image && Storage::disk('public')->exists($cinema->image)) {
            Storage::disk('public')->delete($cinema->image);
        }

        $cinema->delete();

        return redirect()->route('admin.cinemas.index')->with('success', 'Cinema deleted successfully.');
    }
}
