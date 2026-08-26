<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Models\Genre;
use App\Models\Movie;
use App\Models\Review;
use App\Services\CinemaContext;
use App\Services\CustomerShowtimeCatalogService;
use App\Services\PublicShowtimeCatalog;
use App\Services\ReviewService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MovieController extends Controller
{
    public function __construct(
        private readonly CinemaContext $cinemaContext,
        private readonly PublicShowtimeCatalog $catalog,
        private readonly CustomerShowtimeCatalogService $customerCatalog,
        private readonly ReviewService $reviewService,
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
            ->whereIn('status', Movie::PUBLIC_STATUSES);

        $selectedCinema = null;
        if ($request->filled('cinema')) {
            $selectedCinema = Cinema::query()->active()->where('code', mb_strtoupper((string) $request->query('cinema')))->firstOrFail();
        }
        $selectedDate = $this->catalog->date($request->query('date'), $selectedCinema);
        if ($selectedCinema || $request->filled('date')) {
            $availableMovieIds = $this->catalog->forDate($selectedDate, $selectedCinema)->pluck('movie_id')->unique();
            $query->whereIn('id', $availableMovieIds);
        }
        $this->catalog->withCustomerBookingAvailability($query, $selectedCinema);
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

        if (in_array($status, Movie::PUBLIC_STATUSES, true)) {
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
        | Lọc theo độ tuổi
        |--------------------------------------------------------------------------
        */
        $ageRating = $request->query('age_rating');

        if ($ageRating) {
            $query->where('age_rating', $ageRating);
        }

        $duration = $request->query('duration');
        match ($duration) {
            'short' => $query->where('duration', '<', 90),
            'medium' => $query->whereBetween('duration', [90, 120]),
            'long' => $query->where('duration', '>', 120),
            default => null,
        };

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
            ->whereIn('status', Movie::PUBLIC_STATUSES)
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->distinct()
            ->orderBy('country')
            ->pluck('country');

        /*
        |--------------------------------------------------------------------------
        | Danh sách độ tuổi
        |--------------------------------------------------------------------------
        */
        $ageRatings = Movie::query()
            ->whereIn('status', ['now_showing', 'coming_soon'])
            ->whereNotNull('age_rating')
            ->where('age_rating', '!=', '')
            ->distinct()
            ->orderBy('age_rating')
            ->pluck('age_rating');

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
            'ageRatings',
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
        $selectedCinema = $request->filled('cinema')
            ? Cinema::query()->active()->where('code', mb_strtoupper((string) $request->query('cinema')))->firstOrFail()
            : null;
        $movieQuery = Movie::query()
            ->where('slug', $slug)
            ->whereIn('status', PublicShowtimeCatalog::MOVIE_STATUSES)
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->with('genres');
        $this->catalog->withCustomerBookingAvailability($movieQuery, $selectedCinema);
        $movie = $movieQuery->firstOrFail();
        $selectedDate = $this->catalog->date($request->query('date'), $selectedCinema);
        $showtimes = $this->customerCatalog->forDate($selectedDate, $selectedCinema, $movie);
        $bookingAvailable = $movie->allowsCustomerBooking() && (bool) $movie->customer_booking_available;
        $preferredCinema = $this->cinemaContext->preference();
        if (! $selectedCinema && $preferredCinema) {
            $showtimes = $showtimes->sortBy(fn ($showtime): array => [
                (int) $showtime['cinema']->id === (int) $preferredCinema->id ? 0 : 1,
                $showtime['cinema']->name,
                $showtime['starts_at'],
            ])->values();
        }
        $publicReviews = Review::query()->where('movie_id', $movie->id)->where('moderation_status', Review::MODERATION_PUBLISHED)
            ->where('status', Review::STATUS_VISIBLE)->with('user:id,name')->latest('first_published_at')->paginate(10, ['*'], 'reviews_page')->withQueryString();
        $existingReview = $request->user()?->reviews()->where('movie_id', $movie->id)->first();
        $reviewBooking = null;
        if ($request->user()) {
            try {
                $reviewBooking = $this->reviewService->eligibleBooking($request->user(), $movie->id);
            } catch (ValidationException) {
                $reviewBooking = null;
            }
        }

        return view('user.movies.show', [
            'movie' => $movie,
            'showtimes' => $showtimes,
            'availableShowtimesCount' => $showtimes->count(),
            'selectedCinema' => $selectedCinema,
            'selectedDate' => $selectedDate,
            'cinemas' => Cinema::query()->active()->orderBy('name')->get(['id', 'code', 'name', 'address', 'latitude', 'longitude']),
            'dates' => $this->catalog->dates($selectedCinema),
            'preferredCinema' => $preferredCinema,
            'publicReviews' => $publicReviews,
            'existingReview' => $existingReview,
            'reviewBooking' => $reviewBooking,
            'bookingAvailable' => $bookingAvailable,
        ]);
    }
}
