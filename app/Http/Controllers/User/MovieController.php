<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Models\Genre;
use App\Models\Movie;
use App\Services\CinemaContext;
use App\Services\PublicShowtimeCatalog;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    public function __construct(
        private readonly CinemaContext $cinemaContext,
        private readonly PublicShowtimeCatalog $catalog,
    ) {}

    /**
     * Hiển thị danh sách phim cho người dùng.
     */
    public function index(Request $request)
    {
        $query = Movie::query()
            ->with('genres')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->whereIn('status', ['now_showing', 'coming_soon']);

        $selectedCinema = null;
        if ($request->filled('cinema')) {
            $selectedCinema = Cinema::query()->active()->where('code', mb_strtoupper((string) $request->query('cinema')))->firstOrFail();
        }
        $selectedDate = $this->catalog->date($request->query('date'), $selectedCinema);
        if ($selectedCinema || $request->filled('date')) {
            $availableMovieIds = $this->catalog->forDate($selectedDate, $selectedCinema)->pluck('movie_id')->unique();
            $query->whereIn('id', $availableMovieIds);
        }
        $preferredCinema = $this->cinemaContext->preference();
        if (! $selectedCinema && $preferredCinema) {
            $query->withExists(['showtimes as preferred_branch_available' => fn ($showtimes) => $showtimes
                ->where('cinema_id', $preferredCinema->id)->where('status', 'active')->whereDate('show_date', $selectedDate)])
                ->orderByDesc('preferred_branch_available');
        }

        /*
        |--------------------------------------------------------------------------
        | Tìm kiếm phim
        |--------------------------------------------------------------------------
        */
        $search = trim((string) $request->query(
            'search',
            $request->query('keyword', '')
        ));

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%");
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Lọc trạng thái
        |--------------------------------------------------------------------------
        */
        $status = $request->query('status');

        if (in_array($status, ['now_showing', 'coming_soon'], true)) {
            $query->where('status', $status);
        }

        /*
        |--------------------------------------------------------------------------
        | Lọc thể loại
        |--------------------------------------------------------------------------
        */
        $genreId = $request->query(
            'genre',
            $request->query('genre_id')
        );

        if ($genreId) {
            $query->whereHas('genres', function ($q) use ($genreId) {
                $q->where('genres.id', $genreId);
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Lọc quốc gia
        |--------------------------------------------------------------------------
        */
        $country = $request->query('country');

        if ($country) {
            $query->where('country', $country);
        }

        /*
        |--------------------------------------------------------------------------
        | Sắp xếp
        |--------------------------------------------------------------------------
        */
        $sort = $request->query('sort', 'latest');

        switch ($sort) {
            case 'name':
                $query->orderBy('title', 'asc');
                break;

            case 'rating':
                $query->orderByDesc('reviews_avg_rating')
                    ->orderByDesc('reviews_count');
                break;

            case 'release_date':
                $query->orderByRaw('release_date IS NULL')
                    ->orderBy('release_date', 'asc');
                break;

            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;

            case 'latest':
            default:
                $query->orderByDesc('created_at');
                break;
        }

        /*
        |--------------------------------------------------------------------------
        | Phân trang
        |--------------------------------------------------------------------------
        */
        $movies = $query
            ->paginate(12)
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | Danh sách thể loại
        |--------------------------------------------------------------------------
        */
        $genres = Genre::query()
            ->orderBy('name')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Danh sách quốc gia
        |--------------------------------------------------------------------------
        */
        $countries = Movie::query()
            ->whereIn('status', ['now_showing', 'coming_soon'])
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->distinct()
            ->orderBy('country')
            ->pluck('country');

        /*
        |--------------------------------------------------------------------------
        | Tiêu đề trang
        |--------------------------------------------------------------------------
        */
        $pageTitle = match ($status) {
            'now_showing' => 'Phim đang chiếu',
            'coming_soon' => 'Phim sắp chiếu',
            default => 'Danh sách phim',
        };

        return view('user.movies.index', compact(
            'movies',
            'genres',
            'countries',
            'pageTitle',
            'search',
            'selectedCinema',
            'selectedDate'
        ) + [
            'cinemas' => Cinema::query()->active()->orderBy('name')->get(['id', 'code', 'name']),
            'dates' => $this->catalog->dates($selectedCinema),
            'preferredCinema' => $preferredCinema,
        ]);
    }

    /**
     * Hiển thị chi tiết phim theo slug.
     */
    public function show(Request $request, string $slug)
    {
        $movie = Movie::query()
            ->where('slug', $slug)
            ->whereIn('status', PublicShowtimeCatalog::MOVIE_STATUSES)
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->with('genres')
            ->firstOrFail();
        $selectedCinema = $request->filled('cinema')
            ? Cinema::query()->active()->where('code', mb_strtoupper((string) $request->query('cinema')))->firstOrFail()
            : null;
        $selectedDate = $this->catalog->date($request->query('date'), $selectedCinema);
        $showtimes = $this->catalog->forDate($selectedDate, $selectedCinema, $movie);
        $preferredCinema = $this->cinemaContext->preference();
        if (! $selectedCinema && $preferredCinema) {
            $showtimes = $showtimes->sortBy(fn ($showtime): array => [
                (int) $showtime->cinema_id === (int) $preferredCinema->id ? 0 : 1,
                $showtime->cinema->name,
                $showtime->show_time,
            ])->values();
        }

        return view('user.movies.show', [
            'movie' => $movie,
            'showtimes' => $showtimes,
            'selectedCinema' => $selectedCinema,
            'selectedDate' => $selectedDate,
            'cinemas' => Cinema::query()->active()->orderBy('name')->get(['id', 'code', 'name', 'address']),
            'dates' => $this->catalog->dates($selectedCinema),
            'preferredCinema' => $preferredCinema,
        ]);
    }
}
