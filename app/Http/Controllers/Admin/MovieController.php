<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ShowtimeScheduleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListMoviesRequest;
use App\Http\Requests\Admin\SaveMovieRequest;
use App\Models\Genre;
use App\Models\Movie;
use App\Models\PresentationFormat;
use App\Models\Showtime;
use App\Services\CinemaAccessService;
use App\Services\MovieImageService;
use App\Services\MovieLifecycleService;
use App\Services\MoviePresentationFormatService;
use App\Services\ShowtimeScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class MovieController extends Controller
{
    public function index(ListMoviesRequest $request, CinemaAccessService $cinemaAccess)
    {
        $filters = $request->validated();
        $search = (string) ($filters['search'] ?? '');
        $status = (string) ($filters['status'] ?? '');
        $genreId = isset($filters['genre']) ? (int) $filters['genre'] : null;
        $country = (string) ($filters['country'] ?? '');
        $sort = (string) ($filters['sort'] ?? 'updated_at');
        $direction = (string) ($filters['direction'] ?? 'desc');
        $currentCinema = $cinemaAccess->currentCinema($request->user());
        $timezone = $currentCinema?->timezone ?: config('cinema.timezone', 'Asia/Ho_Chi_Minh');
        $now = CarbonImmutable::now($timezone);
        $upcomingScope = function (Builder $query) use ($currentCinema, $now): Builder {
            return $query->where('status', 'active')
                ->when($currentCinema, fn (Builder $query): Builder => $query->where('cinema_id', $currentCinema->id))
                ->where(function (Builder $query) use ($now): void {
                    $query->whereDate('show_date', '>', $now->toDateString())
                        ->orWhere(function (Builder $query) use ($now): void {
                            $query->whereDate('show_date', $now->toDateString())
                                ->whereTime('show_time', '>', $now->format('H:i:s'));
                        });
                })
                ->whereHas('cinema', fn (Builder $query): Builder => $query->active())
                ->whereHas('room', fn (Builder $query): Builder => $query->where('status', 'active'));
        };

        $query = Movie::query()->with('genres')
            ->withCount(['showtimes as upcoming_showtimes_count' => $upcomingScope]);

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%");
            });
        }
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($genreId) {
            $query->whereHas('genres', fn ($genres) => $genres->whereKey($genreId));
        }
        if ($country !== '') {
            $query->where('country', $country);
        }

        $sortColumn = match ($sort) {
            'title' => 'title',
            'release_date' => 'release_date',
            default => 'updated_at',
        };
        $movies = $query->orderBy($sortColumn, $direction)->orderBy('id', $direction)
            ->paginate(15)->withQueryString();
        $genres = Genre::query()->orderBy('name')->get(['id', 'name']);
        $countries = Movie::query()->whereNotNull('country')->where('country', '!=', '')
            ->distinct()->orderBy('country')->pluck('country');
        $summary = [
            'movies' => Movie::query()->where('status', '!=', Movie::STATUS_ARCHIVED)->count(),
            'drafts' => Movie::query()->where('status', Movie::STATUS_DRAFT)->count(),
            'missing_release_dates' => Movie::query()
                ->where('status', '!=', Movie::STATUS_ARCHIVED)
                ->whereNull('release_date')
                ->count(),
            'upcoming_showtimes' => $upcomingScope(Showtime::query())->count(),
        ];

        return view('admin.movies.index', compact(
            'movies', 'search', 'status', 'genreId', 'country', 'genres', 'countries',
            'sort', 'direction', 'currentCinema', 'summary',
        ));
    }

    /**
     * Show the form for creating a new movie.
     */
    public function create()
    {
        $genres = Genre::all();
        $presentationFormats = PresentationFormat::query()->active()->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.movies.create', compact('genres', 'presentationFormats'));
    }

    /**
     * Store a newly created movie in storage.
     */
    public function store(SaveMovieRequest $request, MovieImageService $images, MoviePresentationFormatService $formats): RedirectResponse
    {
        $validated = $request->validated();
        $genres = $validated['genres'] ?? [];
        $formatIds = $validated['presentation_format_ids'] ?? [];
        unset($validated['genres'], $validated['presentation_format_ids'], $validated['poster'], $validated['cover_image']);
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

            $formats->create($validated, $genres, $formatIds);
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
        $movie->load(['genres', 'supportedPresentationFormats']);
        $presentationFormats = PresentationFormat::query()->active()->orderBy('sort_order')->orderBy('name')->get();
        $archivedPresentationFormats = $movie->supportedPresentationFormats->where('is_active', false)->sortBy('sort_order')->values();

        return view('admin.movies.edit', compact('movie', 'genres', 'presentationFormats', 'archivedPresentationFormats'));
    }

    /**
     * Update the specified movie in storage.
     */
    public function update(SaveMovieRequest $request, Movie $movie, MovieImageService $images, ShowtimeScheduleService $schedule, MoviePresentationFormatService $formats): RedirectResponse
    {
        abort_if($movie->status === Movie::STATUS_ARCHIVED, 409, 'Phim đã lưu trữ chỉ có thể xem.');
        $validated = $request->validated();
        $genres = $validated['genres'] ?? [];
        $formatIds = $validated['presentation_format_ids'] ?? [];
        unset($validated['genres'], $validated['presentation_format_ids'], $validated['poster'], $validated['cover_image']);
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

            $formats->update($movie, $validated, $genres, $formatIds);
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
