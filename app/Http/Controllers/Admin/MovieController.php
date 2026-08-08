<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ShowtimeScheduleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveMovieRequest;
use App\Models\Genre;
use App\Models\Movie;
use App\Services\MovieImageService;
use App\Services\MovieLifecycleService;
use App\Services\ShowtimeScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class MovieController extends Controller
{
public function index(Request $request)
{
    $search = trim((string) $request->query('search', ''));

    $query = Movie::query()
        ->with('genres');

    if ($search !== '') {
        $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('country', 'like', "%{$search}%")
                ->orWhere('status', 'like', "%{$search}%");
        });
    }

    $movies = $query
        ->orderByDesc('created_at')
        ->paginate(15)
        ->withQueryString();

    return view('admin.movies.index', compact(
        'movies',
        'search'
    ));
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
        $validated['status'] = Movie::STATUS_DRAFT;
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
            ->with('success', 'Đã tạo phim thành công.');
    }

    /**
     * Display the specified movie.
     */
    public function show(Movie $movie, MovieLifecycleService $lifecycle)
    {
        $movie->load('genres');

        $allowedTransitions = $lifecycle->allowedTransitions($movie);

        return view('admin.movies.show', compact('movie', 'allowedTransitions'));
    }

    /**
     * Show the form for editing the specified movie.
     */
    public function edit(Movie $movie)
    {
        abort_if($movie->status === Movie::STATUS_ARCHIVED, 409, 'Phim đã lưu trữ chỉ có thể xem.');
        $genres = Genre::all();
        $movie->load('genres');

        return view('admin.movies.edit', compact('movie', 'genres'));
    }

    /**
     * Update the specified movie in storage.
     */
    public function update(SaveMovieRequest $request, Movie $movie, MovieImageService $images, ShowtimeScheduleService $schedule): RedirectResponse
    {
        abort_if($movie->status === Movie::STATUS_ARCHIVED, 409, 'Phim đã lưu trữ chỉ có thể xem.');
        $validated = $request->validated();
        $genres = $validated['genres'] ?? [];
        unset($validated['genres'], $validated['poster'], $validated['cover_image']);
        unset($validated['status']);
        $validated['slug'] = $this->uniqueSlug($validated['slug'] ?? null, $validated['title'], $movie->id);
        $oldPoster = $movie->poster;
        $oldCover = $movie->cover_image;
        $stored = [];

        if (isset($validated['duration'])) {
            try {
                $schedule->assertMovieDurationChangeSafe($movie, (int) $validated['duration']);
            } catch (ShowtimeScheduleException $exception) {
                throw ValidationException::withMessages(['duration' => $exception->getMessage()]);
            }
        }

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
            ->with('success', 'Đã cập nhật phim thành công.');
    }

    public function lifecycle(Request $request, Movie $movie, MovieLifecycleService $lifecycle): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', 'string']]);
        $movie = $lifecycle->transition($movie, $validated['status'], $request->user());

        return redirect()->route('admin.movies.show', $movie)
            ->with('success', 'Đã cập nhật vòng đời phim. Dữ liệu suất chiếu và hình ảnh được giữ nguyên.');
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
