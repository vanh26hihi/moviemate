<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Genre;
use App\Models\Movie;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Seat;
use App\Models\BookingSeat;

class MovieController extends Controller
{
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
        | Lọc theo độ tuổi
        |--------------------------------------------------------------------------
        */
        $ageRating = $request->query('age_rating');

        if ($ageRating) {
            $query->where('age_rating', $ageRating);
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
            'search'
        ));
    }

    /**
     * Hiển thị chi tiết phim theo slug.
     */
    public function show(string $slug)
    {
        $today = now()
            ->timezone('Asia/Ho_Chi_Minh')
            ->toDateString();

        $movie = Movie::query()
            ->where('slug', $slug)
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->with([
                'genres',
                'showtimes' => function ($query) use ($today) {
                    $query->where('status', 'active')
                        ->whereDate('show_date', '>=', $today)
                        ->orderBy('show_date')
                        ->orderBy('show_time');
                },
            ])
            ->firstOrFail();

        $now = now()->timezone('Asia/Ho_Chi_Minh');

        /*
        |--------------------------------------------------------------------------
        | Loại bỏ suất chiếu đã qua
        |--------------------------------------------------------------------------
        */
        $showtimes = $movie->showtimes
            ->filter(function ($showtime) use ($now) {
                if (! $showtime->show_date || ! $showtime->show_time) {
                    return false;
                }
                $showDate = Carbon::parse($showtime->show_date)
                    ->timezone('Asia/Ho_Chi_Minh');

                if ($showDate->isAfter($now->copy()->startOfDay())) {
                    return true;
                }

                if ($showDate->isSameDay($now)) {
                    return $showtime->show_time >= $now->format('H:i:s');
                }

                return false;
            })
            ->values();
                                /*
|--------------------------------------------------------------------------
| Tính số ghế còn trống của từng suất chiếu
|--------------------------------------------------------------------------
*/
  $showtimes->each(function ($showtime) {
    $totalSeats = Seat::query()
        ->where('room_id', $showtime->room_id)
        ->where('status', 'active')
        ->count();

    $bookedSeats = BookingSeat::query()
        ->where('showtime_id', $showtime->id)
        ->count();

    $showtime->available_seats = max(
        $totalSeats - $bookedSeats,
        0
    );
});

            $availableShowtimesCount = $showtimes->count();

      return view('user.movies.show', compact(
    'movie',
    'showtimes',
    'availableShowtimesCount'
));
    }
}