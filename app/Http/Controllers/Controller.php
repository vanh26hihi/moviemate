<?php

namespace App\Http\Controllers;

use App\Models\Movie;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MovieController extends Controller
{
    public function index()
    {
        return Movie::with(['genres', 'showtimes'])->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'poster' => 'nullable|string',
            'country' => 'nullable|string',
            'duration' => 'required|integer',
            'release_date' => 'required|date',
        ]);

        $data['slug'] = Str::slug($data['title']);

        $movie = Movie::create($data);

        return response()->json($movie, 201);
    }

    public function show($id)
    {
        return Movie::with(['genres', 'reviews'])->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $movie = Movie::findOrFail($id);

        $movie->update($request->all());

        return response()->json($movie);
    }

    public function destroy($id)
    {
        Movie::destroy($id);

        return response()->json(['message' => 'Deleted']);
    }
}