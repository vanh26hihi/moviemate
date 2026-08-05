<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveMovieRequest;
use App\Models\Genre;
use App\Models\Movie;
use App\Services\MovieImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

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
    public function store(SaveMovieRequest $request, MovieImageService $images): RedirectResponse
    {
        $validated = $request->validated();
        $genres = $validated['genres'] ?? [];
        unset($validated['genres'], $validated['poster'], $validated['cover_image']);
        $validated['slug'] = $this->uniqueSlug($validated['slug'] ?? null, $validated['title']);
        $stored = [];

        try {
            if ($request->hasFile('poster')) {
                $validated['poster'] = $stored[] = $images->store($request->file('poster'), MovieImageService::POSTER);
            }
            if ($request->hasFile('cover_image')) {
                $validated['cover_image'] = $stored[] = $images->store($request->file('cover_image'), MovieImageService::BANNER);
            }

            DB::transaction(function () use ($validated, $genres): void {
                $movie = Movie::create($validated);
                $movie->genres()->sync($genres);
            });
        } catch (Throwable $exception) {
            $images->deleteStored($stored);
            throw $exception;
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
    public function update(SaveMovieRequest $request, Movie $movie, MovieImageService $images): RedirectResponse
    {
        $validated = $request->validated();
        $genres = $validated['genres'] ?? [];
        unset($validated['genres'], $validated['poster'], $validated['cover_image']);
        $validated['slug'] = $this->uniqueSlug($validated['slug'] ?? null, $validated['title'], $movie->id);
        $oldPoster = $movie->poster;
        $oldCover = $movie->cover_image;
        $stored = [];

        try {
            if ($request->hasFile('poster')) {
                $validated['poster'] = $stored[] = $images->store($request->file('poster'), MovieImageService::POSTER);
            }
            if ($request->hasFile('cover_image')) {
                $validated['cover_image'] = $stored[] = $images->store($request->file('cover_image'), MovieImageService::BANNER);
            }

            DB::transaction(function () use ($movie, $validated, $genres): void {
                $movie->update($validated);
                $movie->genres()->sync($genres);
            });
        } catch (Throwable $exception) {
            $images->deleteStored($stored);
            throw $exception;
        }

        if ($request->hasFile('poster')) {
            $images->deleteIfUnreferenced($oldPoster);
        }
        if ($request->hasFile('cover_image')) {
            $images->deleteIfUnreferenced($oldCover);
        }

        return redirect()
            ->route('admin.movies.index')
            ->with('success', 'Movie updated successfully.');
    }

    /**
     * Remove the specified movie from storage.
     */
    public function destroy(Movie $movie, MovieImageService $images): RedirectResponse
    {
        $poster = $movie->poster;
        $cover = $movie->cover_image;

        DB::transaction(function () use ($movie): void {
            $movie->genres()->detach();
            $movie->delete();
        });

        $images->deleteIfUnreferenced($poster);
        $images->deleteIfUnreferenced($cover);

        return redirect()
            ->route('admin.movies.index')
            ->with('success', 'Movie deleted successfully.');
    }

    private function uniqueSlug(?string $requested, string $title, ?int $excludingMovieId = null): string
    {
        if (filled($requested)) {
            return $requested;
        }

        $base = Str::slug($title) ?: 'movie';
        $slug = $base;
        $counter = 1;

        while (Movie::query()
            ->when($excludingMovieId, fn ($query) => $query->whereKeyNot($excludingMovieId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
