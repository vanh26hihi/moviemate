<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Services\CinemaContext;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    public function __construct(private readonly CinemaContext $cinemaContext) {}

    /**
     * List movies (now showing & coming soon) with optional search and genre filter.
     */
    public function index(Request $request)
    {
        $query = Movie::with('genres')
            ->whereIn('status', ['now_showing', 'coming_soon']);

        $search = $request->query('keyword', $request->query('search'));

        if ($search) {
            $query->where('title', 'like', "%{$search}%");
        }

        if ($status = $request->query('status')) {
            if (in_array($status, ['now_showing', 'coming_soon'], true)) {
                $query->where('status', $status);
            }
        }

        if ($country = $request->query('country')) {
            $query->where('country', $country);
        }

        $genreId = $request->query('genre_id', $request->query('genre'));

        if ($genreId) {
            $query->whereHas('genres', function ($q) use ($genreId) {
                $q->where('genres.id', $genreId);
            });
        }

        $movies = $query->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        $countries = Movie::whereNotNull('country')
            ->where('country', '!=', '')
            ->distinct()
            ->orderBy('country')
            ->pluck('country');

        $pageTitle = match ($request->query('status')) {
            'now_showing' => 'Phim đang chiếu',
            'coming_soon' => 'Phim sắp chiếu',
            default => 'Tất cả phim',
        };

        return view('user.movies.index', compact('movies', 'countries', 'pageTitle'));
    }

    /**
     * Show movie detail by slug, with active future showtimes.
     */
    public function show($slug)
    {
        $movie = Movie::query()->where('slug', $slug)->with('genres')->firstOrFail();
        $showtimes = $movie->showtimes()->with(['cinema', 'room'])
            ->where('cinema_id', $this->cinemaContext->id())
            ->whereHas('room', fn ($query) => $query->where('status', 'active'))
            ->where('status', 'active')
            ->whereDate('show_date', '>=', now()->timezone('Asia/Ho_Chi_Minh')->toDateString())
            ->orderBy('show_date')->orderBy('show_time')->get();

        // Filter out showtimes that have already passed (same day, earlier time)
        $now = now()->timezone('Asia/Ho_Chi_Minh');
        $showtimes = $showtimes->filter(function ($show) use ($now) {
            if (! $show->show_date || ! $show->show_time) {
                return false;
            }

            $showDate = Carbon::parse($show->show_date);
            if ($showDate->isAfter($now->toDateString())) {
                return true;
            }
            $startsAt = Carbon::parse($showDate->format('Y-m-d').' '.$show->show_time, 'Asia/Ho_Chi_Minh');

            return $startsAt->copy()->addMinutes(30)->isFuture();
        });

        return view('user.movies.show', compact('movie', 'showtimes'));
    }
}
