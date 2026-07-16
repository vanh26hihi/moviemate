<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MovieController extends Controller
{
    /**
     * List movies (now showing & coming soon) with optional search and genre filter.
     */
    public function index(Request $request)
    {
        $query = Movie::with('genres')
            ->whereIn('status', ['now_showing', 'coming_soon']);

        if ($search = $request->query('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        if ($genreId = $request->query('genre_id')) {
            $query->whereHas('genres', function ($q) use ($genreId) {
                $q->where('genres.id', $genreId);
            });
        }

        $movies = $query->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        return view('user.movies.index', compact('movies'));
    }

    /**
     * Show movie detail by slug, with active future showtimes.
     */
    public function show($slug)
    {
        $movie = Movie::where('slug', $slug)
            ->with(['genres', 'showtimes' => function ($q) {
                $q->where('status', 'active')
                  ->whereDate('show_date', '>=', now()->timezone('Asia/Ho_Chi_Minh')->toDateString())
                  ->orderBy('show_date')
                  ->orderBy('show_time');
            }])
            ->firstOrFail();

        // Filter out showtimes that have already passed (same day, earlier time)
        $now = now()->timezone('Asia/Ho_Chi_Minh');
        $showtimes = $movie->showtimes->filter(function ($show) use ($now) {
            $showDate = $show->show_date;
            if ($showDate->isAfter($now->toDateString())) {
                return true;
            }
            if ($showDate->isSameDay($now) && $show->show_time >= $now->format('H:i:s')) {
                return true;
            }
            return false;
        });

        return view('user.movies.show', compact('movie', 'showtimes'));
    }
}