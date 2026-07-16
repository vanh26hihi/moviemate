<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\Genre;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class MovieController extends Controller
{
    /**
     * Display a listing of the movies.
     */
    public function index(Request $request)
    {
        $query = Movie::with('genres');

        // Simple search by title
        if ($search = $request->query('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        $movies = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

        return view('admin.movies.index', compact('movies', 'search'));
    }

    /**
     * Show the form for creating a new movie.
     */
    public function create()
    {
        $genres = Genre::all();
        return view('admin.movies.create', compact('genres'));
    }

    /**
     * Store a newly created movie in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'         => 'required|string|max:255',
            'slug'          => 'nullable|string|max:255|unique:movies,slug',
            'description'   => 'nullable|string',
            'poster'        => 'nullable|image|max:2048',
            'cover_image'   => 'nullable|image|max:4096',
            'trailer_url'   => 'nullable|url',
            'country'       => 'nullable|string|max:100',
            'duration'      => 'nullable|integer|min:1',
            'age_rating'    => 'nullable|string|max:10',
            'release_date'  => 'nullable|date',
            'status'        => 'required|in:now_showing,coming_soon,stopped',
            'genres'        => 'nullable|array',
            'genres.*'      => 'exists:genres,id',
        ]);

        // Generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
            // Ensure uniqueness
            $original = $validated['slug'];
            $counter = 1;
            while (Movie::where('slug', $validated['slug'])->exists()) {
                $validated['slug'] = $original . '-' . $counter++;
            }
        }

        // Handle file uploads
        if ($request->hasFile('poster')) {
            $validated['poster'] = $request->file('poster')
                ->store('posters', 'public');
        }

        if ($request->hasFile('cover_image')) {
            $validated['cover_image'] = $request->file('cover_image')
                ->store('covers', 'public');
        }

        $movie = Movie::create($validated);

        // Sync genres
        if (!empty($validated['genres'])) {
            $movie->genres()->sync($validated['genres']);
        }

        return redirect()
            ->route('admin.movies.index')
            ->with('success', 'Movie created successfully.');
    }

    /**
     * Display the specified movie.
     */
    public function show(Movie $movie)
    {
        $movie->load('genres');
        return view('admin.movies.show', compact('movie'));
    }

    /**
     * Show the form for editing the specified movie.
     */
    public function edit(Movie $movie)
    {
        $genres = Genre::all();
        $movie->load('genres');
        return view('admin.movies.edit', compact('movie', 'genres'));
    }

    /**
     * Update the specified movie in storage.
     */
    public function update(Request $request, Movie $movie)
    {
        $validated = $request->validate([
            'title'         => 'required|string|max:255',
            'slug'          => 'nullable|string|max:255|unique:movies,slug,' . $movie->id,
            'description'   => 'nullable|string',
            'poster'        => 'nullable|image|max:2048',
            'cover_image'   => 'nullable|image|max:4096',
            'trailer_url'   => 'nullable|url',
            'country'       => 'nullable|string|max:100',
            'duration'      => 'nullable|integer|min:1',
            'age_rating'    => 'nullable|string|max:10',
            'release_date'  => 'nullable|date',
            'status'        => 'required|in:now_showing,coming_soon,stopped',
            'genres'        => 'nullable|array',
            'genres.*'      => 'exists:genres,id',
        ]);

        // Generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
            $original = $validated['slug'];
            $counter = 1;
            while (Movie::where('slug', $validated['slug'])->where('id', '!=', $movie->id)->exists()) {
                $validated['slug'] = $original . '-' . $counter++;
            }
        }

        // Handle poster replacement
        if ($request->hasFile('poster')) {
            // Delete old file if exists
            if ($movie->poster && Storage::disk('public')->exists($movie->poster)) {
                Storage::disk('public')->delete($movie->poster);
            }
            $validated['poster'] = $request->file('poster')
                ->store('posters', 'public');
        }

        // Handle cover image replacement
        if ($request->hasFile('cover_image')) {
            if ($movie->cover_image && Storage::disk('public')->exists($movie->cover_image)) {
                Storage::disk('public')->delete($movie->cover_image);
            }
            $validated['cover_image'] = $request->file('cover_image')
                ->store('covers', 'public');
        }

        $movie->update($validated);

        // Sync genres
        if (isset($validated['genres'])) {
            $movie->genres()->sync($validated['genres']);
        } else {
            $movie->genres()->detach();
        }

        return redirect()
            ->route('admin.movies.index')
            ->with('success', 'Movie updated successfully.');
    }

    /**
     * Remove the specified movie from storage.
     */
    public function destroy(Movie $movie)
    {
        // Detach genres first
        $movie->genres()->detach();

        // Delete associated files
        if ($movie->poster && Storage::disk('public')->exists($movie->poster)) {
            Storage::disk('public')->delete($movie->poster);
        }
        if ($movie->cover_image && Storage::disk('public')->exists($movie->cover_image)) {
            Storage::disk('public')->delete($movie->cover_image);
        }

        $movie->delete();

        return redirect()
            ->route('admin.movies.index')
            ->with('success', 'Movie deleted successfully.');
    }
}