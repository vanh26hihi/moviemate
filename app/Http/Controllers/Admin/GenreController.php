<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GenreController extends Controller
{
    /**
     * Display a listing of the genres.
     */
    public function index(Request $request)
    {
        $query = Genre::query();

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $genres = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.genres.index', compact('genres', 'search'));
    }

    /**
     * Show the form for creating a new genre.
     */
    public function create()
    {
        return view('admin.genres.create');
    }

    /**
     * Store a newly created genre in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'slug'        => 'nullable|string|max:255|unique:genres,slug',
            'description' => 'nullable|string',
        ]);

        // Generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
            $original = $validated['slug'];
            $counter = 1;
            while (Genre::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $original . '-' . $counter++;
            }
        }

        Genre::create($validated);

        return redirect()
            ->route('admin.genres.index')
            ->with('success', 'Genre created successfully.');
    }

    /**
     * Display the specified genre.
     */
    public function show(Genre $genre)
    {
        $genre->load('movies');
        return view('admin.genres.show', compact('genre'));
    }

    /**
     * Show the form for editing the specified genre.
     */
    public function edit(Genre $genre)
    {
        return view('admin.genres.edit', compact('genre'));
    }

    /**
     * Update the specified genre in storage.
     */
    public function update(Request $request, Genre $genre)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'slug'        => 'nullable|string|max:255|unique:genres,slug,' . $genre->id,
            'description' => 'nullable|string',
        ]);

        // Generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
            $original = $validated['slug'];
            $counter = 1;
            while (Genre::where('slug', $validated['slug'])->where('id', '!=', $genre->id)->exists()) {
                $validated['slug'] = $original . '-' . $counter++;
            }
        }

        $genre->update($validated);

        return redirect()
            ->route('admin.genres.index')
            ->with('success', 'Genre updated successfully.');
    }

    /**
     * Remove the specified genre from storage.
     */
    public function destroy(Genre $genre)
    {
        // Detach movies first to keep pivot table clean
        $genre->movies()->detach();

        $genre->delete();

        return redirect()
            ->route('admin.genres.index')
            ->with('success', 'Genre deleted successfully.');
    }
}